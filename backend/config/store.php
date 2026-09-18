<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Store business configuration
    |--------------------------------------------------------------------------
    |
    | Server-side values that the OrderService relies on when calculating
    | order figures. Shipping is a flat store-wide fee applied at checkout.
    | Prices and totals are never accepted from the frontend.
    |
    */

    'shipping_fee' => (float) env('STORE_SHIPPING_FEE', 2.00),

    /*
    | The store's base listing currency. Order totals are always stored in the
    | base currency and converted server-side when a customer chooses to pay
    | in KHR (Cambodian Riel) at the payment step.
    */
    'base_currency' => strtoupper((string) env('STORE_CURRENCY', 'USD')),

    /*
    | Exchange rate used to convert a USD order total into KHR for a payment
    | attempt. KHR has no minor units, so converted amounts are rounded to a
    | whole riel. Only used as a display/conversion reference — order totals
    | themselves never change currency.
    */
    'khr_exchange_rate' => (float) env('KHR_EXCHANGE_RATE', 4100),
];