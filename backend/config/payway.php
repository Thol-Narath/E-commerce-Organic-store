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
    | Sandbox URLs (from the official PayWay developer docs):
    |   Purchase: https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/purchase
    |   Check:    https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/check-transaction-2
    |
    | Production URLs:
    |   Purchase: https://checkout.payway.com.kh/api/payment-gateway/v1/payments/purchase
    |   Check:    https://checkout.payway.com.kh/api/payment-gateway/v1/payments/check-transaction-2
    |
    */

    'environment' => env('ABAPAYWAY_ENV', 'sandbox'),

    'merchant_id' => env('ABAPAYWAY_MERCHANT_ID', ''),

    'api_key' => env('ABAPAYWAY_API_KEY', ''),

    'purchase_url' => env('ABAPAYWAY_PURCHASE_URL', 'https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/purchase'),

    'check_url' => env('ABAPAYWAY_CHECK_URL', 'https://checkout-sandbox.payway.com.kh/api/payment-gateway/v1/payments/check-transaction-2'),

    'timeout' => (int) env('ABAPAYWAY_TIMEOUT', 30),

    /*
    | URL PayWay uses to POST the callback/webhook when a payment outcome is
    | known. Relative paths resolve against the Laravel host.
    */
    'callback_url' => env('ABAPAYWAY_CALLBACK_URL', '/api/v1/payments/payway/webhook'),

    /*
    | Return URLs passed to PayWay so the payer is sent back to our app.
    | In the deeplink flows these are informational; the SPA handles the
    | outcome by polling payment-status.
    */
    'return_url' => env('ABAPAYWAY_RETURN_URL', '/payment'),

    /*
    | Time-to-live of a PayWay transaction in minutes (minimum accepted = 3).
    | Mirrored into payments.expires_at so we can expire stale attempts
    | ourselves even if the gateway is unreachable.
    */
    'lifetime' => (int) env('ABAPAYWAY_LIFETIME', 30),

    'currency' => env('ABAPAYWAY_CURRENCY', 'USD'),

    /*
    | Enabled payment methods surfaced via GET /api/v1/payment-methods and
    | enforced when a payment is created. Staff can toggle per method.
    */
    'methods' => [
        'aba_pay' => (bool) env('ABAPAYWAY_ABA_PAY_ENABLED', true),
        'khqr' => (bool) env('ABAPAYWAY_KHQR_ENABLED', true),
        'card' => (bool) env('ABAPAYWAY_CARD_ENABLED', true),
    ],

    /*
    | When true, background/refresh reconciliation may call the gateway's
    | check-transaction endpoint to confirm a pending attempt.
    */
    'verify_transaction' => (bool) env('ABAPAYWAY_VERIFY_TRANSACTION', true),
];