<?php

return [
    'output_disk' => env('AI_OUTPUT_DISK', env('FILESYSTEM_DISK', 's3')),
    'retention_days' => (int) env('AI_OUTPUT_RETENTION_DAYS', 90),
    'moderation' => [
        'blocked_terms' => ['pornographic', 'sexual violence', 'child abuse'],
    ],
];
