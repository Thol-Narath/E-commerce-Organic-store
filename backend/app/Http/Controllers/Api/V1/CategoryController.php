<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\CacheService;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use ApiResponse, Paginates;

    public function __construct(
        private readonly ProductService $productService,
        private readonly CacheService $cacheService
    ) {}

    /**
     * List active categories with their active product counts.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $this->cacheService->remember(
            'categories',
            ['listing' => 'index', 'page' => (int) $request->input('page', 1), 'per_page' => $this->perPage($request)],
            CacheService::TTL_MEDIUM,
            function () use ($request) {
                $paginator = Category::query()
                    ->active()
                    ->withCount(['products as products_count' => fn ($q) => $q->active()])
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->paginate($this->perPage($request))
                    ->withQueryString();

                return [
                    'items' => CategoryResource::collection($paginator->items())->resolve(),
                    'pagination' => $this->pagination($paginator),
                ];
            }
        );

        return $this->success($data, 'Categories retrieved successfully.');
    }

    /**
     * Show a single active category.
     */
    public function show(Request $request, Category $category): JsonResponse
    {
        if (! $category->isActive()) {
            return $this->error('Category not found.', null, 404);
        }

        $data = $this->cacheService->remember(
            'categories',
            ['detail' => $category->slug, 'id' => $category->id],
            CacheService::TTL_MEDIUM,
            function () use ($category) {
                return (new CategoryResource($category->loadCount(['products as products_count' => fn ($q) => $q->active()])))->resolve();
            }
        );

        return $this->success($data, 'Category retrieved successfully.');
    }

    /**
     * List active products belonging to an active category.
     */
    public function products(Request $request, Category $category): JsonResponse
    {
        if (! $category->isActive()) {
            return $this->error('Category not found.', null, 404);
        }

        $filters = $request->only([
            'search', 'min_price', 'max_price', 'featured', 'sort',
        ]);
        $filters['category_id'] = $category->id;

        $data = $this->cacheService->remember(
            'categories',
            ['products' => $category->slug, 'filters' => $filters, 'page' => (int) $request->input('page', 1), 'per_page' => $this->perPage($request)],
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
}
