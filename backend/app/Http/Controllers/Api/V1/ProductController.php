<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ApiResponse, Paginates;

    public function __construct(private readonly ProductService $productService) {}

    /**
     * List/search/filter/sort/paginate active products.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search', 'category_id', 'category_slug', 'min_price',
            'max_price', 'featured', 'sort',
        ]);

        $paginator = $this->productService->publicQuery($filters)
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->success([
            'items' => ProductResource::collection($paginator->items()),
            'pagination' => $this->pagination($paginator),
        ], 'Products retrieved successfully.');
    }

    /**
     * List featured active products.
     */
    public function featured(Request $request): JsonResponse
    {
        $filters = $request->only(['category_id', 'category_slug', 'sort']);
        $filters['featured'] = true;

        $query = $this->productService->publicQuery($filters);

        // Featured is a curated listing; accept a larger page size but stay capped.
        $paginator = $query->paginate($this->perPage($request))->withQueryString();

        return $this->success([
            'items' => ProductResource::collection($paginator->items()),
            'pagination' => $this->pagination($paginator),
        ], 'Featured products retrieved successfully.');
    }

    /**
     * Show a single active product.
     */
    public function show(Request $request, Product $product): JsonResponse
    {
        if (! $product->trashed() && ! $product->isAvailable()) {
            return $this->error('Product not found.', null, 404);
        }

        $product->load(['category:id,name,slug', 'images']);

        return $this->success(new ProductResource($product), 'Product retrieved successfully.');
    }
}
