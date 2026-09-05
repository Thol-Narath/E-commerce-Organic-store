<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\UniqueSlug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminCategoryController extends Controller
{
    use ApiResponse, Paginates, UniqueSlug;

    /**
     * List all categories (including inactive).
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = Category::query()
            ->withCount(['products as products_count' => fn ($q) => $q->withoutTrashed()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->success([
            'items' => CategoryResource::collection($paginator->items()),
            'pagination' => $this->pagination($paginator),
        ], 'Categories retrieved successfully.');
    }

    /**
     * Show a single category (including inactive).
     */
    public function show(Request $request, Category $category): JsonResponse
    {
        $category->loadCount(['products as products_count' => fn ($q) => $q->withoutTrashed()]);

        return $this->success(new CategoryResource($category), 'Category retrieved successfully.');
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->only(['parent_id', 'name', 'description', 'status', 'sort_order']);
        $data['slug'] = $this->uniqueSlug(Category::class, $request->input('name'), $request->input('slug'));

        if ($request->hasFile('icon')) {
            $data['icon'] = $this->storeIcon($request->file('icon'));
        }

        $category = Category::create($data);

        return $this->success(new CategoryResource($category), 'Category created successfully.', 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $data = $request->only(['parent_id', 'name', 'description', 'status', 'sort_order']);

        if ($request->filled('name') || $request->has('slug')) {
            $data['slug'] = $this->uniqueSlug(
                Category::class,
                $request->input('name', $category->name),
                $request->input('slug'),
                $category->id
            );
        }

        if ($request->hasFile('icon')) {
            if ($category->icon && Storage::disk('public')->exists($category->icon)) {
                Storage::disk('public')->delete($category->icon);
            }
            $data['icon'] = $this->storeIcon($request->file('icon'));
        }

        $category->update($data);

        return $this->success(new CategoryResource($category->fresh()), 'Category updated successfully.');
    }

    /**
     * Soft-delete a category, or return a clear error if it still has products.
     */
    public function destroy(Request $request, Category $category): JsonResponse
    {
        if (Product::where('category_id', $category->id)->exists()) {
            return $this->error(
                'Cannot delete this category because it still has products. Reassign or delete its products first.',
                null,
                409
            );
        }

        $category->delete();

        return $this->success(null, 'Category deleted successfully.');
    }

    protected function storeIcon($file): string
    {
        $filename = Str::slug($file->getClientOriginalName() ?: 'icon').'-'.Str::uuid().'.'.$file->getClientOriginalExtension();

        return $file->storeAs('images/categories', $filename, 'public');
    }
}
