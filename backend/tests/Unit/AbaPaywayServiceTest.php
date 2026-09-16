<?php

namespace Tests\Unit;

use App\Enums\PaymentStatus;
use App\Services\AbaPaywayService;
use Tests\TestCase;

/**
 * Unit tests for the ABA PayWay gateway client.
 *
 * These verify the exact HMAC-SHA512 hashing, URL configuration and gateway
 * status mapping that the official PayWay documentation requires — WITHOUT
 * making any real network calls.
 */
class AbaPaywayServiceTest extends TestCase
{
    private const MERCHANT_ID = 'test_merchant';
    private const API_KEY = 'test_api_key';

    private function makeService(): AbaPaywayService
    {
        config([
            'payway.merchant_id' => self::MERCHANT_ID,
            'payway.api_key' => self::API_KEY,
            'payway.purchase_url' => 'https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/purchase',
            'payway.check_url' => 'https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/check-transaction-2',
        ]);

        return new AbaPaywayService();
    }

    public function test_hash_is_base64_hmac_sha512_keyed_by_api_key(): void
    {
        $service = $this->makeService();

        $raw = '20260915093000'.self::MERCHANT_ID.'PY123';

        $expected = base64_encode(hash_hmac('sha512', $raw, self::API_KEY, true));

        $this->assertSame($expected, $service->hash($raw), 'The ABA PayWay hash must be base64 HMAC-SHA512 keyed by the API key.');
    }

    public function test_is_configured_requires_merchant_id_and_api_key(): void
    {
        $service = $this->makeService();
        $this->assertTrue($service->isConfigured());

        config(['payway.merchant_id' => '', 'payway.api_key' => '']);
        $this->assertFalse((new AbaPaywayService)->isConfigured());
    }

    public function test_req_time_is_utc_formatted_as_yyyymmddhhmmss(): void
    {
        $service = $this->makeService();

        $this->assertMatchesRegularExpression('/^\d{14}$/', $service->reqTime());
        $this->assertSame(now()->utc()->format('YmdHis'), $service->reqTime());
    }

    public function test_purchase_url_reads_from_config_with_sandbox_default(): void
    {
        $service = $this->makeService();

        $this->assertSame(
            'https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/purchase',
            $service->purchaseUrl()
        );

        config(['payway.purchase_url' => 'https://checkout.payway.com.kh/api/payment-gateway/v1/payments/purchase']);
        $this->assertSame(
            'https://checkout.payway.com.kh/api/payment-gateway/v1/payments/purchase',
            (new AbaPaywayService)->purchaseUrl()
        );
    }

    public function test_check_url_reads_from_config_with_sandbox_default(): void
    {
        $service = $this->makeService();

        $this->assertSame(
            'https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/check-transaction-2',
            $service->checkUrl()
        );

        config(['payway.check_url' => 'https://checkout.payway.com.kh/api/payment-gateway/v1/payments/check-transaction-2']);
        $this->assertSame(
            'https://checkout.payway.com.kh/api/payment-gateway/v1/payments/check-transaction-2',
            (new AbaPaywayService)->checkUrl()
        );
    }

    public function test_map_gateway_status_matches_official_codes(): void
    {
        $service = $this->makeService();

        $this->assertSame(PaymentStatus::Paid, $service->mapGatewayStatus(0));      // APPROVED / PRE-AUTH
        $this->assertSame(PaymentStatus::Pending, $service->mapGatewayStatus(2));   // PENDING
        $this->assertSame(PaymentStatus::Failed, $service->mapGatewayStatus(3));    // DECLINED
        $this->assertSame(PaymentStatus::Refunded, $service->mapGatewayStatus(4));  // REFUNDED
        $this->assertSame(PaymentStatus::Cancelled, $service->mapGatewayStatus(7)); // CANCELLED
        $this->assertSame(PaymentStatus::Failed, $service->mapGatewayStatus(99));   // unknown
    }

    public function test_verify_callback_signature_accepts_only_matching_hmac(): void
    {
        $service = $this->makeService();

        $payload = ['tran_id' => 'PY123', 'status' => '0', 'apv' => '753786'];
        ksort($payload);

        $raw = implode('', array_map(fn ($value) => (string) $value, $payload));
        $goodSignature = base64_encode(hash_hmac('sha512', $raw, self::API_KEY, true));

        $this->assertTrue($service->verifyCallbackSignature($payload, $goodSignature));
        $this->assertFalse($service->verifyCallbackSignature($payload, 'forged-signature'));
        $this->assertFalse($service->verifyCallbackSignature($payload, null));
        $this->assertFalse($service->verifyCallbackSignature($payload, ''));
    }

    public function test_verify_callback_signature_is_rejected_when_not_configured(): void
    {
        config(['payway.api_key' => '', 'payway.merchant_id' => '']);

        $service = new AbaPaywayService;

        $this->assertFalse($service->verifyCallbackSignature(['tran_id' => 'PY1'], 'anything'));
    }

    public function test_absolute_url_resolves_relative_paths_against_app_url(): void
    {
        config(['app.url' => 'https://store.example.com']);

        $service = $this->makeService();

        $this->assertSame('https://store.example.com/payment', $service->absoluteUrl('/payment'));
        $this->assertSame('https://tunnel.example.com/api/v1/payments/payway/webhook', $service->absoluteUrl('https://tunnel.example.com/api/v1/payments/payway/webhook'));
    }
}