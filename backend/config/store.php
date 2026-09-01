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
];