<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BlogResource;
use App\Models\Blog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    use ApiResponse, Paginates;

    public function index(Request $request): JsonResponse
    {
        $paginator = Blog::query()
            ->published()
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->success([
            'items' => BlogResource::collection($paginator->items()),
            'pagination' => $this->pagination($paginator),
        ], 'Blogs retrieved successfully.');
    }

    public function show(string $slug): JsonResponse
    {
        $blog = Blog::query()
            ->published()
            ->where('slug', $slug)
            ->first();

        if (! $blog) {
            return $this->error('Blog not found.', null, 404);
        }

        return $this->success(
            new BlogResource($blog),
            'Blog retrieved successfully.'
        );
    }
}
