<?php

namespace App\Domains\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $previousPlanKey) {}

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'subscription_expired',
            'plan_key' => $this->previousPlanKey,
            'message' => "اشتراک پلن {$this->previousPlanKey} شما به پایان رسیده و حساب شما به پلن رایگان منتقل شد.",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('انقضای اشتراک Hale')
            ->greeting('سلام '.$notifiable->name)
            ->line("اشتراک پلن {$this->previousPlanKey} شما منقضی شده است و حساب شما به پلن رایگان منتقل گردید.")
            ->line('برای ادامه استفاده از امکانات ویژه و ساخت ویدئو، می‌توانید پلن خود را تمدید کنید.')
            ->action('تمدید یا ارتقای پلن', url('/pricing'));
    }
}
