<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Exceptions\PaymentGatewayException;
use Illuminate\Support\Facades\Http;

/**
 * Thin client for the ABA PayWay payment gateway.
 *
 * Implements the official API (developer.payway.com.kh):
 *   - POST /api/payment-gateway/v1/payments/purchase
 *   - POST /api/payment-gateway/v1/payments/check-transaction-2
 *
 * The merchant id and API key are read from the environment server-side and
 * NEVER reach the React client. Laravel is the only component allowed to talk
 * to PayWay.
 *
 * Hash rule (official): the `hash` field is a base64 encoded HMAC-SHA512 of
 * concatenated field values (in the documented order) keyed by the API key.
 * For check-transaction only req_time + merchant_id + tran_id are hashed.
 *
 * @see docs/payment-integration.md
 */
class PayWayService
{
    private const PURCHASE_PATH = 'api/payment-gateway/v1/payments/purchase';
    private const CHECK_PATH = 'api/payment-gateway/v1/payments/check-transaction-2';

    /**
     * Fields that must be included in the purchase hash, in this exact order.
     * `view_type` is deliberately NOT part of the hash.
     */
    private const HASHED_PURCHASE_FIELDS = [
        'req_time',
        'merchant_id',
        'tran_id',
        'amount',
        'items',
        'shipping',
        'firstname',
        'lastname',
        'email',
        'phone',
        'type',
        'payment_option',
        'return_url',
        'cancel_url',
        'continue_success_url',
        'return_deeplink',
        'currency',
        'custom_fields',
        'return_params',
        'payout',
        'lifetime',
        'additional_params',
        'google_pay_token',
        'skip_success_page',
    ];

    public function isConfigured(): bool
    {
        return $this->merchantId() !== '' && $this->apiKey() !== '';
    }

    public function merchantId(): string
    {
        return (string) config('payway.merchant_id');
    }

