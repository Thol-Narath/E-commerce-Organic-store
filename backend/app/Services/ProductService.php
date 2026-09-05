<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductService
{
    use UniqueSlug;

    /**
     * Allowed API sort keys mapped to safe Eloquent orderBy clauses.
     */
    public const SORT_WHITELIST = [
        'newest' => ['created_at', 'desc'],
        'oldest' => ['created_at', 'asc'],
        'price_low' => ['price', 'asc'],
        'price_high' => ['price', 'desc'],
        'name_asc' => ['name', 'asc'],
        'name_desc' => ['name', 'desc'],
        'best_selling' => ['sales_count', 'desc'],
    ];

    /**
     * Build a customer-facing (active + available) product query with the
     * provided search, filters, category and sort. Never exposes inactive
     * or draft products regardless of query parameters.
     */
    public function publicQuery(array $filters): Builder
    {
        return $this->applyFilters(
            Product::query()->active()->with(['category:id,name,slug', 'primaryImage']),
            $filters,
            true
        );
    }

    /**
     * Build an admin query that may include inactive/draft products.
     */
    public function adminQuery(array $filters): Builder
    {
        return $this->applyFilters(
            Product::query()->with(['category:id,name,slug', 'images', 'primaryImage']),
            $filters,
            false
        );
    }

    /**
     * Apply validated search/filters/sort to a product query.
     *
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters, bool $forceActive): Builder
    {
        if (! empty($filters['search'])) {
            $term = trim((string) $filters['search']);
            $query->where(function (Builder $q) use ($term) {
                // Index-backed term search: the FULLTEXT MATCH branch lets
                // MySQL/MariaDB drive lookup from the products FULLTEXT index
                // (products_name_short_description_description_fulltext). The
                // LIKE clauses are ORed in so substring matches and rows
                // inserted in the current (uncommitted) transaction still
                // resolve — InnoDB fulltext indexes do not see uncommitted rows.
                if (mb_strlen($term) >= 3) {
                    $q->whereFullText(
                        ['name', 'short_description', 'description'],
                        $this->fulltextSearchTerm($term),
                        ['mode' => 'boolean']
                    );
                }

                $q->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('short_description', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    // SKU uses its unique index for exact + prefix matches.
                    ->orWhere('sku', $term)
                    ->orWhere('sku', 'like', $term.'%');

                if (ctype_digit($term)) {
                    $q->orWhere('id', (int) $term);
                }
            });
        }

        if (isset($filters['category_id']) || isset($filters['category_slug'])) {
            $query->whereHas('category', function (Builder $q) use ($filters) {
                if (isset($filters['category_id'])) {
                    $q->where('id', $filters['category_id']);
                }
                if (isset($filters['category_slug'])) {
                    $q->where('slug', $filters['category_slug']);
                }
            });
        }

        if (isset($filters['min_price']) && $filters['min_price'] !== '') {
            $query->where('price', '>=', (float) $filters['min_price']);
        }

        if (isset($filters['max_price']) && $filters['max_price'] !== '') {
            $query->where('price', '<=', (float) $filters['max_price']);
        }

        if (isset($filters['featured']) && $filters['featured'] !== '') {
            $query->where('is_featured', filter_var($filters['featured'], FILTER_VALIDATE_BOOLEAN));
        }

        // The public API may never switch status to expose inactive products.
        if ($forceActive) {
            $query->active();
        } elseif (isset($filters['status']) && in_array($filters['status'], ['active', 'inactive', 'draft'])) {
            $query->where('status', $filters['status']);
        }

        $sort = $filters['sort'] ?? 'newest';

        if ($sort === 'best_selling') {
            $query->withCount('orderItems as order_items_count')
                ->orderBy('order_items_count', 'desc')
                ->orderBy('id', 'desc');
        } elseif (isset(self::SORT_WHITELIST[$sort])) {
            [$column, $dir] = self::SORT_WHITELIST[$sort];
            $query->orderBy($column, $dir)->orderBy('id', 'desc');
        } else {
            $query->orderBy('created_at', 'desc')->orderBy('id', 'desc');
        }

        return $query;
    }

    /**
     * Build a MySQL FULLTEXT boolean-mode query string from user input.
     *
     * Each sanitized word becomes a required prefix match (`+word*`) so the
     * search is and-ed across words while still using the FULLTEXT index.
     * Special boolean-mode characters are stripped to avoid injection and
     * syntax errors.
     */
    protected function fulltextSearchTerm(string $term): string
    {
        $words = preg_split('/\s+/u', $term) ?: [];

        $parts = [];
        foreach ($words as $word) {
            $word = (string) preg_replace('/["+\-><()~*@]/', '', $word);
            if ($word !== '') {
                $parts[] = '+'.$word.'*';
            }
        }

        return $parts !== [] ? implode(' ', $parts) : $term;
    }

    public function createProduct(array $data, ?UploadedFile $primaryImage = null): Product
    {
        return DB::transaction(function () use ($data, $primaryImage) {
            $data['slug'] = $this->uniqueSlug(Product::class, $data['name'], $data['slug'] ?? null);

            $product = Product::create($data);

            if ($primaryImage) {
                $this->storeImage($product, $primaryImage, true);
            }

            return $product->load(['category:id,name,slug', 'images']);
        });
    }

    public function updateProduct(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            if (isset($data['name']) && ($data['name'] !== $product->name || isset($data['slug']))) {
                $data['slug'] = $this->uniqueSlug(Product::class, $data['name'], $data['slug'] ?? null, $product->id);
            } elseif (array_key_exists('slug', $data)) {
                $data['slug'] = $this->uniqueSlug(Product::class, $data['name'] ?? $product->name, $data['slug'], $product->id);
            }

            $product->update($data);

            return $product->fresh(['category:id,name,slug', 'images']);
        });
    }

    /**
     * Upload an additional gallery image for a product (never primary by default).
     */
    public function uploadImage(Product $product, UploadedFile $file, ?string $altText = null): ProductImage
    {
        return DB::transaction(function () use ($product, $file, $altText) {
            return $this->storeImage($product, $file, false, $altText);
        });
    }

    /**
     * Mark an image as the single primary image for its product.
     */
    public function setPrimaryImage(Product $product, ProductImage $image): ProductImage
    {
        return DB::transaction(function () use ($product, $image) {
            if ($image->product_id !== $product->id) {
                abort(422, 'The image does not belong to this product.');
            }

            $product->images()->update(['is_primary' => false]);
            $image->update(['is_primary' => true]);

            return $image->refresh();
        });
    }

    public function deleteImage(Product $product, ProductImage $image): void
    {
        if ($image->product_id !== $product->id) {
            abort(404, 'Image not found.');
        }

        DB::transaction(function () use ($product, $image) {
            $image->delete();

            if (Storage::disk('public')->exists($image->image)) {
                Storage::disk('public')->delete($image->image);
            }

            // Ensure the product keeps a primary image if one was deleted.
            if (! $product->images()->where('is_primary', true)->exists()) {
                $next = $product->images()->orderBy('sort_order')->first();
                if ($next) {
                    $next->update(['is_primary' => true]);
                }
            }
        });
    }

    /**
     * Store an uploaded image via Laravel Storage, set primary flag when needed,
     * and create the ProductImage record.
     */
    public function storeImage(Product $product, UploadedFile $file, bool $makePrimary, ?string $altText = null): ProductImage
    {
        // Force a safe, unique disk filename. The original name is never trusted.
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $filename = Str::slug($product->slug ?: 'product').'-'.Str::uuid().'.'.$extension;
        $path = $file->storeAs('images/products', $filename, 'public');

        if ($makePrimary) {
            $product->images()->update(['is_primary' => false]);
        }

        return $product->images()->create([
            'image' => $path,
            'alt_text' => $altText,
            'sort_order' => ($product->images()->count()) + 1,
            'is_primary' => $makePrimary || $product->images()->count() === 0,
        ]);
    }
}
