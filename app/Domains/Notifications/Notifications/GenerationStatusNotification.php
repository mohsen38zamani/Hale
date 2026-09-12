<?php

namespace App\Domains\Notifications\Notifications;

use App\Domains\Generations\Models\Generation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class GenerationStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Generation $generation, private readonly string $status) {}

    public function via(object $notifiable): array
    {
        return ['database'];
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
}
