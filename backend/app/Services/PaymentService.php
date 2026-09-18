<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Exceptions\PaymentGatewayException;
use App\Http\Resources\PaymentResource;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Payment lifecycle for online payments (Phase 8).
 *
 * Supports two gateways:
 *   - ABA PayWay: aba_pay, khqr, card
 *   - Bakong Open API: bakong (direct KHQR)
 *
 * Rules enforced here (business-rules.md §Payment):
 *   - The payment amount/currency ALWAYS come from the stored order total —
 *     never from the client;
 *   - Only enabled methods listed by GET /payment-methods can be used;
 *   - An order can have many attempts but only ONE success settles it;
 *   - A payment is considered paid ONLY after the gateway confirms it: the
 *     webhook alone never settles a payment — a valid-HMAC webhook still has
 *     to be re-verified through check-transaction-2 (same path as polling and
 *     the scheduler), and the amount/currency must match the stored total;
 *   - A confirmed payment flips the order to payment_status=paid,
 *     status=confirmed;
 *   - Failed/expired/cancelled attempts never alter the order status.
 */
class PaymentService
{
    /**
     * Minimum seconds between backend-initiated Bakong verification calls for
     * the same pending attempt (default 300s — the Bakong Open API only allows
     * 100 requests/day/account, so aggressive polling exhausts the quota).
     */
    private const BAKONG_VERIFY_THROTTLE_SECONDS = 300;

    public function __construct(
        private readonly AbaPaywayService $payWay,
        private readonly BakongService $bakong,
    ) {}

    /**
     * The enabled, supported payment methods surfaced to the UI.
     */
    public function methods(): array
    {
        return collect(PaymentMethod::cases())
            ->filter(function (PaymentMethod $method) {
                if ($method->isPayway()) {
                    return $this->isMethodEnabled($method->value);
                }

                if ($method->isBakong()) {
                    return (bool) config('bakong.enabled', false) && $this->bakong->isConfigured();
                }

                return false;
            })
            ->map(fn (PaymentMethod $method) => [
                'method' => $method->value,
                'label' => $method->label(),
                'payway_option' => $method->isPayway() ? $method->paywayOption() : null,
            ])
            ->values()
            ->all();
    }

    public function isMethodEnabled(string $method): bool
    {
        $enum = PaymentMethod::tryFrom($method);

        if ($enum === null) {
            return false;
        }

        if ($enum->isPayway()) {
            // A PayWay method is only usable when the gateway has real
            // credentials — otherwise the attempt would fail before generating
            // any QR and leave the customer looking at a QR-less "pending".
            return (bool) config('payway.methods.'.$method, false)
                && $this->payWay->isConfigured();
        }

        if ($enum->isBakong()) {
            return (bool) config('bakong.enabled', false) && $this->bakong->isConfigured();
        }

        return false;
    }

    /**
     * The most recent payment attempt, if any.
     */
    public function latestPayment(Order $order): ?Payment
    {
        return $order->payments()->orderByDesc('id')->with('order')->first();
    }

    /**
     * Create a payment attempt against a payment gateway.
     *
     * @throws PaymentException     for business-rule violations
     * @throws PaymentGatewayException when the gateway refuses/unavailable
     */
    public function createPayment(User $user, Order $order, string $paymentMethod): Payment
    {
        $enum = PaymentMethod::tryFrom($paymentMethod);

        if ($enum === null) {
            throw new PaymentException('The payment method is not supported.', 422);
        }

        if (! $this->isMethodEnabled($enum->value)) {
            throw new PaymentException('The selected payment method is not available.', 422);
        }

        if ($order->user_id !== $user->id) {
            throw new PaymentException('Order not found.', 404);
        }

        if ($order->payment_status === 'paid') {
            throw new PaymentException('This order has already been paid.', 409);
        }

        if (in_array($order->status, ['cancelled', 'refunded'], true)) {
            throw new PaymentException('This order can no longer be paid.', 409);
        }

        $amount = (float) $order->total;

        if ($amount <= 0) {
            throw new PaymentException('The order total must be greater than zero.', 422);
        }

        $currency = $enum->isBakong()
            ? strtoupper((string) config('bakong.currency', 'USD'))
            : strtoupper((string) config('payway.currency', 'USD'));

        $lifetime = $enum->isBakong()
            ? max(3, (int) config('bakong.lifetime', 15))
            : max(3, (int) config('payway.lifetime', 30));

        $gateway = $enum->isBakong() ? 'bakong' : 'payway';

        $payment = Payment::create([
            'payment_number' => 'PENDING',
            'order_id' => $order->id,
            'payment_method' => $enum->value,
            'gateway' => $gateway,
            'amount' => $amount,
            'currency' => $currency,
            'payment_status' => PaymentStatus::Pending->value,
            'expires_at' => now()->addMinutes($lifetime),
        ]);

        $payment->payment_number = 'PAY-'.$payment->created_at->format('Ymd').'-'
            .str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);
        $payment->save();

