<?php

return [
    'initial_balance' => (int) env('INITIAL_CREDIT_BALANCE', 30),
    'costs' => [
        'image' => ['standard' => 10, 'premium' => 25],
        'video' => ['base' => 20, 'per_second' => 5],
    ],
];
