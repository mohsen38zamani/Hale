<?php

namespace App\Domains\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'welcome',
            'message' => 'به Hale خوش آمدید. اولین محتوای تبلیغاتی خود را بسازید.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('به Hale خوش آمدید')
            ->greeting('سلام '.$notifiable->name)
            ->line('به Hale خوش آمدید. اولین محتوای تبلیغاتی خود را بسازید.')
            ->action('شروع ساخت', url('/create'));
    }
}
