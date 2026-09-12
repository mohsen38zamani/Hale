<?php

return [
    'phone' => [
        'code_length' => 6,
        'expires_minutes' => 10,
        'max_attempts' => 5,
        'free_credits' => (int) env('PHONE_VERIFICATION_FREE_CREDITS', 30),
        'testing_code' => env('PHONE_VERIFICATION_TESTING_CODE'),
    ],
];
