<?php

namespace Tests\Unit;

use App\Services\BakongQRGenerator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class BakongQRGeneratorTest extends TestCase
{
    private BakongQRGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new BakongQRGenerator();
    }

    public function test_it_generates_a_dynamic_khqr_string(): void
    {
        $qr = $this->generator->generate([
            'account_id' => 'merchant@aclb',
            'merchant_name' => 'Organic Store',
            'merchant_city' => 'Phnom Penh',
            'amount' => 12.50,
            'currency' => 'USD',
            'bill_number' => 'ORD-1001',
        ]);

        // Payload format indicator "01" + point-of-initiation "12" (dynamic)
        $this->assertStringStartsWith('000201', $qr);
        $this->assertStringContainsString('010212', $qr);

        // Merchant Account Info tag 29: sub-tag 00 must be the Bakong account
        // id itself (individual/solo KHQR). No "www.bakong.gov.kh" GUID.
        $this->assertStringContainsString('0013merchant@aclb', $qr);
        $this->assertStringNotContainsString('www.bakong.gov.kh', $qr);

        // Merchant category (52), currency (53 = 840 USD), amount (54),
        // country (58), name (59), city (60) and CRC (63) must be present.
        $this->assertStringContainsString('52045999', $qr);
        $this->assertStringContainsString('5303840', $qr);
        $this->assertStringContainsString('540512.50', $qr);
        $this->assertStringContainsString('5802KH', $qr);
        $this->assertStringContainsString('5913Organic Store', $qr);
        $this->assertStringContainsString('6010Phnom Penh', $qr);
        $this->assertMatchesRegularExpression('/^.*63\d{2}[0-9A-F]{4}$/', $qr);
    }

    public function test_it_builds_additional_data_in_tag_62(): void
    {
        $qr = $this->generator->generate([
            'account_id' => 'merchant@aclb',
            'merchant_name' => 'Organic Store',
            'merchant_city' => 'Phnom Penh',
            'amount' => 10.00,
            'currency' => 'USD',
            'bill_number' => 'ORD-1001',
            'store_label' => 'Organic Store',
            'terminal_label' => 'Web',
            'purpose_of_transaction' => 'Payment',
            'mobile_number' => '85512345678',
        ]);

        // Tag 62 sub-tags: 01 bill, 02 mobile, 03 store, 07 terminal, 08 purpose.
        $this->assertStringContainsString('62620108ORD-1001', $qr);
        $this->assertStringContainsString('021185512345678', $qr);
        $this->assertStringContainsString('0313Organic Store', $qr);
        $this->assertStringContainsString('0703Web', $qr);
        $this->assertStringContainsString('0807Payment', $qr);
    }

    public function test_it_embeds_timestamp_tag_99_for_dynamic_qr(): void
    {
        $createdAt = Carbon::create(2024, 1, 1, 0, 0, 0, 'UTC');
        $expiresAt = $createdAt->copy()->addMinutes(15);

        $qr = $this->generator->generate([
            'account_id' => 'merchant@aclb',
            'merchant_name' => 'Organic Store',
            'merchant_city' => 'Phnom Penh',
            'amount' => 5.00,
            'currency' => 'USD',
            'created_at' => $createdAt,
            'expires_at' => $expiresAt,
        ]);

        // 99 <34> 00 <13> 1704067200000  01 <13> 1704068100000
        $this->assertStringContainsString('993400131704067200000', $qr);
        $this->assertStringContainsString('01131704068100000', $qr);
    }

    public function test_it_uses_khr_currency_code(): void
    {
        $qr = $this->generator->generate([
            'account_id' => 'merchant@aclb',
            'merchant_name' => 'Organic Store',
            'merchant_city' => 'Phnom Penh',
            'amount' => 15000,
            'currency' => 'KHR',
        ]);

        $this->assertStringContainsString('5303116', $qr); // 116 = KHR
        $this->assertStringContainsString('540515000', $qr);
    }

    public function test_it_generates_static_qr_when_amount_is_zero(): void
    {
        $qr = $this->generator->generate([
            'account_id' => 'merchant@aclb',
            'merchant_name' => 'Organic Store',
            'merchant_city' => 'Phnom Penh',
            'amount' => 0,
            'currency' => 'USD',
        ]);

        // Point of initiation "11" = static, no amount tag 54 is emitted.
        $this->assertStringStartsWith('000201', $qr);
        $this->assertStringContainsString('010211', $qr);
        $this->assertDoesNotMatchRegularExpression('/54\d{2}[0-9]/', $qr);
    }

    public function test_md5_is_deterministic_and_matches_qr_string(): void
    {
        $qr = $this->generator->generate([
            'account_id' => 'merchant@aclb',
            'merchant_name' => 'Organic Store',
            'merchant_city' => 'Phnom Penh',
            'amount' => 5.00,
            'currency' => 'USD',
        ]);

        $this->assertSame(md5($qr), $this->generator->generateMd5($qr));
        $this->assertSame($this->generator->generateMd5($qr), $this->generator->generateMd5($qr));
    }

    public function test_crc_is_valid(): void
    {
        $qr = $this->generator->generate([
            'account_id' => 'merchant@aclb',
            'merchant_name' => 'Organic Store',
            'merchant_city' => 'Phnom Penh',
            'amount' => 99.99,
            'currency' => 'USD',
        ]);

        // Extract the CRC from the trailing tag 63 (value is 4 hex chars)
        // and recompute it to prove the checksum is correct.
        preg_match('/63\d{2}([0-9A-F]{4})$/', $qr, $matches);
        $this->assertNotEmpty($matches, 'Missing trailing CRC tag.');

        $crc = $matches[1];
        $payloadWithTag = substr($qr, 0, -4);

        $this->assertSame($crc, strtoupper($this->invokeCrc16($payloadWithTag)));
    }

    public function test_truncates_merchant_name_and_city(): void
    {
        $qr = $this->generator->generate([
            'account_id' => 'merchant@aclb',
            'merchant_name' => str_repeat('A', 50),
            'merchant_city' => str_repeat('B', 50),
            'amount' => 1.00,
            'currency' => 'USD',
        ]);

        $this->assertStringContainsString('5925'.str_repeat('A', 25), $qr);
        $this->assertStringContainsString('6015'.str_repeat('B', 15), $qr);
    }

    public function test_validate_accepts_a_valid_dynamic_khqr(): void
    {
        $qr = $this->generator->generate([
            'account_id' => 'merchant@aclb',
            'merchant_name' => 'Organic Store',
            'merchant_city' => 'Phnom Penh',
            'amount' => 22.50,
            'currency' => 'USD',
            'bill_number' => 'ORD-2002',
        ]);

        $errors = $this->generator->validate($qr, [
            'amount' => 22.50,
            'currency' => 'USD',
            'account_id' => 'merchant@aclb',
        ]);

        $this->assertSame([], $errors);
    }

    public function test_validate_rejects_a_tampered_crc(): void
    {
        $qr = $this->generator->generate([
            'account_id' => 'merchant@aclb',
            'merchant_name' => 'Organic Store',
            'merchant_city' => 'Phnom Penh',
            'amount' => 33.00,
            'currency' => 'USD',
        ]);

        $tampered = substr($qr, 0, -4).'0000';

        $errors = $this->generator->validate($tampered);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('CRC', implode(' ', $errors));
    }

    public function test_validate_rejects_an_unknown_merchant_account(): void
    {
        $qr = $this->generator->generate([
            'account_id' => 'someoneelse@aclb',
            'merchant_name' => 'Organic Store',
            'merchant_city' => 'Phnom Penh',
            'amount' => 7.00,
            'currency' => 'USD',
        ]);

        $errors = $this->generator->validate($qr, ['account_id' => 'merchant@aclb']);

        $this->assertNotEmpty($errors);
    }

    private function invokeCrc16(string $data): string
    {
        $method = new \ReflectionMethod($this->generator, 'crc16');
        $method->setAccessible(true);

        return $method->invoke($this->generator, $data);
    }
}