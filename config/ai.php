<?php

return [
    'output_disk' => env('AI_OUTPUT_DISK', 'local'),
    'moderation' => [
        'blocked_terms' => ['pornographic', 'sexual violence', 'child abuse'],
    ],
];
