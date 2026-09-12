<?php

return [
    'output_disk' => env('AI_OUTPUT_DISK', env('FILESYSTEM_DISK', 's3')),
    'moderation' => [
        'blocked_terms' => ['pornographic', 'sexual violence', 'child abuse'],
    ],
];
