<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PaymentException;
use App\Exceptions\PaymentGatewayException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\CreatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly OrderService $orderService,
    ) {}

    /**
     * POST /api/v1/orders/{orderNumber}/payments — start a payment attempt
     * against ABA PayWay for the customer's pending order.
     */
    public function store(CreatePaymentRequest $request, string $orderNumber): JsonResponse
    {
        $order = $this->orderService->findByNumber($request->user(), $orderNumber);

        if (! $order) {
            return $this->error('Order not found.', null, 404);
        }

        try {
            $payment = $this->paymentService->createPayment(
                $request->user(),
                $order,
                $request->input('payment_method'),
            );
        } catch (PaymentException $e) {
            return $this->error($e->getMessage(), null, $e->responseStatus());
        } catch (PaymentGatewayException) {
            return $this->error('The payment gateway is unavailable. Please try again later.', null, 502);
        }

        return $this->success(new PaymentResource($payment), 'Payment initiated successfully.', 201);
    }

    /**
     * GET /api/v1/orders/{orderNumber}/payment-status — the current order and
     * latest attempt status, used by the SPA to poll without touching PayWay.
     */
    public function status(Request $request, string $orderNumber): JsonResponse
    {
        $order = $this->orderService->findByNumber($request->user(), $orderNumber);

        if (! $order) {
            return $this->error('Order not found.', null, 404);
        }

        return $this->success(
            $this->paymentService->statusData($order),
            'Payment status retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/orders/{orderNumber}/payments/{payment}/refresh —
     * re-check a pending attempt against the gateway (check-transaction-2).
     */
    public function refresh(Request $request, string $orderNumber, Payment $payment): JsonResponse
    {
        $order = $this->orderService->findByNumber($request->user(), $orderNumber);

        if (! $order) {
            return $this->error('Order not found.', null, 404);
        }

        if ($payment->order_id !== $order->id) {
            return $this->error('Payment not found for this order.', null, 404);
        }

        try {
            $payment = $this->paymentService->refresh($payment);
        } catch (PaymentGatewayException) {
            return $this->error('The payment gateway is unavailable. Please try again later.', null, 502);
        }

        return $this->success(new PaymentResource($payment), 'Payment refreshed successfully.');
    }
}