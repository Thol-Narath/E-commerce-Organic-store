<?php

namespace App\Jobs;

use App\Exceptions\PaymentGatewayException;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

/**
 * Expire stale pending payments and (when enabled and configured) reconcile a
 * small batch of pending attempts against the PayWay gateway.
 *
 * Runs on the default (sync) queue in development and should be scheduled
 * every few minutes in production (schedule:work / cron).
 */
class CheckPendingPaymentsJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(PaymentService $paymentService): void
    {
        // 1. Expire pending attempts whose lifetime has elapsed (offline-safe:
        //    works even when the gateway is unreachable).
        $this->expireOverduePayments($paymentService);

        // 2. Optionally reconcile still-pending attempts that DO have a gateway
        //    reference, so a payment the gateway already settled gets marked
        //    paid even if the webhook never arrived.
        if (! config('payway.verify_transaction', false)
            || ! config('payway.merchant_id')
            || ! config('payway.api_key')) {
            return;
        }

        $this->reconcilePendingPayments($paymentService);
    }

    private function expireOverduePayments(PaymentService $paymentService): void
    {
        Payment::where('payment_status', PaymentStatus::Pending->value)
            ->where(function ($query) {
                $query->where('expires_at', '<', now())
                    ->orWhere(function ($query) {
                        $query->whereNull('expires_at')->where('created_at', '<', now()->subHours(24));
                    });
            })
            ->limit(50)
            ->get()
            ->each(fn (Payment $payment) => $paymentService->markExpired($payment));
    }

    private function reconcilePendingPayments(PaymentService $paymentService): void
    {
        Payment::where('payment_status', PaymentStatus::Pending->value)
            ->whereNotNull('gateway_transaction_id')
            ->where('created_at', '>=', now()->subHours(24))
            ->orderBy('created_at')
            ->limit(10)
            ->get()
            ->each(function (Payment $payment) use ($paymentService) {
                try {
                    $paymentService->refresh($payment);
                } catch (PaymentGatewayException $e) {
                    Log::warning('PayWay reconciliation skipped', [
                        'payment_id' => $payment->id,
                        'gateway_code' => $e->gatewayCode(),
                    ]);
                }
            });
    }
}