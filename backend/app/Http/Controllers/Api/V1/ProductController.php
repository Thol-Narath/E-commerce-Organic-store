<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\CacheService;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use ApiResponse, Paginates;

    public function __construct(
        private readonly ProductService $productService,
        private readonly CacheService $cacheService
    ) {}

    /**
     * List/search/filter/sort/paginate active products.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'search', 'category_id', 'category_slug', 'min_price',
            'max_price', 'featured', 'best_seller', 'discounted', 'sort',
        ]);

        $data = $this->cacheService->remember(
            'products',
            ['filters' => $filters, 'page' => (int) $request->input('page', 1), 'per_page' => $this->perPage($request)],
            CacheService::TTL_SHORT,
            function () use ($filters, $request) {
                $paginator = $this->productService->publicQuery($filters)
                    ->paginate($this->perPage($request))
                    ->withQueryString();

                return [
                    'items' => ProductResource::collection($paginator->items())->resolve(),
                    'pagination' => $this->pagination($paginator),
                ];
            }
        );

        return $this->success($data, 'Products retrieved successfully.');
    }

    /**
     * List featured active products.
     */
    public function featured(Request $request): JsonResponse
    {
        $filters = $request->only(['category_id', 'category_slug', 'sort']);
        $filters['featured'] = true;

        $data = $this->cacheService->remember(
            'products',
            ['listing' => 'featured', 'filters' => $filters, 'page' => (int) $request->input('page', 1), 'per_page' => $this->perPage($request)],
            CacheService::TTL_SHORT,
            function () use ($filters, $request) {
                $paginator = $this->productService->publicQuery($filters)
                    ->paginate($this->perPage($request))
                    ->withQueryString();

                return [
                    'items' => ProductResource::collection($paginator->items())->resolve(),
                    'pagination' => $this->pagination($paginator),
                ];
            }
        );

        return $this->success($data, 'Featured products retrieved successfully.');
    }

    /**
     * Show a single active product.
     */
    public function show(Request $request, Product $product): JsonResponse
    {
        if (! $product->trashed() && ! $product->isAvailable()) {
            return $this->error('Product not found.', null, 404);
        }

        $data = $this->cacheService->remember(
            'products',
            ['detail' => $product->slug, 'id' => $product->id],
            CacheService::TTL_SHORT,
            function () use ($product) {
                $product->load(['category:id,name,slug', 'primaryImage', 'images']);

                return (new ProductResource($product))->resolve();
            }
        );

        return $this->success($data, 'Product retrieved successfully.');
    }
}
