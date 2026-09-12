<?php

namespace App\Domains\Notifications\Notifications;

use App\Domains\Generations\Models\Generation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GenerationStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Generation $generation, private readonly string $status) {}

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'generation_'.$this->status,
            'generation_id' => $this->generation->id,
            'type' => $this->generation->type,
            'status' => $this->status,
            'message' => $this->status === 'completed' ? 'تولید شما با موفقیت تکمیل شد.' : 'تولید شما ناموفق بود و اعتبار رزروشده برگشت داده شد.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $completed = $this->status === 'completed';

        return (new MailMessage)
            ->subject($completed ? 'خروجی Hale آماده است' : 'تولید Hale ناموفق بود')
            ->greeting('سلام '.$notifiable->name)
            ->line($completed ? 'خروجی شما با موفقیت آماده شده است.' : 'تولید شما ناموفق بود و اعتبار رزروشده برگشت داده شد.')
            ->action($completed ? 'مشاهده خروجی' : 'مشاهده تاریخچه', url($completed ? '/generations/'.$this->generation->id : '/dashboard'));
    }
}
