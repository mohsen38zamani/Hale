<?php

namespace App\Domains\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('تأیید ایمیل Hale')
            ->greeting('سلام '.$notifiable->name)
            ->line('برای فعال‌سازی کامل حساب، ایمیل خود را تأیید کنید.')
            ->line('تا زمان تأیید ایمیل، ساخت محتوا و خرید پلن غیرفعال است.')
            ->action('تأیید ایمیل', $this->verificationUrl($notifiable))
            ->line('این لینک تا ۲۴ ساعت معتبر است.');
    }

    public function verificationUrl(object $notifiable): string
    {
        return URL::temporarySignedRoute('email.verification.verify', now()->addDay(), [
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
            'redirect' => 1,
        ]);
    }
}
