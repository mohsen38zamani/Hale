<?php

return [
    'initial_balance' => (int) env('INITIAL_CREDIT_BALANCE', 30),
    'low_balance_threshold' => (int) env('LOW_CREDIT_THRESHOLD', 5),
    'costs' => [
        'image' => ['standard' => 10, 'premium' => 25],
        'video' => ['base' => 20, 'per_second' => 5],
        // Non-image AI tasks (caption writing, ...).
        'text' => (int) env('CREDIT_COST_TEXT', 3),
        // One-shot AI utility tools (charged upfront, refunded on failure).
        'edit' => [
            'remove_bg' => 5,
            'shadow' => 5,
            'expand' => 10,
            // upscale targets: hd is free-for-all, 2k/4k need a premium plan.
            'upscale' => ['hd' => 5, '2k' => 10, '4k' => 15],
        ],
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