        try {
            if ($enum->isBakong()) {
                $result = $this->createBakongPayment($order, $payment);
            } else {
                $result = $this->payWay->createPayment($this->gatewayPayload($user, $order, $payment, $enum));
            }
        } catch (PaymentGatewayException $e) {
            // The gateway could not start the attempt. Record it as failed so a
            // QR-less "pending" attempt never becomes the latest attempt and
            // shadows a later working one, then let the caller surface the error.
            Log::error('Payment gateway call failed', [
                'payment_id' => $payment->id,
                'gateway' => $gateway,
                'method' => $enum->value,
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
            $this->markFailed($payment);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Unexpected payment creation error', [
                'payment_id' => $payment->id,
                'gateway' => $gateway,
                'method' => $enum->value,
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
            $this->markFailed($payment);
            throw new PaymentGatewayException('An unexpected error occurred while creating the payment.');
        }

        $payment->gateway_transaction_id = $result['gateway_transaction_id'];
        $payment->transaction_id = $result['gateway_transaction_id'];

        if (! empty($result['qr_string'])) {
            $payment->qr_string = $result['qr_string'];
        }

        if (! empty($result['deeplink'])) {
            $payment->deeplink = $result['deeplink'];
        }

        if (! empty($result['html'])) {
            $payment->gateway_response = $result['html'];
        }

        $payment->save();

        return $payment->load('order');
    }

    /**
     * The data the payment page needs without hitting the gateway:
     * order state + latest attempt (QR / deeplink / hosted checkout link).
     *
     * For Bakong attempts this also re-verifies the transaction server-side
     * (throttled) so React's polling can auto-detect a completed scan without
     * relying on the scheduler. PayWay attempts stay a cheap DB read — their
     * confirmation comes from the HMAC webhook + manual/scheduled refresh.
     */
    public function statusData(Order $order): array
    {
        $payment = $this->latestPayment($order);

        if ($payment?->shouldReconcileOnStatus()) {
            $this->verifyBakongIfDue($payment);

            // Re-read so the response reflects the verification result.
            $payment = $this->latestPayment($order);
        }

        return [
            'order_number' => $order->order_number,
            'order_status' => $order->status,
            'order_payment_status' => $order->payment_status,
            'payment' => $payment ? (new PaymentResource($payment))->resolve() : null,
        ];
    }

    /**
     * Re-verify a pending Bakong attempt against the Bakong Open API.
     *
     * The throttle lives inside refreshBakong() (not here) so that every
     * verification path — SPA status polling, the manual "Check payment
     * status" button, and the periodic scheduler job — honours the same
     * per-attempt cooldown. A transient gateway error is not fatal: the
     * attempt stays pending and the next run retries it.
     */
    private function verifyBakongIfDue(Payment $payment): void
    {
        try {
            $this->refresh($payment);
        } catch (PaymentGatewayException $e) {
            Log::warning('Bakong verification failed on status poll', [
                'payment_id' => $payment->id,
                'gateway_code' => $e->gatewayCode(),
            ]);
        }
    }

    /**
     * Authoritatively record a verified successful payment.
     *
     * Idempotent: an already-paid payment is returned untouched so duplicate
     * webhooks or repeated reconciliations are harmless.
     *
     * @throws PaymentException when the gateway-reported amount/currency do not
     *                          match the stored order total
     */
    public function confirmPayment(
        Payment $payment,
        ?float $amount = null,
        ?string $currency = null,
        ?string $apv = null,
    ): Payment {
        if ($payment->payment_status === PaymentStatus::Paid->value) {
            return $payment->load('order');
        }

        if ($amount !== null && abs($amount - (float) $payment->amount) > 0.005) {
            throw new PaymentException('The paid amount does not match the order total.', 422);
        }

        if ($currency !== null
            && strtoupper($currency) !== strtoupper((string) $payment->currency)) {
            throw new PaymentException('The paid currency does not match the order currency.', 422);
        }

        return DB::transaction(function () use ($payment, $apv) {
            $payment->update([
                'payment_status' => PaymentStatus::Paid->value,
                'paid_at' => now(),
                'gateway_reference' => $apv ?: $payment->gateway_reference,
            ]);

            $order = $payment->order;

            $order->update([
                'payment_status' => 'paid',
                'status' => 'confirmed',
            ]);

            // Only one successful attempt is allowed per order: retire siblings.
            Payment::where('order_id', $order->id)
                ->where('id', '!=', $payment->id)
                ->where('payment_status', PaymentStatus::Pending->value)
                ->update(['payment_status' => PaymentStatus::Cancelled->value]);

            return $payment->fresh()->load('order');
        });
    }

    public function markFailed(Payment $payment): Payment
    {
        $this->transition($payment, PaymentStatus::Failed);

        return $payment->refresh();
    }

    public function markExpired(Payment $payment): Payment
    {
        $this->transition($payment, PaymentStatus::Expired);

        return $payment->refresh();
    }

    public function markCancelled(Payment $payment): Payment
    {
        $this->transition($payment, PaymentStatus::Cancelled);

        return $payment->refresh();
    }

    /**
     * Reconcile a pending payment against its gateway.
     *
     * PayWay: check-transaction-2 endpoint.
     * Bakong: check_transaction_by_md5 endpoint.
     */
    public function refresh(Payment $payment): Payment
    {
        if ($payment->payment_status !== PaymentStatus::Pending->value) {
            return $payment;
        }

        if ($payment->gateway === 'bakong') {
            return $this->refreshBakong($payment);
        }

        // PayWay gateway
        if (! $payment->gateway_transaction_id) {
            return $payment;
        }

        try {
            $gateway = $this->payWay->checkTransaction($payment->gateway_transaction_id);
        } catch (PaymentGatewayException $e) {
            if ($e->gatewayCode() === '6') {
                return $this->markFailed($payment);
            }

            throw $e;
        }

        $status = $this->payWay->mapGatewayStatus($gateway['payment_status_code']);

        return match ($status) {
            PaymentStatus::Paid => $this->confirmPayment(
                $payment,
                $gateway['total_amount'] ?? $gateway['payment_amount'],
                $gateway['payment_currency'],
                $gateway['apv'],
            ),
            PaymentStatus::Pending => $payment,
            PaymentStatus::Refunded => $this->transition($payment, PaymentStatus::Refunded),
            PaymentStatus::Cancelled => $this->markCancelled($payment),
            default => $this->markFailed($payment),
        };
    }

    /**
     * Check a Bakong payment via the check_transaction_by_md5 endpoint.
     *
     * Throttled per attempt so the same pending payment is not re-checked more
     * often than config('bakong.verify_throttle') seconds — regardless of who
     * asked (poll, button, scheduler), because every Bakong API call counts
     * against the account's 100-requests/day limit.
     */
    private function refreshBakong(Payment $payment): Payment
    {
        if (! $payment->gateway_transaction_id) {
            return $payment;
        }

        $throttleKey = 'bakong.verify:'.$payment->id;

        if (Cache::has($throttleKey)) {
            return $payment;
        }

        Cache::put(
            $throttleKey,
            true,
            max(15, (int) config('bakong.verify_throttle', self::BAKONG_VERIFY_THROTTLE_SECONDS))
        );

        try {
            $result = $this->bakong->checkTransaction($payment->gateway_transaction_id);
        } catch (PaymentGatewayException $e) {
            // Bakong unreachable/unexpected — keep the attempt pending for a
            // later run; never mark it failed on a transient network error.
            Log::warning('Bakong transaction check failed', [
                'payment_id' => $payment->id,
                'gateway_code' => $e->gatewayCode(),
            ]);

            return $payment;
        }

        if ($result['paid']) {
            $data = $result['data'];

            return $this->confirmPayment(
                $payment,
                isset($data['amount']) ? (float) $data['amount'] : null,
                $data['currency'] ?? null,
                $data['hash'] ?? null,
            );
        }

        return $payment;
    }

    /**
     * Generate a KHQR payment via the Bakong Open API.
     */
    private function createBakongPayment(Order $order, Payment $payment): array
    {
        $qrData = $this->bakong->generatePaymentQR(
            (float) $payment->amount,
            $order->order_number,
        );

        return [
            'gateway_transaction_id' => $qrData['md5'],
            'qr_string' => $qrData['qr_string'],
            'deeplink' => $qrData['deeplink'],
        ];
    }

    /**
     * Apply an authenticated PayWay callback/webhook payload.
     *
     * The HMAC signature must already have been verified by the caller.
     *
     * The callback status never settles a payment on its own. A `status=0`
     * webhook is still re-checked against check-transaction-2 (which reports
     * the APPROVED status, amount, currency and apv) before the payment and
     * order may be marked paid. A gateway "transaction not found" is treated
     * as a failed attempt; other gateway failures bubble up as failures the
     * caller can retry.
     *
     * @throws PaymentException when the referenced transaction is unknown
     */
    public function verifyCallbackAndApply(array $payload): Payment
    {
        $tranId = $payload['tran_id'] ?? null;

        if (! $tranId) {
            throw new PaymentException('The transaction reference is missing.', 422);
        }

        $payment = Payment::where('gateway_transaction_id', $tranId)->first();

        if (! $payment) {
            throw new PaymentException('Transaction not found.', 404);
        }

        if ($payment->payment_status === PaymentStatus::Paid->value) {
            return $payment->load('order');
        }

        $callbackStatus = (string) ($payload['status'] ?? '');

        if ($callbackStatus !== '0') {
            return $this->markFailed($payment);
        }

        if ($payment->gateway !== 'payway') {
            throw new PaymentException('Transaction not found.', 404);
        }

        // Independent verification: check-transaction-2 must report APPROVED
        // with a matching amount/currency before the payment is settled.
        return $this->refresh($payment);
    }

    /**
     * Build the PayWay purchase payload for a payment attempt.
     *
     * Buyer details come from the account + the order's address snapshot;
     * `items` and `custom_fields` are base64 JSON description/remark only —
     * the gateway never calculates totals from them.
     */
    private function gatewayPayload(User $user, Order $order, Payment $payment, PaymentMethod $method): array
    {
        $snapshot = $order->shipping_address_snapshot
            ? json_decode($order->shipping_address_snapshot, true)
            : [];

        $name = trim((string) $user->name);
        $nameParts = preg_split('/\s+/', $name, 2);

        return [
            'tran_id' => 'PY'.$payment->id.strtoupper(Str::random(7)),
            'amount' => (float) $payment->amount,
            'currency' => (string) $payment->currency,
            'payment_option' => $method->paywayOption(),
            'firstname' => $nameParts[0] ?? '',
            'lastname' => $nameParts[1] ?? '',
            'email' => (string) $user->email,
            'phone' => (string) ($user->phone ?: ($snapshot['recipient_phone'] ?? '')),
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'price' => (float) $item->unit_price,
            ])->values()->all(),
            'shipping' => (float) $order->shipping_fee,
            'return_url' => $this->payWay->absoluteUrl((string) config('payway.callback_url')),
            'cancel_url' => $this->payWay->absoluteUrl('/payment/'.$order->order_number.'?status=cancelled'),
            'continue_success_url' => $this->payWay->absoluteUrl((string) config('payway.return_url')),
            'custom_fields' => [
                'order_number' => $order->order_number,
                'payment_number' => $payment->payment_number,
            ],
            'return_params' => $order->order_number.'|'.$payment->payment_number,
            'lifetime' => (int) config('payway.lifetime', 30),
        ];
    }

    private function transition(Payment $payment, PaymentStatus $status): Payment
    {
        if ($payment->payment_status !== $status->value
            && ($payment->payment_status === PaymentStatus::Pending->value || $status === PaymentStatus::Refunded)) {
            $payment->update(['payment_status' => $status->value]);
        }

        return $payment->refresh();
    }
}