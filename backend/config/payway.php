<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ABA PayWay payment gateway
    |--------------------------------------------------------------------------
    |
    | Server-side gateway configuration. Secrets (merchant id, API key) are
    | read from the environment and must NEVER reach the React client.
    |
    | Base URLs (from the official PayWay developer docs):
    |   sandbox   https://checkout-sandbox.payway.com.kh/
    |   production https://checkout.payway.com.kh/
    |
    */

    'environment' => env('PAYWAY_ENVIRONMENT', 'sandbox'),

    'merchant_id' => env('PAYWAY_MERCHANT_ID', ''),

    'api_key' => env('PAYWAY_API_KEY', ''),

    'base_url' => env('PAYWAY_BASE_URL', 'https://checkout-sandbox.payway.com.kh/'),

    'timeout' => (int) env('PAYWAY_TIMEOUT', 30),

    /*
    | URL PayWay uses to POST the callback/webhook when a payment outcome is
    | known. Relative paths resolve against the Laravel host.
    */
    'callback_url' => env('PAYWAY_CALLBACK_URL', '/api/v1/payments/payway/webhook'),

    /*
    | Return URLs passed to PayWay so the payer is sent back to our app.
    | In the deeplink flows these are informational; the SPA handles the
    | outcome by polling payment-status.
    */
    'return_url' => env('PAYWAY_RETURN_URL', '/payment'),

    /*
    | Time-to-live of a PayWay transaction in minutes (minimum accepted = 3).
    | Mirrored into payments.expires_at so we can expire stale attempts
    | ourselves even if the gateway is unreachable.
    */
    'lifetime' => (int) env('PAYWAY_LIFETIME', 30),

    'currency' => env('PAYWAY_CURRENCY', 'USD'),

    /*
    | Enabled payment methods surfaced via GET /api/v1/payment-methods and
    | enforced when a payment is created. Staff can toggle per method.
    */
    'methods' => [
        'aba_pay' => (bool) env('PAYWAY_ABA_PAY_ENABLED', true),
        'khqr' => (bool) env('PAYWAY_KHQR_ENABLED', true),
        'card' => (bool) env('PAYWAY_CARD_ENABLED', true),
    ],

    /*
    | When true, background/refresh reconciliation may call the gateway's
    | check-transaction endpoint to confirm a pending attempt.
    */
    'verify_transaction' => (bool) env('PAYWAY_VERIFY_TRANSACTION', true),
];