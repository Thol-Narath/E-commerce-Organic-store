<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Requests\Product\UploadProductImageRequest;
use App\Http\Resources\AdminProductResource;
use App\Http\Resources\ProductImageResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProductController extends Controller
{
    use ApiResponse, Paginates;

    public function __construct(private readonly ProductService $productService) {}

    /**
     * List all products (including inactive/draft), with search/filter/sort.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search', 'category_id', 'category_slug', 'min_price',
            'max_price', 'featured', 'status', 'sort',
        ]);

        $paginator = $this->productService->adminQuery($filters)
            ->withTrashed()
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->success([
            'items' => AdminProductResource::collection($paginator->items()),
            'pagination' => $this->pagination($paginator),
        ], 'Products retrieved successfully.');
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        $product->load(['category:id,name,slug', 'images']);

        return $this->success(new AdminProductResource($product), 'Product retrieved successfully.');
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->only([
            'category_id', 'name', 'description', 'short_description', 'sku', 'barcode',
            'price', 'compare_at_price', 'cost_price', 'stock_quantity', 'low_stock_threshold',
            'is_featured', 'is_best_seller', 'status', 'unit', 'weight', 'min_order_qty',
        ]);

        $primaryImage = $request->file('images.0') ?? $request->file('image');
        $product = $this->productService->createProduct($data, $primaryImage);

        return $this->success(
            new AdminProductResource($product),
            'Product created successfully.',
            201
        );
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $data = $request->only([
            'category_id', 'name', 'description', 'short_description', 'sku', 'barcode',
            'price', 'compare_at_price', 'cost_price', 'stock_quantity', 'low_stock_threshold',
            'is_featured', 'is_best_seller', 'status', 'unit', 'weight', 'min_order_qty',
        ]);

        $product = $this->productService->updateProduct($product, $data);

        return $this->success(
            new AdminProductResource($product),
            'Product updated successfully.'
        );
    }

    /**
     * Soft-delete a product so historical orders remain valid.
     */
    public function destroy(Request $request, Product $product): JsonResponse
    {
        $product->delete();

        return $this->success(null, 'Product deleted successfully.');
    }

    /**
     * Toggle or set a product's publish status (active/inactive/draft).
     */
    public function updateStatus(Request $request, Product $product): JsonResponse
    {
        $request->validate(['status' => ['required', 'in:active,inactive,draft']]);

        $product->update(['status' => $request->input('status')]);

        return $this->success(
            new AdminProductResource($product->fresh(['category:id,name,slug', 'images'])),
            'Product status updated successfully.'
        );
    }

    /**
     * Toggle or set whether a product is featured.
     */
    public function updateFeatured(Request $request, Product $product): JsonResponse
    {
        $request->validate(['is_featured' => ['required', 'boolean']]);

        $product->update(['is_featured' => filter_var($request->input('is_featured'), FILTER_VALIDATE_BOOLEAN)]);

        return $this->success(
            new AdminProductResource($product->fresh(['category:id,name,slug', 'images'])),
            'Product featured status updated successfully.'
        );
    }

    /**
     * Upload a gallery image to a product.
     */
    public function uploadImage(UploadProductImageRequest $request, Product $product): JsonResponse
    {
        $image = $this->productService->uploadImage(
            $product,
            $request->file('image'),
            $request->input('alt_text')
        );

        return $this->success(
            new ProductImageResource($image),
            'Image uploaded successfully.',
            201
        );
    }

    /**
     * Set an image as the single primary image for a product.
     */
    public function setPrimaryImage(Request $request, Product $product, ProductImage $image): JsonResponse
    {
        $image = $this->productService->setPrimaryImage($product, $image);

        return $this->success(
            new ProductImageResource($image),
            'Primary image updated successfully.'
        );
    }

    /**
     * Delete a product image.
     */
    public function deleteImage(Request $request, Product $product, ProductImage $image): JsonResponse
    {
        $this->productService->deleteImage($product, $image);

        return $this->success(null, 'Image deleted successfully.');
    }
}
