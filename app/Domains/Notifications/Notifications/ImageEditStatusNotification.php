<?php

namespace App\Domains\Notifications\Notifications;

use App\Domains\Editing\Models\ImageEdit;
use App\Domains\Notifications\Contracts\ShouldWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ImageEditStatusNotification extends Notification implements ShouldQueue, ShouldWebPush
{
    use Queueable;

    public function __construct(private readonly ImageEdit $edit, private readonly string $status) {}

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'image_edit_'.$this->status,
            'edit_id' => $this->edit->id,
            'operation' => $this->edit->operation,
            'source_type' => $this->edit->source_type,
            'status' => $this->status,
            'message' => match ($this->status) {
                'completed' => 'ویرایش تصویر شما با موفقیت تکمیل شد.',
                default => 'اجرای ابزار ویرایش ناموفق بود و اعتبار پرداخت‌شده برگشت داده شد.',
            },
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $completed = $this->status === 'completed';

        $subject = $completed ? 'ویرایش تصویر Hale آماده است' : 'ویرایش تصویر Hale ناموفق بود';
        $line = $completed
            ? 'خروجی ابزار هوش مصنوعی شما آماده دانلود است.'
            : 'اجرای ابزار ناموفق بود و اعتبار پرداخت‌شده برگشت داده شد.';

        // Land the user next to the photo they edited: the original output
        // for generation sources, the dashboard for product photos.
        $actionUrl = $this->edit->source_type === 'generation'
            ? '/generations/'.$this->edit->source_id
            : '/dashboard';

        return (new MailMessage)
            ->subject($subject)
            ->greeting('سلام '.$notifiable->name)
            ->line($line)
            ->action($completed ? 'مشاهده نتیجه' : 'بازگشت به داشبورد', $actionUrl);
    }
}
