<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TestimonialResource;
use App\Models\Testimonial;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestimonialController extends Controller
{
    use ApiResponse, Paginates;

    public function __construct(private readonly CacheService $cacheService) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->cacheService->remember(
            'content',
            ['listing' => 'testimonials', 'page' => (int) $request->input('page', 1), 'per_page' => $this->perPage($request)],
            CacheService::TTL_MEDIUM,
            function () use ($request) {
                $paginator = Testimonial::query()
                    ->active()
                    ->orderBy('sort_order')
                    ->paginate($this->perPage($request))
                    ->withQueryString();

                return [
                    'items' => TestimonialResource::collection($paginator->items())->resolve(),
                    'pagination' => $this->pagination($paginator),
                ];
            }
        );

        return $this->success($data, 'Testimonials retrieved successfully.');
    }
}
