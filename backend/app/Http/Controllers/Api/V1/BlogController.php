<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogResource;
use App\Models\Blog;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    use ApiResponse, Paginates;

    public function __construct(private readonly CacheService $cacheService) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->cacheService->remember(
            'content',
            ['listing' => 'blogs', 'page' => (int) $request->input('page', 1), 'per_page' => $this->perPage($request)],
            CacheService::TTL_MEDIUM,
            function () use ($request) {
                $paginator = Blog::query()
                    ->published()
                    ->paginate($this->perPage($request))
                    ->withQueryString();

                return [
                    'items' => BlogResource::collection($paginator->items())->resolve(),
                    'pagination' => $this->pagination($paginator),
                ];
            }
        );

        return $this->success($data, 'Blogs retrieved successfully.');
    }

    public function show(string $slug): JsonResponse
    {
        $data = $this->cacheService->remember(
            'content',
            ['blog' => $slug],
            CacheService::TTL_MEDIUM,
            function () use ($slug) {
                $blog = Blog::query()
                    ->published()
                    ->where('slug', $slug)
                    ->first();

                if (! $blog) {
                    return null;
                }

                return (new BlogResource($blog))->resolve();
            }
        );

        if ($data === null) {
            return $this->error('Blog not found.', null, 404);
        }

        return $this->success($data, 'Blog retrieved successfully.');
    }
}