    public function apiKey(): string
    {
        return (string) config('payway.api_key');
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('payway.base_url'), '/').'/';
    }

    /**
     * Current UTC time formatted as YYYYMMDDHHMMSS (as required by PayWay).
     */
    public function reqTime(): string
    {
        return now()->utc()->format('YmdHis');
    }

    /**
     * base64(HMAC-SHA512(raw, api_key)).
     */
    public function hash(string $raw): string
    {
        return base64_encode(hash_hmac('sha512', $raw, $this->apiKey(), true));
    }

    /**
     * Create a payment transaction at PayWay.
     *
     * @param  array<string, mixed>  $data  keys: tran_id, amount, currency,
     *                                      payment_option, firstname, lastname,
     *                                      email, phone, items, shipping,
     *                                      return_url, cancel_url,
     *                                      continue_success_url, custom_fields,
     *                                      return_params
     * @return array<string, mixed>  gateway_transaction_id + optional
     *                               qr_string/deeplink/checkout_qr_url/html
     *
     * @throws PaymentGatewayException when not configured, the gateway errors,
     *                                 or the response is not understood
     */
    public function createPayment(array $data): array
    {
        $this->assertConfigured();

        $tranId = (string) $data['tran_id'];

        $fields = [
            'req_time' => $this->reqTime(),
            'merchant_id' => $this->merchantId(),
            'tran_id' => $tranId,
            'amount' => $this->decimal($data['amount']),
            'items' => $this->encodeJson(data_get($data, 'items', [])),
            'shipping' => $this->decimal(data_get($data, 'shipping', 0)),
            'firstname' => (string) data_get($data, 'firstname', ''),
            'lastname' => (string) data_get($data, 'lastname', ''),
            'email' => (string) data_get($data, 'email', ''),
            'phone' => (string) data_get($data, 'phone', ''),
            'type' => 'purchase',
            'payment_option' => (string) $data['payment_option'],
            'return_url' => (string) $data['return_url'],
            'cancel_url' => (string) data_get($data, 'cancel_url', $data['return_url']),
            'continue_success_url' => (string) data_get($data, 'continue_success_url', $data['return_url']),
            'return_deeplink' => '',
            'currency' => (string) $data['currency'],
            'custom_fields' => $this->encodeJson(data_get($data, 'custom_fields', [])),
            'return_params' => (string) data_get($data, 'return_params', ''),
            'payout' => '',
            'lifetime' => (string) (int) data_get($data, 'lifetime', config('payway.lifetime')),
            'additional_params' => '',
            'google_pay_token' => '',
            'skip_success_page' => '1',
        ];

        $fields['hash'] = $this->signPurchase($fields);

        // view_type is intentionally NOT part of the hash.
        $fields['view_type'] = (string) data_get($data, 'view_type', 'hosted_view');

        $response = Http::asMultipart()
            ->timeout((int) config('payway.timeout', 30))
            ->post($this->baseUrl().self::PURCHASE_PATH, $fields);

        if ($response->failed()) {
            throw new PaymentGatewayException(
                'PayWay purchase request failed with HTTP '.$response->status()
            );
        }

        // Card (`cards`) and hosted views respond with the checkout HTML page.
        if ($this->looksLikeHtml($response->body(), $response->header('Content-Type'))) {
            return [
                'gateway_transaction_id' => $tranId,
                'html' => $response->body(),
            ];
        }

        $payload = $response->json();

        if (! is_array($payload) || ! isset($payload['status']['code'])) {
            throw new PaymentGatewayException('PayWay returned an unexpected purchase response.');
        }

        $code = (string) $payload['status']['code'];

        if ($code !== '00') {
            $message = (string) ($payload['status']['message'] ?? 'PayWay refused the payment transaction.');
            throw new PaymentGatewayException($message, $code);
        }

        return [
            'gateway_transaction_id' => $tranId,
            'qr_string' => isset($payload['qr_string']) ? (string) $payload['qr_string'] : null,
            'deeplink' => isset($payload['abapay_deeplink']) ? (string) $payload['abapay_deeplink'] : null,
            'checkout_qr_url' => isset($payload['checkout_qr_url']) ? (string) $payload['checkout_qr_url'] : null,
        ];
    }

    /**
     * Verify a transaction's current status via check-transaction-2.
     *
     * @throws PaymentGatewayException when not configured, the gateway errors,
     *                                 or the transaction is unknown
     */
    public function checkTransaction(string $tranId): array
    {
        $this->assertConfigured();

        $reqTime = $this->reqTime();

        $payload = [
            'req_time' => $reqTime,
            'merchant_id' => $this->merchantId(),
            'tran_id' => $tranId,
        ];

        $payload['hash'] = $this->hash($reqTime.$this->merchantId().$tranId);

        $response = Http::asJson()
            ->timeout((int) config('payway.timeout', 30))
            ->post($this->baseUrl().self::CHECK_PATH, $payload);

        if ($response->failed()) {
            throw new PaymentGatewayException(
                'PayWay check-transaction request failed with HTTP '.$response->status()
            );
        }

        $body = $response->json();

        if (! is_array($body) || ! isset($body['status']['code'])) {
            throw new PaymentGatewayException('PayWay returned an unexpected check response.');
        }

        $code = (string) $body['status']['code'];

        if ($code !== '00') {
            $message = (string) ($body['status']['message'] ?? 'PayWay could not verify the transaction.');
            throw new PaymentGatewayException($message, $code);
        }

        $data = is_array($body['data'] ?? null) ? $body['data'] : [];

        return [
            'payment_status_code' => $data['payment_status_code'] ?? null,
            'payment_status' => ($data['payment_status'] ?? null) ?: null,
            'total_amount' => isset($data['total_amount']) ? (float) $data['total_amount'] : null,
            'payment_amount' => isset($data['payment_amount']) ? (float) $data['payment_amount'] : null,
            'payment_currency' => ($data['payment_currency'] ?? '') ?: null,
            'apv' => ($data['apv'] ?? '') ?: null,
            'transaction_date' => ($data['transaction_date'] ?? null) ?: null,
        ];
    }

    /**
     * Verify the HMAC signature on a PayWay callback/webhook payload.
     *
     * Rule (community + official guidance): sort the payload keys ascending,
     * concatenate the values (nested arrays are JSON-encoded), HMAC-SHA512 the
     * result with the API key and base64-encode it. Comparison uses hash_equals
     * to avoid timing attacks.
     */
    public function verifyCallbackSignature(array $payload, ?string $signature): bool
    {
        if (! $this->isConfigured() || $signature === null || $signature === '') {
            return false;
        }

        ksort($payload);

        $values = [];

        foreach ($payload as $value) {
            $values[] = is_array($value) ? json_encode($value) : (string) $value;
        }

        return hash_equals($this->hash(implode('', $values)), $signature);
    }

    /**
     * Convert a gateway payment_status_code to a store PaymentStatus.
     * 0 = APPROVED/PRE-AUTH, 2 = PENDING, 3 = DECLINED, 4 = REFUNDED,
     * 7 = CANCELLED; anything else is treated as failed.
     */
    public function mapGatewayStatus(mixed $statusCode): PaymentStatus
    {
        $code = ctype_digit((string) $statusCode) ? (int) $statusCode : null;

        return match ($code) {
            0 => PaymentStatus::Paid,
            2 => PaymentStatus::Pending,
            4 => PaymentStatus::Refunded,
            7 => PaymentStatus::Cancelled,
            default => PaymentStatus::Failed,
        };
    }

    /**
     * Make an absolute URL for a gateway return/callback target, allowing the
     * deployer to set a fully-qualified URL in the environment.
     */
    public function absoluteUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
    }

    private function signPurchase(array $fields): string
    {
        $raw = '';

        foreach (self::HASHED_PURCHASE_FIELDS as $field) {
            $raw .= (string) ($fields[$field] ?? '');
        }

        return $this->hash($raw);
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new PaymentGatewayException('PayWay gateway is not configured.', 'CONFIG');
        }
    }

    /**
     * Two-decimal, dot-separated amount as required by PayWay.
     */
    private function decimal(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 2, '.', '');
    }

    /**
     * base64-encoded JSON payload for `items` and `custom_fields`.
     */
    private function encodeJson(mixed $value): string
    {
        return base64_encode((string) json_encode($value ?: []));
    }

    private function looksLikeHtml(string $body, ?string $contentType): bool
    {
        if ($contentType !== null && str_contains(strtolower($contentType), 'text/html')) {
            return true;
        }

        return (bool) preg_match('/^\s*<!DOCTYPE/i', ltrim($body));
    }
}