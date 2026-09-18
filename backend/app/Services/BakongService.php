<?php

namespace App\Services;

use App\Exceptions\PaymentGatewayException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin client for the Bakong Open API (National Bank of Cambodia).
 *
 * Implements:
 *   - POST /v1/generate_deeplink_by_qr  (generate a deeplink from a KHQR string)
 *   - POST /v1/check_transaction_by_md5  (verify payment status)
 *   - POST /v1/renew_token               (auto-renew access token)
 *
 * KHQR string generation is handled by BakongQRGenerator (local, no API call).
 * The access token is stored in cache and auto-renewed when expired.
 *
 * @see https://api-bakong.nbc.gov.kh/document
 */
class BakongService
{
    private const TOKEN_CACHE_KEY = 'bakong_access_token';

    private const TOKEN_EXPIRY_CACHE_KEY = 'bakong_token_expires_at';

    public function __construct(
        private readonly BakongQRGenerator $qrGenerator,
    ) {}

    public function isConfigured(): bool
    {
        return $this->accessToken() !== '' && $this->accountId() !== '';
    }

    public function baseUrl(): string
    {
        return rtrim((string) config('bakong.base_url'), '/');
    }

    public function accessToken(): string
    {
        return (string) config('bakong.access_token');
    }

    public function accountId(): string
    {
        return (string) config('bakong.account_id');
    }

    public function merchantName(): string
    {
        return (string) config('bakong.merchant_name', 'Organic Store');
    }

    public function merchantCity(): string
    {
        return (string) config('bakong.merchant_city', 'Phnom Penh');
    }

    public function currency(): string
    {
        return strtoupper((string) config('bakong.currency', 'USD'));
    }

    /**
     * Generate a KHQR string and its MD5 hash for a payment.
     *
     * @return array{qr_string: string, md5: string, deeplink: ?string}
     */
    public function generatePaymentQR(float $amount, string $orderNumber): array
    {
        $this->assertConfigured();

        $lifetimeMinutes = max(3, (int) config('bakong.lifetime', 15));
        $createdAt = now();
        $expiresAt = $createdAt->copy()->addMinutes($lifetimeMinutes);

        $qrString = $this->qrGenerator->generate([
            'account_id' => $this->accountId(),
            'merchant_name' => $this->merchantName(),
            'merchant_city' => $this->merchantCity(),
            'amount' => $amount,
            'currency' => $this->currency(),
            'bill_number' => $orderNumber,
            'store_label' => 'Organic Store',
            'terminal_label' => 'Web',
            'purpose_of_transaction' => 'Payment',
            'created_at' => $createdAt,
            'expires_at' => $expiresAt,
        ]);

        $errors = $this->qrGenerator->validate($qrString, [
            'amount' => $amount,
            'currency' => $this->currency(),
            'account_id' => $this->accountId(),
        ]);

        if ($errors !== []) {
            Log::error('Bakong KHQR validation failed before display', [
                'order' => $orderNumber,
                'errors' => $errors,
                'qr_length' => strlen($qrString),
                // The QR string is not a secret: it is shown to the customer
                // in the UI and is included here only for debugging purposes.
                'qr_string' => $qrString,
            ]);

            throw new PaymentGatewayException(
                'Generated KHQR payload is invalid: '.implode('; ', $errors),
                'INVALID_KHQR'
            );
        }

        $md5 = $this->qrGenerator->generateMd5($qrString);

        Log::info('Bakong KHQR generated', [
            'order' => $orderNumber,
            'amount' => $amount,
            'currency' => $this->currency(),
            'md5' => $md5,
            'qr_length' => strlen($qrString),
            'expires_at' => $expiresAt->toIso8601String(),
        ]);

        $deeplink = null;

        try {
            $deeplink = $this->generateDeeplink($qrString);
        } catch (PaymentGatewayException $e) {
            Log::warning('Bakong deeplink generation failed', [
                'order' => $orderNumber,
                'error' => $e->getMessage(),
            ]);
        }

        return [
            'qr_string' => $qrString,
            'md5' => $md5,
            'deeplink' => $deeplink,
        ];
    }

    /**
     * Generate a Bakong deeplink from a KHQR string.
     */
    public function generateDeeplink(string $qrString): ?string
    {
        $this->assertConfigured();

        $payload = ['qr' => $qrString];

        $sourceInfo = $this->sourceInfo();

        if ($sourceInfo !== null) {
            $payload['sourceInfo'] = $sourceInfo;
        }

        $response = $this->apiRequest('POST', '/generate_deeplink_by_qr', $payload);

        if (! is_array($response) || isset($response['errorCode'])) {
            return null;
        }

        $data = $response['data'] ?? null;

        if (! is_array($data)) {
            return null;
        }

        // Bakong returns the deeplink as `fullLink` (plus a shorter `shortLink`);
        // older/other responses may use `deeplink`. Accept any of them.
        return $data['fullLink'] ?? $data['shortLink'] ?? $data['deeplink'] ?? null;
    }

