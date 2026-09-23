<?php

return [
    'driver' => env('PAYMENT_DRIVER', 'zarinpal'),
    'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET', 'change-me'),
    'subscription_months' => 1,
    'zarinpal' => [
        'merchant_id' => env('ZARINPAL_MERCHANT_ID'),
        'sandbox' => (bool) env('ZARINPAL_SANDBOX', false),
        'base_url' => env('ZARINPAL_BASE_URL', env('ZARINPAL_SANDBOX', false) ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com'),
        'start_pay_url' => env('ZARINPAL_START_PAY_URL', env('ZARINPAL_SANDBOX', false) ? 'https://sandbox.zarinpal.com/pg/StartPay' : 'https://payment.zarinpal.com/pg/StartPay'),
        'callback_url' => env('ZARINPAL_CALLBACK_URL', env('APP_URL').'/api/payments/zarinpal/callback'),
        'currency' => env('ZARINPAL_CURRENCY', 'IRR'),
        'timeout' => (int) env('ZARINPAL_TIMEOUT', 15),
    ],
    'usd_to_toman_rate' => (int) env('FINANCIAL_USD_TO_TOMAN', env('FINANCIAL_USD_TO_IRR', 100000)),
];
