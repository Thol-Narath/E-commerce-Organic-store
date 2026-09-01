<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use ApiResponse, Paginates;

    public function __construct(private readonly ProductService $productService) {}

    /**
     * List active categories with their active product counts.
     */
    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->active()
            ->withCount(['products as products_count' => fn ($q) => $q->active()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->success(
            CategoryResource::collection($categories),
            'Categories retrieved successfully.'
        );
    }

    /**
     * Show a single active category.
     */
    public function show(Request $request, Category $category): JsonResponse
    {
        if (! $category->isActive()) {
            return $this->error('Category not found.', null, 404);
        }

        return $this->success(
            new CategoryResource($category->loadCount(['products as products_count' => fn ($q) => $q->active()])),
            'Category retrieved successfully.'
        );
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

        $paginator = $this->productService->publicQuery($filters)
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->success([
            'items' => ProductResource::collection($paginator->items()),
            'pagination' => $this->pagination($paginator),
        ], 'Products retrieved successfully.');
    }
}
