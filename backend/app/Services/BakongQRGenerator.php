<?php

namespace App\Services;

/**
 * Generates EMVCo-compliant KHQR strings locally without calling any API.
 *
 * Follows the KHQR Content Guideline v1.4 from the National Bank of Cambodia.
 * The generated QR string can be scanned by any KHQR-enabled banking app.
 *
 * @see https://bakong.nbc.gov.kh/download/KHQR/integration/KHQR%20Content%20Guideline%20v1.3.pdf
 */
class BakongQRGenerator
{
    private const TAG_PAYLOAD_FORMAT = '00';
    private const TAG_POINT_OF_INITIATION = '01';
    private const TAG_MERCHANT_ACCOUNT_INFO = '29';
    private const TAG_MERCHANT_CATEGORY = '52';
    private const TAG_TRANSACTION_CURRENCY = '53';
    private const TAG_TRANSACTION_AMOUNT = '54';
    private const TAG_COUNTRY_CODE = '58';
    private const TAG_MERCHANT_NAME = '59';
    private const TAG_MERCHANT_CITY = '60';
    private const TAG_CRC = '63';

    private const BAKONG_GLOBALLY_UNIQUE_ID = 'www.bakong.gov.kh';
    private const MERCHANT_ACCOUNT_SUB_TAG_GUID = '00';
    private const MERCHANT_ACCOUNT_SUB_TAG_ACCOUNT = '01';
    private const MERCHANT_ACCOUNT_SUB_TAG_MOBILE = '02';
    private const MERCHANT_ACCOUNT_SUB_TAG_MERCHANT_ID = '03';
    private const MERCHANT_ACCOUNT_SUB_TAG_STORE_LABEL = '07';
    private const MERCHANT_ACCOUNT_SUB_TAG_TERMINAL_LABEL = '08';
    private const MERCHANT_ACCOUNT_SUB_TAG_PURPOSE = '09';

    private const CURRENCY_MAP = [
        'USD' => '840',
        'KHR' => '116',
    ];

    /**
     * Generate a KHQR string for a payment.
     *
     * @param  array{
     *     account_id: string,
     *     merchant_name: string,
     *     merchant_city: string,
     *     amount: float,
     *     currency: string,
     *     ?bill_number: string,
     *     ?store_label: string,
     *     ?terminal_label: string,
     *     ?purpose_of_transaction: string,
     *     ?mobile_number: string,
     *  }  $params
     */
    public function generate(array $params): string
    {
        $accountId = $params['account_id'];
        $merchantName = $params['merchant_name'];
        $merchantCity = $params['merchant_city'];
        $amount = (float) $params['amount'];
        $currency = strtoupper($params['currency']);
        $billNumber = $params['bill_number'] ?? null;
        $storeLabel = $params['store_label'] ?? null;
        $terminalLabel = $params['terminal_label'] ?? null;
        $purpose = $params['purpose_of_transaction'] ?? null;
        $mobileNumber = $params['mobile_number'] ?? null;

        $tags = [];

        // Tag 00: Payload Format Indicator (fixed "01")
        $tags[] = $this->tag(self::TAG_PAYLOAD_FORMAT, '01');

        // Tag 01: Point of Initiation Method ("12" = dynamic QR with amount)
        $tags[] = $this->tag(self::TAG_POINT_OF_INITIATION, $amount > 0 ? '12' : '11');

        // Tag 29: Merchant Account Information (Bakong)
        $merchantAccountData = $this->buildMerchantAccountInfo(
            $accountId,
            $billNumber,
            $storeLabel,
            $terminalLabel,
            $purpose,
            $mobileNumber,
        );
        $tags[] = $this->tag(self::TAG_MERCHANT_ACCOUNT_INFO, $merchantAccountData);

        // Tag 52: Merchant Category Code (5999 = General Merchant)
        $tags[] = $this->tag(self::TAG_MERCHANT_CATEGORY, '5999');

        // Tag 53: Transaction Currency
        $currencyCode = self::CURRENCY_MAP[$currency] ?? '840';
        $tags[] = $this->tag(self::TAG_TRANSACTION_CURRENCY, $currencyCode);

        // Tag 54: Transaction Amount (only for dynamic QR)
        if ($amount > 0) {
            $tags[] = $this->tag(self::TAG_TRANSACTION_AMOUNT, $this->formatAmount($amount, $currency));
        }

        // Tag 58: Country Code
        $tags[] = $this->tag(self::TAG_COUNTRY_CODE, 'KH');

        // Tag 59: Merchant Name (max 25 chars)
        $tags[] = $this->tag(self::TAG_MERCHANT_NAME, mb_substr($merchantName, 0, 25));

        // Tag 60: Merchant City (max 15 chars)
        $tags[] = $this->tag(self::TAG_MERCHANT_CITY, mb_substr($merchantCity, 0, 15));

        // Build payload without CRC
        $payload = implode('', $tags);

        // Tag 63: CRC (CRC-16 CCITT-FALSE)
        $crc = $this->crc16($payload . self::TAG_CRC . '04');
        $payload .= self::TAG_CRC . '04' . strtoupper($crc);

        return $payload;
    }

