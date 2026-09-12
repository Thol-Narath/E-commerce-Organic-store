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
        ]);

        $md5 = $this->qrGenerator->generateMd5($qrString);

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

        $response = $this->apiRequest('POST', '/generate_deeplink_by_qr', [
            'qr' => $qrString,
            'sourceInfo' => [
                'appIconUrl' => (string) config('app.url').'/favicon.ico',
                'appName' => (string) config('app.name', 'Organic Store'),
                'appDeepLinkCallback' => (string) config('app.url').'/payment/success',
            ],
        ]);

        if (! is_array($response) || isset($response['errorCode'])) {
            return null;
        }

        $data = $response['data'] ?? null;

        return is_array($data) ? ($data['deeplink'] ?? null) : null;
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
     * Renew the Bakong API token.
     *
     * The official token lives 90 days. We cache it and auto-renew on 401.
     */
    public function renewToken(): ?string
    {
        $email = (string) config('bakong.access_token');

        // If the configured token is already a JWT, use the renew endpoint
        $response = Http::timeout((int) config('bakong.timeout', 30))
            ->post($this->baseUrl().'/renew_token', [
                'email' => $email,
            ]);

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

        $response = Http::timeout((int) config('bakong.timeout', 30))
            ->withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Content-Type' => 'application/json',
            ])
            ->$method($this->baseUrl().$path, $payload);

        // Auto-retry once on 401 (token expired)
        if ($response->status() === 401) {
            $newToken = $this->renewToken();

            if ($newToken !== null) {
                $response = Http::timeout((int) config('bakong.timeout', 30))
                    ->withHeaders([
                        'Authorization' => 'Bearer '.$newToken,
                        'Content-Type' => 'application/json',
                    ])
                    ->$method($this->baseUrl().$path, $payload);
            }
        }

        if ($response->failed()) {
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