    /**
     * Optional app branding sent with a deeplink request.
     *
     * Bakong's deeplink provider fetches these URLs, so local/non-HTTPS values
     * (the dev `http://localhost:8000` APP_URL) make the request fail with
     * errorCode 4 and no deeplink. Omit `sourceInfo` entirely in that case.
     *
     * @return array{appIconUrl: string, appName: string, appDeepLinkCallback: string}|null
     */
    private function sourceInfo(): ?array
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        if (! str_starts_with($appUrl, 'https://')) {
            return null;
        }

        return [
            'appIconUrl' => $appUrl.'/favicon.ico',
            'appName' => (string) config('app.name', 'Organic Store'),
            'appDeepLinkCallback' => $appUrl.'/payment/success',
        ];
    }

    /**
     * Check the status of a transaction by its MD5 hash.
     *
     * @return array{paid: bool, data: ?array, raw: array}
     */
    public function checkTransaction(string $md5): array
    {
        $this->assertConfigured();

        $response = $this->apiRequest('POST', '/check_transaction_by_md5', [
            'md5' => $md5,
        ]);

        if (! is_array($response)) {
            throw new PaymentGatewayException('Bakong returned an unexpected response.');
        }

        $responseCode = $response['responseCode'] ?? null;
        $data = $response['data'] ?? null;

        // responseCode 0 = transaction found (paid)
        $paid = $responseCode === 0 && is_array($data);

        return [
            'paid' => $paid,
            'data' => $paid ? $data : null,
            'raw' => $response,
        ];
    }

    /**
     * Standalone check for the legacy public /bakong/check-payment endpoint.
     *
     * @return array{http_status: int, success: bool, data: ?array}
     */
    public function checkPaymentByMd5(string $md5): array
    {
        try {
            $result = $this->checkTransaction($md5);
        } catch (PaymentGatewayException) {
            return [
                'http_status' => 502,
                'success' => false,
                'data' => null,
            ];
        }

        return [
            'http_status' => 200,
            'success' => $result['paid'],
            'data' => $result['paid'] ? $result['data'] : $result['raw'],
        ];
    }

    /**
     * Renew the Bakong API token.
     *
     * The Renew Token API is called with the email used to register the
     * developer account (the access token is a 90-day JWT, not an email).
     * Returns a fresh token or null when renewal is not possible.
     */
    public function renewToken(): ?string
    {
        $email = (string) config('bakong.email', '');

        if ($email === '') {
            Log::warning('Bakong token renewal skipped: BAKONG_EMAIL not configured.');

            return null;
        }

        try {
            $response = Http::timeout((int) config('bakong.timeout', 30))
                ->post($this->baseUrl().'/renew_token', [
                    'email' => $email,
                ]);
        } catch (\Exception $e) {
            Log::error('Bakong token renewal failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        if ($response->failed()) {
            Log::error('Bakong token renewal failed', [
                'status' => $response->status(),
            ]);

            return null;
        }

        $body = $response->json();

        if (is_array($body) && isset($body['data']['token'])) {
            $token = $body['data']['token'];
            Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addDays(89));

            return $token;
        }

        return null;
    }

    /**
     * Make an authenticated API request to Bakong.
     *
     * Handles token caching, 401 auto-retry with token renewal, and error
     * normalization.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws PaymentGatewayException
     */
    private function apiRequest(string $method, string $path, array $payload = []): array
    {
        $token = $this->getValidToken();

        try {
            $response = Http::timeout((int) config('bakong.timeout', 30))
                ->withHeaders([
                    'Authorization' => 'Bearer '.$token,
                    'Content-Type' => 'application/json',
                ])
                ->$method($this->baseUrl().$path, $payload);
        } catch (\Exception $e) {
            throw new PaymentGatewayException('Bakong API connection failed: '.$e->getMessage());
        }

        // Auto-retry once on 401 (token expired)
        if ($response->status() === 401) {
            $newToken = $this->renewToken();

            if ($newToken !== null) {
                try {
                    $response = Http::timeout((int) config('bakong.timeout', 30))
                        ->withHeaders([
                            'Authorization' => 'Bearer '.$newToken,
                            'Content-Type' => 'application/json',
                        ])
                        ->$method($this->baseUrl().$path, $payload);
                } catch (\Exception $e) {
                    throw new PaymentGatewayException('Bakong API connection failed: '.$e->getMessage());
                }
            }
        }

        if ($response->failed()) {
            Log::error('Bakong API request failed', [
                'endpoint' => $path,
                'http_status' => $response->status(),
                'response' => mb_substr((string) $response->body(), 0, 2000),
            ]);

            throw new PaymentGatewayException(
                'Bakong API request failed with HTTP '.$response->status()
            );
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new PaymentGatewayException('Bakong returned a non-JSON response.');
        }

        return $body;
    }

    /**
     * Retrieve the access token from cache or use the configured one directly.
     */
    private function getValidToken(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = $this->accessToken();

        if ($token !== '') {
            Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addDays(89));

            return $token;
        }

        throw new PaymentGatewayException('Bakong access token is not configured.', 'CONFIG');
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new PaymentGatewayException('Bakong gateway is not configured.', 'CONFIG');
        }
    }
}