    /**
     * Generate the MD5 hash of a KHQR string (used for transaction checking).
     */
    public function generateMd5(string $qrString): string
    {
        return md5($qrString);
    }

    private function buildMerchantAccountInfo(
        string $accountId,
        ?string $billNumber,
        ?string $storeLabel,
        ?string $terminalLabel,
        ?string $purpose,
        ?string $mobileNumber,
    ): string {
        $data = '';

        // Sub-tag 00: Globally Unique Identifier
        $data .= self::MERCHANT_ACCOUNT_SUB_TAG_GUID
            . $this->length(self::BAKONG_GLOBALLY_UNIQUE_ID)
            . self::BAKONG_GLOBALLY_UNIQUE_ID;

        // Sub-tag 01: Bakong Account ID
        $data .= self::MERCHANT_ACCOUNT_SUB_TAG_ACCOUNT
            . $this->length($accountId)
            . $accountId;

        // Sub-tag 02: Mobile Number (optional)
        if ($mobileNumber !== null && $mobileNumber !== '') {
            $data .= self::MERCHANT_ACCOUNT_SUB_TAG_MOBILE
                . $this->length($mobileNumber)
                . $mobileNumber;
        }

        // Sub-tag 03: Merchant ID / Bill Number (optional)
        if ($billNumber !== null && $billNumber !== '') {
            $data .= self::MERCHANT_ACCOUNT_SUB_TAG_MERCHANT_ID
                . $this->length($billNumber)
                . $billNumber;
        }

        // Sub-tag 07: Store Label (optional)
        if ($storeLabel !== null && $storeLabel !== '') {
            $data .= self::MERCHANT_ACCOUNT_SUB_TAG_STORE_LABEL
                . $this->length($storeLabel)
                . $storeLabel;
        }

        // Sub-tag 08: Terminal Label (optional)
        if ($terminalLabel !== null && $terminalLabel !== '') {
            $data .= self::MERCHANT_ACCOUNT_SUB_TAG_TERMINAL_LABEL
                . $this->length($terminalLabel)
                . $terminalLabel;
        }

        // Sub-tag 09: Purpose of Transaction (optional)
        if ($purpose !== null && $purpose !== '') {
            $data .= self::MERCHANT_ACCOUNT_SUB_TAG_PURPOSE
                . $this->length($purpose)
                . $purpose;
        }

        return $data;
    }

    /**
     * Build an EMVCo TLV tag: ID + length (2 digits) + value.
     */
    private function tag(string $id, string $value): string
    {
        return $id . $this->length($value) . $value;
    }

    /**
     * Two-digit zero-padded length.
     */
    private function length(string $value): string
    {
        return str_pad((string) mb_strlen($value), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Format amount: 2 decimals for USD, integer for KHR.
     */
    private function formatAmount(float $amount, string $currency): string
    {
        if ($currency === 'KHR') {
            return (string) round($amount);
        }

        return number_format($amount, 2, '.', '');
    }

    /**
     * CRC-16 CCITT-FALSE (polynomial 0x1021, init 0xFFFF).
     */
    private function crc16(string $data): string
    {
        $crc = 0xFFFF;

        for ($i = 0; $i < strlen($data); $i++) {
            $crc ^= ord($data[$i]) << 8;

            for ($j = 0; $j < 8; $j++) {
                if ($crc & 0x8000) {
                    $crc = ($crc << 1) ^ 0x1021;
                } else {
                    $crc <<= 1;
                }

                $crc &= 0xFFFF;
            }
        }

        return str_pad(dechex($crc), 4, '0', STR_PAD_LEFT);
    }
}
