<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Search Driver
    |--------------------------------------------------------------------------
    |
    | "database" runs LIKE queries straight against MySQL/SQLite and needs no
    | extra infrastructure. "meilisearch" asks the Meilisearch HTTP API for
    | matching ids (scoped per user) and silently falls back to the database
    | driver whenever the service is unreachable or errors out.
    |
    */

    'driver' => env('SEARCH_DRIVER', 'database'),

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key' => env('MEILISEARCH_KEY'),
        'timeout' => (int) env('MEILISEARCH_TIMEOUT', 3),
        'indexes' => [
            'products' => env('MEILISEARCH_PRODUCTS_INDEX', 'products'),
            'generations' => env('MEILISEARCH_GENERATIONS_INDEX', 'generations'),
        ],
    ],

];
