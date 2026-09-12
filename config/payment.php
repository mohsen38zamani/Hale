<?php

return [
    'driver' => env('PAYMENT_DRIVER', 'fake'),
    'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET', 'change-me'),
    'subscription_months' => 1,
];