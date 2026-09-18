<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ShippingMethodResource;
use App\Models\ShippingMethod;
use Illuminate\Http\JsonResponse;

class ShippingMethodController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/shipping-methods — the active shipping methods offered at
     * checkout, cheapest-first by admin sort order. Prices are informational
     * for the UI only; the fee is always recomputed server-side when the order
     * is placed.
     *
     * Not cached: an admin enabling/disabling a method must take effect
     * immediately without a stale-window delay.
     */
    public function index(): JsonResponse
    {
        $methods = ShippingMethodResource::collection(
            ShippingMethod::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
        );

        return $this->success([
            'methods' => $methods,
        ], 'Shipping methods retrieved successfully.');
    }
}