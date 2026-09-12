<?php

return [
    'driver' => env('PAYMENT_DRIVER', 'zarinpal'),
    'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET', 'change-me'),
    'subscription_months' => 1,
    'zarinpal' => [
        'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
        'base_url' => env('ZARINPAL_BASE_URL', 'https://payment.zarinpal.com'),
        'start_pay_url' => env('ZARINPAL_START_PAY_URL', 'https://payment.zarinpal.com/pg/StartPay'),
        'callback_url' => env('ZARINPAL_CALLBACK_URL', env('APP_URL').'/api/payments/zarinpal/callback'),
        'currency' => env('ZARINPAL_CURRENCY', 'IRR'),
        'timeout' => (int) env('ZARINPAL_TIMEOUT', 15),
    ],
];