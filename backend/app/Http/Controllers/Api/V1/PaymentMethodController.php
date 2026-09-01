<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentMethodController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly PaymentService $paymentService) {}

    /**
     * GET /api/v1/payment-methods — the enabled payment methods the customer
     * store may offer. Read from server config so toggles never reach React.
     */
    public function index(): JsonResponse
    {
        return $this->success(
            ['methods' => $this->paymentService->methods()],
            'Payment methods retrieved successfully.'
        );
    }
}