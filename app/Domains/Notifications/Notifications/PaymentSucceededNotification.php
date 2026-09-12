<?php

namespace App\Domains\Notifications\Notifications;

use App\Domains\Billing\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentSucceededNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
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
}
