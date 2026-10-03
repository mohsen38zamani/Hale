<?php

namespace App\Domains\Notifications\Listeners;

use App\Domains\Notifications\Contracts\ShouldWebPush;
use App\Domains\Notifications\Services\WebPushSender;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * After a ShouldWebPush notification is stored for a user, wake their
 * browsers so the service worker can fetch and display it. Runs on the
 * queue so the notification flow never waits on push services.
 */
class SendWebPushNotification implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(private readonly WebPushSender $sender) {}

    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database') {
            return;
        }

        if (! $event->notification instanceof ShouldWebPush) {
            return;
        }

        $user = $event->notifiable;
        if (! $user instanceof User) {
            return;
        }

        $data = $event->notification->toDatabase($user);
        $message = (string) ($data['message'] ?? 'اعلان جدیدی از Hale داری.');

        $url = isset($data['generation_id'])
            ? '/generations/'.$data['generation_id']
            : '/dashboard';

        $this->sender->sendToUser($user, $message, $url);
    }
}
