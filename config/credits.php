<?php

return [
    'initial_balance' => (int) env('INITIAL_CREDIT_BALANCE', 30),
    'low_balance_threshold' => (int) env('LOW_CREDIT_THRESHOLD', 5),
    'costs' => [
        'image' => ['standard' => 10, 'premium' => 25],
        'video' => ['base' => 20, 'per_second' => 5],
    ],
];
