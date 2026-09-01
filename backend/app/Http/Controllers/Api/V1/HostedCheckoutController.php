<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Response;

class HostedCheckoutController extends Controller
{
    /**
     * GET /api/v1/payments/payway/checkout/{payment} — serve the PayWay hosted
     * card checkout page inside an iframe.
     *
     * The route is protected by a temporary signed URL (Laravel `signed`
     * middleware), so no authentication token is required and the embedded
     * gateway form can never be tampered with by an unknown client.
     */
    public function show(Payment $payment): Response
    {
        if ($payment->payment_method !== 'card'
            || $payment->payment_status !== 'pending'
            || empty($payment->gateway_response)) {
            abort(404);
        }

        return response(
            $payment->gateway_response,
            200,
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }
}