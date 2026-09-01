<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    use ApiResponse;

    /**
     * GET /api/v1/settings/public — public store information used by the
     * customer frontend (display only). The shipping flat rate mirrors the
     * value the OrderService actually charges from config/store.php; the
     * customer may never pick prices or totals, those are always computed
     * server-side at checkout.
     */
    public function publicSettings(): JsonResponse
    {
        return $this->success([
            'store' => [
                'name' => config('app.name', 'Organic Store'),
                'currency' => 'USD',
                'currency_symbol' => '$',
            ],
            'shipping' => [
                'flat_rate' => number_format((float) config('store.shipping_fee'), 2, '.', ''),
            ],
        ]);
    }
}