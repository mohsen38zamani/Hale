<?php

namespace App\Domains\Notifications\Notifications;

use App\Domains\Billing\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentSucceededNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Payment $payment) {}

    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['database', 'mail'] : ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'kind' => 'payment_succeeded',
            'payment_id' => $this->payment->id,
            'plan_key' => $this->payment->plan_key,
            'amount' => $this->payment->amount,
            'reference' => $this->payment->reference,
            'message' => 'پرداخت شما با موفقیت ثبت شد و پلن فعال شد.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('پرداخت Hale با موفقیت انجام شد')
            ->greeting('سلام '.$notifiable->name)
            ->line('پرداخت پلن '.$this->payment->plan_key.' با موفقیت ثبت شد.')
            ->line('مبلغ: '.$this->payment->amount.' ریال')
            ->action('مشاهده پرداخت‌ها', url('/pricing'));
    }
}
