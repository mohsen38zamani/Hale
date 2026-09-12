<?php

namespace App\Domains\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CreditsLowNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $balance) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'credits_low',
            'balance' => $this->balance,
            'message' => 'اعتبار شما رو به پایان است. برای ادامه تولید، پلن خود را ارتقا دهید.',
        ];
    }
}
