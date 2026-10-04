<?php

return [
    'initial_balance' => (int) env('INITIAL_CREDIT_BALANCE', 30),
    'low_balance_threshold' => (int) env('LOW_CREDIT_THRESHOLD', 5),
    'costs' => [
        'image' => ['standard' => 10, 'premium' => 25],
        'video' => ['base' => 20, 'per_second' => 5],
    ],

    /*
    |--------------------------------------------------------------------------
    | One-time credit packs
    |--------------------------------------------------------------------------
    |
    | Purchasable without any subscription: the payment settles straight
    | into the wallet as a "purchase" transaction and never touches the
    | user's plan or subscription. Price is IRR like the plan prices.
    |
    */

    'packs' => [
        'topup_50' => ['key' => 'topup_50', 'credits' => 50, 'price_irr' => 1_500_000],
        'topup_150' => ['key' => 'topup_150', 'credits' => 150, 'price_irr' => 4_000_000],
        'topup_400' => ['key' => 'topup_400', 'credits' => 400, 'price_irr' => 9_500_000],
    ],
];
