<?php

return [
    'driver' => env('AI_DRIVER', 'local'),
    'output_disk' => env('AI_OUTPUT_DISK', env('FILESYSTEM_DISK', 's3')),
    'retention_days' => (int) env('AI_OUTPUT_RETENTION_DAYS', 90),
    'max_output_bytes' => (int) env('AI_MAX_OUTPUT_BYTES', 50 * 1024 * 1024),
    'daily_budget_usd' => (float) env('AI_DAILY_BUDGET_USD', 50.0),
    'processing_lease_seconds' => (int) env('AI_PROCESSING_LEASE_SECONDS', 600),
    'moderation' => [
        'blocked_terms' => [
            'pornographic',
            'sexual violence',
            'child abuse',
            'پورنوگرافی',
            'محتوای مستهجن',
            'پورن',
            'خشونت جنسی',
            'آزار جنسی',
            'کودک آزاری',
            'سکس',
        ],
    ],
    'providers' => [
        'google' => [
            'api_key' => env('GOOGLE_AI_API_KEY'),
            'base_url' => env('GOOGLE_AI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'imagen_model' => env('GOOGLE_IMAGEN_MODEL', 'imagen-3.0-generate-002'),
            'veo_model' => env('GOOGLE_VEO_MODEL', 'veo-2.0-generate-001'),
            'timeout' => (int) env('GOOGLE_AI_TIMEOUT', 60),
            'veo_timeout' => (int) env('GOOGLE_VEO_TIMEOUT', 120),
        ],
    ],
];
