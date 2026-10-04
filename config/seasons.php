<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seasonal studio themes
    |--------------------------------------------------------------------------
    |
    | Themes are checked in order: the first date window that matches wins,
    | so holidays are listed before the generic seasons they overlap. Windows
    | are MM-DD strings and may wrap around New Year (start > end).
    | 09-22 -> 11-21 is covered by the autumn_rain theme, which hands over
    | to black_friday (11-22) before winter starts.
    |
    | SEASON_THEME overrides detection: pin a theme key, use "off" to disable
    | seasonal theming entirely, or "auto" (default) for date detection.
    |
    | prompt_pack / campaign_label power the opt-in campaign style: when a
    | generation request carries campaign=true, CreativeEngine appends the
    | active theme's pack to the prompt (never automatically).
    |
    */

    'active' => env('SEASON_THEME', 'auto'),

    'themes' => [
        [
            'key' => 'yalda',
            'name' => 'یلدا',
            'start' => '12-21',
            'end' => '12-31',
            'emoji' => '🍉',
            'decor' => 'sparkle',
            'palette' => ['#2A0A18', '#E11D48', '#FBBF24'],
            'campaign_label' => 'کمپین یلدا',
            'prompt_pack' => 'Seasonal campaign styling: pomegranate and watermelon accents, warm red-and-gold festive lighting, cozy Persian Yalda night gathering mood.',
        ],
        [
            'key' => 'black_friday',
            'name' => 'جمعه سیاه',
            'start' => '11-22',
            'end' => '11-30',
            'emoji' => '🛍️',
            'decor' => 'sparkle',
            'palette' => ['#0D0D12', '#F59E0B', '#FDE68A'],
            'campaign_label' => 'کمپین جمعه سیاه',
            'prompt_pack' => 'Seasonal campaign styling: dark luxury backdrop with neon-gold highlights, dramatic spotlight beams, bold sale energy.',
        ],
        [
            // Valentine sits inside the winter window and must be listed
            // before it to win the first-match check.
            'key' => 'valentine',
            'name' => 'ولنتاین',
            'start' => '02-10',
            'end' => '02-16',
            'emoji' => '💗',
            'decor' => 'sparkle',
            'palette' => ['#2A0F1B', '#F472B6', '#FDA4AF'],
            'campaign_label' => 'کمپین ولنتاین',
            'prompt_pack' => 'Seasonal campaign styling: soft rose-pink palette, romantic heart-glow bokeh, satin and gift-ribbon accents.',
        ],
        [
            'key' => 'nowruz',
            'name' => 'نوروز',
            'start' => '03-21',
            'end' => '04-02',
            'emoji' => '🌱',
            'decor' => 'vignette',
            'palette' => ['#0C2E22', '#1FA97A', '#E8C547'],
            'campaign_label' => 'کمپین نوروز',
            'prompt_pack' => 'Seasonal campaign styling: fresh spring greens with saffron-gold highlights, Haft-sins inspired botanical touches, bright new-year light.',
        ],
        [
            'key' => 'winter',
            'name' => 'زمستان',
            'start' => '12-01',
            'end' => '03-20',
            'emoji' => '❄️',
            'decor' => 'snowfall',
            'palette' => ['#0B1220', '#60A5FA', '#E0F2FE'],
            'campaign_label' => 'کمپین زمستان',
            'prompt_pack' => 'Seasonal campaign styling: cool blue winter light, frost and snow-dust particles, warm indoor glow contrast.',
        ],
        [
            'key' => 'spring',
            'name' => 'بهار',
            'start' => '04-03',
            'end' => '06-20',
            'emoji' => '🌸',
            'decor' => 'vignette',
            'palette' => ['#132A1A', '#86EFAC', '#F9A8D4'],
            'campaign_label' => 'کمپین بهار',
            'prompt_pack' => 'Seasonal campaign styling: soft blossom pastels, airy diffused daylight, fresh floral accents.',
        ],
        [
            'key' => 'summer',
            'name' => 'تابستان',
            'start' => '06-21',
            'end' => '09-21',
            'emoji' => '☀️',
            'decor' => 'sparkle',
            'palette' => ['#2E1A05', '#FBBF24', '#FB923C'],
            'campaign_label' => 'کمپین تابستان',
            'prompt_pack' => 'Seasonal campaign styling: sun-kissed golden-hour warmth, vibrant summer tones, crisp highlights.',
        ],
        [
            'key' => 'autumn_rain',
            'name' => 'پاییز بارانی',
            'start' => '09-22',
            'end' => '11-21',
            'emoji' => '🌧️',
            'decor' => 'rain',
            'palette' => ['#141A22', '#7DD3FC', '#F59E0B'],
            'campaign_label' => 'کمپین پاییز بارانی',
            'prompt_pack' => 'Seasonal campaign styling: misty rainy-autumn atmosphere, wet-surface reflections, warm window light against cool grey drizzle.',
        ],
    ],

];
