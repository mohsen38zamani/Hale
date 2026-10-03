<?php

namespace Tests\Feature\Notifications;

use App\Domains\Notifications\Contracts\ShouldWebPush;
use Illuminate\Notifications\Notification;

/**
 * Lightweight stand-in for real user-facing notifications; lives in its own
 * PSR-4 file so the queued event listener can serialize/unserialize it.
 */
class PushTestNotification extends Notification implements ShouldWebPush
{
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return ['kind' => 'push_test', 'message' => 'تولید شما با موفقیت تکمیل شد.'];
    }
}
