<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    use ApiResponse, Paginates;

    public function __construct(private readonly CacheService $cacheService) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->cacheService->remember(
            'content',
            ['listing' => 'banners', 'page' => (int) $request->input('page', 1), 'per_page' => $this->perPage($request, 10)],
            CacheService::TTL_MEDIUM,
            function () use ($request) {
                $paginator = Banner::query()
                    ->active()
                    ->orderBy('sort_order')
                    ->orderBy('title')
                    ->paginate($this->perPage($request, 10))
                    ->withQueryString();

                return [
                    'items' => BannerResource::collection($paginator->items())->resolve(),
                    'pagination' => $this->pagination($paginator),
                ];
            }
        );

        return $this->success($data, 'Banners retrieved successfully.');
    }
}
