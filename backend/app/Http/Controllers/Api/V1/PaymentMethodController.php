<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CacheService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentMethodController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly CacheService $cacheService
    ) {}

    /**
     * GET /api/v1/payment-methods — the enabled payment methods the customer
     * store may offer. Read from server config so toggles never reach React.
     */
    public function index(): JsonResponse
    {
        $data = $this->cacheService->rememberStatic(
            'payment-methods',
            CacheService::TTL_LONG,
            fn () => [
                'methods' => $this->paymentService->methods(),
                'currencies' => $this->paymentService->currencies(),
            ]
        );

        return $this->success($data, 'Payment methods retrieved successfully.');
    }
}
