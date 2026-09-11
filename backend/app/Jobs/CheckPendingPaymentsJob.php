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

        // 2. Reconcile PayWay pending attempts (when configured).
        if (config('payway.verify_transaction', false)
            && config('payway.merchant_id')
            && config('payway.api_key')) {
            $this->reconcilePendingPayments($paymentService, 'payway');
        }

        // 3. Reconcile Bakong pending attempts (when configured).
        if (config('bakong.verify_transaction', false)
            && config('bakong.access_token')
            && config('bakong.account_id')) {
            $this->reconcilePendingPayments($paymentService, 'bakong');
        }
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

    private function reconcilePendingPayments(PaymentService $paymentService, string $gateway = 'payway'): void
    {
        Payment::where('payment_status', PaymentStatus::Pending->value)
            ->where('gateway', $gateway)
            ->whereNotNull('gateway_transaction_id')
            ->where('created_at', '>=', now()->subHours(24))
            ->orderBy('created_at')
            ->limit(10)
            ->get()
            ->each(function (Payment $payment) use ($paymentService) {
                try {
                    $paymentService->refresh($payment);
                } catch (PaymentGatewayException $e) {
                    Log::warning('Gateway reconciliation skipped', [
                        'payment_id' => $payment->id,
                        'gateway' => $payment->gateway,
                        'gateway_code' => $e->gatewayCode(),
                    ]);
                }
            });
    }
}