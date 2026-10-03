<?php

namespace App\Domains\Notifications\Contracts;

/**
 * Marker for notifications that should also wake the browser via Web Push
 * when their database row is written. Notifications without this interface
 * (welcome, verify email, admin budget alerts) never trigger a push.
 */
interface ShouldWebPush {}
