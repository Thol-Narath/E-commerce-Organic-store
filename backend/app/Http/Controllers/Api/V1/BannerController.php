<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    use ApiResponse, Paginates;

    public function index(Request $request): JsonResponse
    {
        $paginator = Banner::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('title')
            ->paginate($this->perPage($request, 10))
            ->withQueryString();

        return $this->success([
            'items' => BannerResource::collection($paginator->items()),
            'pagination' => $this->pagination($paginator),
        ], 'Banners retrieved successfully.');
    }
}
