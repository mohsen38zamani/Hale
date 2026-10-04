<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web delivery optimization
    |--------------------------------------------------------------------------
    |
    | Media assets get an on-demand WebP variant capped at the dimension
    | below so previews and downloads stay small on real connections.
    | The untouched original is always kept for AI input and for callers
    | that explicitly ask for ?variant=original.
    |
    */

    'web_max_dimension' => (int) env('MEDIA_WEB_MAX_DIMENSION', 1600),
    'web_quality' => (int) env('MEDIA_WEB_QUALITY', 82),

];
