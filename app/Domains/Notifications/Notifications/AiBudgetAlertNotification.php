<?php

namespace App\Domains\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AiBudgetAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly float $spentUsd,
        public readonly float $limitUsd,
        public readonly float $percentage
    ) {}

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'ai_budget_alert',
            'spent_usd' => $this->spentUsd,
            'limit_usd' => $this->limitUsd,
            'percentage' => $this->percentage,
            'message' => "هشدار بودجه هوش مصنوعی: مصرف روزانه به {$this->percentage}٪ سقف مجاز رسید ({$this->spentUsd} / {$this->limitUsd} USD).",
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('هشدار سقف بودجه هوش مصنوعی Hale')
            ->greeting('سلام '.$notifiable->name)
            ->line("هشدار: مصرف بودجه روزانه هوش مصنوعی به {$this->percentage}٪ رسیده است.")
            ->line("میزان مصرف شده (رزرو + هزینه واقعی): {$this->spentUsd} دلار از سقف {$this->limitUsd} دلار.")
            ->action('مشاهده پنل مدیریت', url('/admin'));
    }
}
