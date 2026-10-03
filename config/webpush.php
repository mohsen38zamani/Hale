<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web Push (VAPID)
    |--------------------------------------------------------------------------
    |
    | Generate a key pair with `php artisan webpush:generate-keys` and put the
    | values here. While the keys are empty the sender is disabled: no push
    | requests are made and the dashboard hides the activation button.
    |
    */

    'subject' => env('WEBPUSH_SUBJECT', 'mailto:hello@example.com'),

    'vapid_public_key' => env('WEBPUSH_VAPID_PUBLIC_KEY'),

    'vapid_private_key' => env('WEBPUSH_VAPID_PRIVATE_KEY'),

    'ttl' => (int) env('WEBPUSH_TTL', 3600),

];
