<?php

return [
    'currency' => env('STORE_CURRENCY', 'MXN'),
    'shipping_flat_cents' => (int) env('STORE_SHIPPING_FLAT_CENTS', 9900),
    'free_shipping_threshold_cents' => (int) env('STORE_FREE_SHIPPING_THRESHOLD_CENTS', 80000),
];
