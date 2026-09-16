<?php

namespace App\Services;

/**
 * Generates EMVCo-compliant KHQR strings locally without calling any API.
 *
 * Follows the official KHQR Content Guideline v1.3 and KHQR SDK from the
 * National Bank of Cambodia. The generated QR string can be scanned by any
 * KHQR-enabled banking app.
 *
 * For an individual/solo Bakong account (the case of an account id like
 * "name@bkrt"), the Merchant Account Information uses tag 29 and the Bakong
 * account id goes in sub-tag 00 of that tag. Additional data (bill number,
 * store label, terminal label, purpose, mobile) belongs in tag 62, and a
 * dynamic QR includes a timestamp tag 99 carrying creation/expiration
 * timestamps in milliseconds.
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

    private const TAG_ADDITIONAL_DATA = '62';

    private const TAG_CRC = '63';

    private const TAG_TIMESTAMP = '99';

    // Merchant Account Information (Tag 29) sub-tags — Individual/Solo KHQR.
    // Sub-tag 00 carries the Bakong account id itself (NOT a GUID).
    private const MERCHANT_ACCOUNT_SUB_TAG_ACCOUNT = '00';

    private const MERCHANT_ACCOUNT_SUB_TAG_ACCOUNT_INFO = '01';

    private const MERCHANT_ACCOUNT_SUB_TAG_ACQUIRING_BANK = '02';

    // Additional Data Field (Tag 62) sub-tags.
    private const ADDITIONAL_DATA_BILL_NUMBER = '01';

    private const ADDITIONAL_DATA_MOBILE_NUMBER = '02';

    private const ADDITIONAL_DATA_STORE_LABEL = '03';

    private const ADDITIONAL_DATA_TERMINAL_LABEL = '07';

    private const ADDITIONAL_DATA_PURPOSE = '08';

    // Timestamp (Tag 99) sub-tags — epoch milliseconds.
    private const TIMESTAMP_SUB_TAG_CREATE = '00';

    private const TIMESTAMP_SUB_TAG_EXPIRE = '01';

    private const CURRENCY_MAP = [
        'USD' => '840',
        'KHR' => '116',
    ];

    private const MAX_MERCHANT_NAME = 25;

    private const MAX_MERCHANT_CITY = 15;

    private const MAX_ADDITIONAL_DATA_VALUE = 25;

    private const MAX_ACCOUNT_ID = 32;

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
     *     ?account_information: string,
     *     ?acquiring_bank: string,
     *     ?created_at: \Carbon\CarbonInterface,
     *     ?expires_at: \Carbon\CarbonInterface,
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
        $accountInformation = $params['account_information'] ?? null;
        $acquiringBank = $params['acquiring_bank'] ?? null;
        $createdAt = $params['created_at'] ?? null;
        $expiresAt = $params['expires_at'] ?? null;

        $isDynamic = $amount > 0;

        $tags = [];

        // Tag 00: Payload Format Indicator (fixed "01")
        $tags[] = $this->tag(self::TAG_PAYLOAD_FORMAT, '01');

        // Tag 01: Point of Initiation Method ("12" = dynamic QR with amount)
        $tags[] = $this->tag(self::TAG_POINT_OF_INITIATION, $isDynamic ? '12' : '11');

        // Tag 29: Merchant Account Information (individual/solo Bakong KHQR)
        $merchantAccountData = $this->buildMerchantAccountInfo($accountId, $accountInformation, $acquiringBank);
        $tags[] = $this->tag(self::TAG_MERCHANT_ACCOUNT_INFO, $merchantAccountData);

        // Tag 52: Merchant Category Code (5999 = General Merchant)
        $tags[] = $this->tag(self::TAG_MERCHANT_CATEGORY, '5999');

        // Tag 53: Transaction Currency
        $currencyCode = self::CURRENCY_MAP[$currency] ?? '840';
        $tags[] = $this->tag(self::TAG_TRANSACTION_CURRENCY, $currencyCode);

        // Tag 54: Transaction Amount (only for dynamic QR)
        if ($isDynamic) {
            $tags[] = $this->tag(self::TAG_TRANSACTION_AMOUNT, $this->formatAmount($amount, $currency));
        }

        // Tag 58: Country Code
        $tags[] = $this->tag(self::TAG_COUNTRY_CODE, 'KH');

        // Tag 59: Merchant Name (max 25 chars)
        $tags[] = $this->tag(self::TAG_MERCHANT_NAME, mb_substr($merchantName, 0, self::MAX_MERCHANT_NAME));

        // Tag 60: Merchant City (max 15 chars)
        $tags[] = $this->tag(self::TAG_MERCHANT_CITY, mb_substr($merchantCity, 0, self::MAX_MERCHANT_CITY));

        // Tag 62: Additional Data Field (optional)
        $additionalData = $this->buildAdditionalData($billNumber, $mobileNumber, $storeLabel, $terminalLabel, $purpose);
        if ($additionalData !== '') {
            $tags[] = $this->tag(self::TAG_ADDITIONAL_DATA, $additionalData);
        }

        // Tag 99: Timestamp (only for dynamic QR; creation + expiration ms)
        if ($isDynamic && ($createdAt !== null || $expiresAt !== null)) {
            $create = $createdAt ?? now();
            $expire = $expiresAt ?? $create->copy();

            $tags[] = $this->tag(self::TAG_TIMESTAMP,
                $this->tag(self::TIMESTAMP_SUB_TAG_CREATE, (string) $create->getTimestampMs())
                .$this->tag(self::TIMESTAMP_SUB_TAG_EXPIRE, (string) $expire->getTimestampMs())
            );
        }

        // Build payload without CRC
        $payload = implode('', $tags);

        // Tag 63: CRC (CRC-16 CCITT-FALSE)
        $crc = $this->crc16($payload.self::TAG_CRC.'04');
        $payload .= self::TAG_CRC.'04'.strtoupper($crc);

        return $payload;
    }

    /**
     * Generate the MD5 hash of a KHQR string (used for transaction checking).
     */
    public function generateMd5(string $qrString): string
    {
        return md5($qrString);
    }

    /**
     * Validate a KHQR string against the EMVCo/KHQR rules.
     *
     * Returns a list of human-readable error messages; an empty array means
     * the QR string is structurally valid. Optional expected values may be
     * supplied to enforce that the payload matches the intended payment.
     *
     * @param  array{amount?: float, currency?: string, account_id?: string}  $expected
     * @return string[]
     */
    public function validate(string $qrString, array $expected = []): array
    {
        $errors = [];
        $tags = $this->parse($qrString);

        // 1. Payload Format Indicator
        if (($tags['00'] ?? '') !== '01') {
            $errors[] = 'Payload Format Indicator must be "01".';
        }

        // 2. Point of Initiation Method
        $pointOfInitiation = $tags['01'] ?? null;
        if (! in_array($pointOfInitiation, ['11', '12'], true)) {
            $errors[] = 'Point of Initiation Method must be "11" or "12".';
        }

        // 3. Merchant Account Information
        $merchantAccount = isset($tags['29']) ? $this->parse($tags['29']) : [];
        if ($merchantAccount === []) {
            $errors[] = 'Missing Merchant Account Information (tag 29).';
        } else {
            $accountId = $merchantAccount['00'] ?? '';
            if ($accountId === '') {
                $errors[] = 'Merchant Account Information must contain the Bakong account id (sub-tag 00).';
            }
            if (strlen($accountId) > self::MAX_ACCOUNT_ID) {
                $errors[] = 'Bakong account id exceeds 32 chars.';
            }
        }

        // 4. Transaction Currency
        $currencyCode = $tags['53'] ?? null;
        if (! in_array($currencyCode, self::CURRENCY_MAP, true)) {
            $errors[] = 'Transaction Currency must be USD (840) or KHR (116).';
        }

        // 5. Amount is mandatory for dynamic QR, forbidden for static
        $isDynamic = $pointOfInitiation === '12';
        $amount = null;
        if ($isDynamic && ! isset($tags['54'])) {
            $errors[] = 'Dynamic KHQR requires a Transaction Amount (tag 54).';
        }
        if (! $isDynamic && isset($tags['54'])) {
            $errors[] = 'Static KHQR must not contain a Transaction Amount (tag 54).';
        }
        if (isset($tags['54'])) {
            $amount = (float) $tags['54'];
            if ($amount <= 0) {
                $errors[] = 'Transaction Amount must be positive.';
            }
        }

        // 6. Country Code
        if (($tags['58'] ?? '') !== 'KH') {
            $errors[] = 'Country Code must be "KH".';
        }

        // 7. Merchant Name
        if (! isset($tags['59']) || $tags['59'] === '') {
            $errors[] = 'Missing Merchant Name (tag 59).';
        }

        // 8. Merchant City
        if (! isset($tags['60']) || $tags['60'] === '') {
            $errors[] = 'Missing Merchant City (tag 60).';
        }

        // 9. Additional Data Field sub-tag lengths
        if (isset($tags['62'])) {
            foreach ($this->parse($tags['62']) as $subTag => $value) {
                if (strlen($value) > self::MAX_ADDITIONAL_DATA_VALUE) {
                    $errors[] = "Additional Data sub-tag {$subTag} exceeds 25 chars.";
                }
            }
        }

        // 10. CRC16
        if (preg_match('/63(..)([0-9A-F]{4})$/', $qrString, $matches) !== 1) {
            $errors[] = 'QR string does not end with a valid CRC (tag 63).';
        } else {
            $storedCrc = $matches[2];
            $computedCrc = strtoupper($this->crc16(substr($qrString, 0, -4)));
            if ($storedCrc !== $computedCrc) {
                $errors[] = "CRC mismatch: expected {$computedCrc}, found {$storedCrc}.";
            }
        }

        // Expected values (when provided)
        if (isset($expected['currency'])) {
            $mapped = self::CURRENCY_MAP[strtoupper($expected['currency'])] ?? null;
            if ($mapped !== null && $currencyCode !== $mapped) {
                $errors[] = "Currency mismatch: expected {$expected['currency']} ({$mapped}).";
            }
        }
        if (isset($expected['amount']) && $amount !== null) {
            if (abs($amount - $expected['amount']) > 0.005) {
                $errors[] = "Amount mismatch: expected {$expected['amount']}, found {$amount}.";
            }
        }
        if (isset($expected['account_id'])) {
            $encodedAccountId = $merchantAccount['00'] ?? '';
            if ($encodedAccountId !== $expected['account_id']) {
                $errors[] = 'Merchant account id mismatch.';
            }
        }

        return $errors;
    }

    /**
     * Build the Merchant Account Information value for an individual/solo
     * Bakong account (tag 29). The account id is sub-tag 00; optional
     * account information and acquiring bank may follow.
     */
    private function buildMerchantAccountInfo(
        string $accountId,
        ?string $accountInformation,
        ?string $acquiringBank,
    ): string {
        $data = self::MERCHANT_ACCOUNT_SUB_TAG_ACCOUNT
            .$this->length($accountId)
            .$accountId;

        // Sub-tag 01: Account Information (optional)
        if ($accountInformation !== null && $accountInformation !== '') {
            $data .= self::MERCHANT_ACCOUNT_SUB_TAG_ACCOUNT_INFO
                .$this->length($accountInformation)
                .$accountInformation;
        }

        // Sub-tag 02: Acquiring Bank (optional)
        if ($acquiringBank !== null && $acquiringBank !== '') {
            $data .= self::MERCHANT_ACCOUNT_SUB_TAG_ACQUIRING_BANK
                .$this->length($acquiringBank)
                .$acquiringBank;
        }

        return $data;
    }

    /**
     * Build the Additional Data Field value (tag 62).
     */
    private function buildAdditionalData(
        ?string $billNumber,
        ?string $mobileNumber,
        ?string $storeLabel,
        ?string $terminalLabel,
        ?string $purpose,
    ): string {
        $data = '';

        // Sub-tag 01: Bill Number (optional, max 25)
        if ($billNumber !== null && $billNumber !== '') {
            $data .= self::ADDITIONAL_DATA_BILL_NUMBER
                .$this->length($billNumber)
                .mb_substr($billNumber, 0, self::MAX_ADDITIONAL_DATA_VALUE);
        }

        // Sub-tag 02: Mobile Number (optional, max 25)
        if ($mobileNumber !== null && $mobileNumber !== '') {
            $data .= self::ADDITIONAL_DATA_MOBILE_NUMBER
                .$this->length($mobileNumber)
                .mb_substr($mobileNumber, 0, self::MAX_ADDITIONAL_DATA_VALUE);
        }

        // Sub-tag 03: Store Label (optional, max 25)
        if ($storeLabel !== null && $storeLabel !== '') {
            $data .= self::ADDITIONAL_DATA_STORE_LABEL
                .$this->length($storeLabel)
                .mb_substr($storeLabel, 0, self::MAX_ADDITIONAL_DATA_VALUE);
        }

        // Sub-tag 07: Terminal Label (optional, max 25)
        if ($terminalLabel !== null && $terminalLabel !== '') {
            $data .= self::ADDITIONAL_DATA_TERMINAL_LABEL
                .$this->length($terminalLabel)
                .mb_substr($terminalLabel, 0, self::MAX_ADDITIONAL_DATA_VALUE);
        }

        // Sub-tag 08: Purpose of Transaction (optional, max 25)
        if ($purpose !== null && $purpose !== '') {
            $data .= self::ADDITIONAL_DATA_PURPOSE
                .$this->length($purpose)
                .mb_substr($purpose, 0, self::MAX_ADDITIONAL_DATA_VALUE);
        }

        return $data;
    }

    /**
     * Build an EMVCo TLV tag: ID + length (2 digits) + value.
     */
    private function tag(string $id, string $value): string
    {
        return $id.$this->length($value).$value;
    }

    /**
     * Two-digit zero-padded length. Uses byte length to match the official
     * Java/SDK encoder (UTF-8). All fields generated here are ASCII.
     */
    private function length(string $value): string
    {
        return str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT);
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
     * Parse a TLV string into ["id" => "value"].
     *
     * @return array<string, string>
     */
    private function parse(string $data): array
    {
        $tags = [];
        $offset = 0;
        $totalLength = strlen($data);

        while ($offset + 4 <= $totalLength) {
            $id = substr($data, $offset, 2);
            $valueLength = (int) substr($data, $offset + 2, 2);
            $offset += 4;

            if ($offset + $valueLength > $totalLength) {
                break;
            }

            $tags[$id] = substr($data, $offset, $valueLength);
            $offset += $valueLength;
        }

        return $tags;
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
