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
    | 09-22 -> 11-30 intentionally has no theme (autumn gap).
    |
    | SEASON_THEME overrides detection: pin a theme key, use "off" to disable
    | seasonal theming entirely, or "auto" (default) for date detection.
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
        ],
        [
            'key' => 'nowruz',
            'name' => 'نوروز',
            'start' => '03-21',
            'end' => '04-02',
            'emoji' => '🌱',
            'decor' => 'vignette',
            'palette' => ['#0C2E22', '#1FA97A', '#E8C547'],
        ],
        [
            'key' => 'winter',
            'name' => 'زمستان',
            'start' => '12-01',
            'end' => '03-20',
            'emoji' => '❄️',
            'decor' => 'snowfall',
            'palette' => ['#0B1220', '#60A5FA', '#E0F2FE'],
        ],
        [
            'key' => 'spring',
            'name' => 'بهار',
            'start' => '04-03',
            'end' => '06-20',
            'emoji' => '🌸',
            'decor' => 'vignette',
            'palette' => ['#132A1A', '#86EFAC', '#F9A8D4'],
        ],
        [
            'key' => 'summer',
            'name' => 'تابستان',
            'start' => '06-21',
            'end' => '09-21',
            'emoji' => '☀️',
            'decor' => 'sparkle',
            'palette' => ['#2E1A05', '#FBBF24', '#FB923C'],
        ],
    ],

];
