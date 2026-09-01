<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PaymentException;
use App\Http\Controllers\Controller;
use App\Services\PayWayService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayWayWebhookController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PayWayService $payWay,
        private readonly PaymentService $paymentService,
    ) {}

    /**
     * POST /api/v1/payments/payway/webhook — PayWay's callback.
     *
     * Public by design (PayWay cannot carry a Bearer token) and therefore
     * protected by the HMAC-SHA512 signature in the X-PayWay-Hmac-SHA512
     * header. Invalid signatures are rejected BEFORE any state changes.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->json()->all();

        if (! is_array($payload)) {
            return $this->error('Invalid payload.', null, 400);
        }

        $signature = $request->header('X-PayWay-Hmac-SHA512');

        if (! $this->payWay->verifyCallbackSignature($payload, $signature)) {
            return $this->error('Invalid callback signature.', null, 400);
        }

        try {
            $this->paymentService->verifyCallbackAndApply($payload);
        } catch (PaymentException $e) {
            return $this->error($e->getMessage(), null, $e->responseStatus());
        }

        return $this->success(null, 'Webhook processed successfully.');
    }
}