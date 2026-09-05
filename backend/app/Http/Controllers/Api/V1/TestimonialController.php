<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TestimonialResource;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TestimonialController extends Controller
{
    use ApiResponse, Paginates;

    public function index(Request $request): JsonResponse
    {
        $paginator = Testimonial::query()
            ->active()
            ->orderBy('sort_order')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return $this->success([
            'items' => TestimonialResource::collection($paginator->items()),
            'pagination' => $this->pagination($paginator),
        ], 'Testimonials retrieved successfully.');
    }
}
