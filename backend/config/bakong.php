<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bakong Open API (National Bank of Cambodia)
    |--------------------------------------------------------------------------
    |
    | Server-side gateway configuration for direct Bakong KHQR integration.
    | Secrets (access token) are read from the environment and must NEVER
    | reach the React client.
    |
    | Base URLs:
    |   SIT         https://sit-api-bakong.nbc.gov.kh/v1
    |   production  https://api-bakong.nbc.gov.kh/v1
    |
    */

    'base_url' => env('BAKONG_BASE_URL', 'https://api-bakong.nbc.gov.kh/v1'),

    'access_token' => env('BAKONG_ACCESS_TOKEN', ''),

    'account_id' => env('BAKONG_ACCOUNT_ID', ''),

    'merchant_name' => env('BAKONG_MERCHANT_NAME', 'Organic Store'),

    'merchant_city' => env('BAKONG_MERCHANT_CITY', 'Phnom Penh'),

    'currency' => env('BAKONG_CURRENCY', 'USD'),

    'timeout' => (int) env('BAKONG_TIMEOUT', 30),

    'lifetime' => (int) env('BAKONG_LIFETIME', 15),

    'enabled' => (bool) env('BAKONG_ENABLED', true),

    'verify_transaction' => (bool) env('BAKONG_VERIFY_TRANSACTION', true),
];
