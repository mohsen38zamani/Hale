<?php

return [
    'driver' => env('AI_DRIVER', 'local'),
    'output_disk' => env('AI_OUTPUT_DISK', env('FILESYSTEM_DISK', 's3')),
    'retention_days' => (int) env('AI_OUTPUT_RETENTION_DAYS', 90),
    'max_output_bytes' => (int) env('AI_MAX_OUTPUT_BYTES', 50 * 1024 * 1024),
    'daily_budget_usd' => (float) env('AI_DAILY_BUDGET_USD', 50.0),
    // Comma-separated provider keys, e.g. "google_imagen,local". Empty keeps
    // registration order (google first, local fallback last).
    'provider_priority' => array_values(array_filter(array_map('trim', explode(',', (string) env('AI_PROVIDER_PRIORITY', ''))))),
    // Budget reservation estimates (actual cost is settled after each run).
    'pricing' => [
        'default' => [
            'image' => (float) env('AI_PRICING_IMAGE_USD', 0.04),
            'video_per_second' => (float) env('AI_PRICING_VIDEO_PER_SECOND_USD', 0.05),
            // One image-edit run (background removal, upscale, ...).
            'edit' => (float) env('AI_PRICING_EDIT_USD', 0.04),
        ],
        // Optional per-provider overrides; null falls back to default.
        'google_imagen' => [
            'image' => env('GOOGLE_IMAGEN_PRICING_IMAGE_USD') !== null ? (float) env('GOOGLE_IMAGEN_PRICING_IMAGE_USD') : null,
        ],
        'google_veo' => [
            'video_per_second' => env('GOOGLE_VEO_PRICING_PER_SECOND_USD') !== null ? (float) env('GOOGLE_VEO_PRICING_PER_SECOND_USD') : null,
        ],
    ],
    // The edge-protection pass of the foreground-invariant fidelity mode:
    // after the scene is rendered, the product is landed again by the edit
    // model with the customer's own photo beside it. A second, optional
    // model call - switched off it costs nothing, and the pass failing
    // never costs the generation itself.
    'edge_protect' => [
        'enabled' => (bool) env('AI_EDGE_PROTECT_ENABLED', true),
        // The pass decorates a finished render; it gets a short leash of
        // its own instead of holding the whole generation hostage.
        'timeout' => (int) env('AI_EDGE_PROTECT_TIMEOUT', 60),
        // Logged on the usage ledger for margin reporting; the user's
        // credits pay for the render, never twice for the pass.
        'cost_usd' => (float) env('AI_EDGE_PROTECT_COST_USD', 0.04),
    ],
    'processing_lease_seconds' => (int) env('AI_PROCESSING_LEASE_SECONDS', 600),
    // How many chained edits one conversation may reach. A reply is checked
    // before it is charged: an unbounded thread is an unbounded bill.
    'max_edit_chain' => (int) env('AI_MAX_EDIT_CHAIN', 8),
    // Text completion settings (caption generator). Falls back to the
    // deterministic template whenever the model is unreachable.
    'text' => [
        'temperature' => (float) env('AI_TEXT_TEMPERATURE', 0.9),
        'max_output_tokens' => (int) env('AI_TEXT_MAX_TOKENS', 540),
    ],
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
            'text_model' => env('GOOGLE_TEXT_MODEL', 'gemini-2.0-flash'),
            // Trade analysis (category/style detection) — the only vision call
            // that runs outside a generation, so it gets its own model knob.
            'vision_model' => env('GOOGLE_VISION_MODEL', 'gemini-2.5-flash'),
            'edit_model' => env('GOOGLE_IMAGE_EDIT_MODEL', 'gemini-2.5-flash-image'),
            'veo_model' => env('GOOGLE_VEO_MODEL', 'veo-2.0-generate-001'),
            'timeout' => (int) env('GOOGLE_AI_TIMEOUT', 60),
            // The one-line Persian-to-English pass runs inside a preview, so
            // it gets its own, much shorter leash than the image providers.
            'translate_timeout' => (int) env('GOOGLE_TRANSLATE_TIMEOUT', 8),
            // One HTTP call. The render itself is a long-running operation
            // and gets its own clock further down.
            'veo_timeout' => (int) env('GOOGLE_VEO_TIMEOUT', 120),
            // Veo answers with an operation name, not a video. Poll until it
            // is done, giving up after this many seconds and letting the
            // chain fall back rather than hold the queue forever.
            'veo_operation_timeout' => (int) env('GOOGLE_VEO_OPERATION_TIMEOUT', 300),
            'veo_poll_interval' => (int) env('GOOGLE_VEO_POLL_INTERVAL', 5),
            // Above this the product photo is left out of the request: a
            // photo too large for the inline limit would turn a render that
            // works into a permanent 400, and the prompt still describes the
            // product without it.
            'veo_max_reference_bytes' => (int) env('GOOGLE_VEO_MAX_REFERENCE_BYTES', 5 * 1024 * 1024),
        ],
    ],
];
