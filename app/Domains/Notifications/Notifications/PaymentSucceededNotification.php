<?php

namespace App\Domains\Notifications\Notifications;

use App\Domains\Billing\Models\Payment;
use App\Domains\Notifications\Contracts\ShouldWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentSucceededNotification extends Notification implements ShouldQueue, ShouldWebPush
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
            'message' => $this->isTopup()
                ? 'بستهٔ اعتبار شما با موفقیت خریداری شد و به کیف پول اضافه گردید.'
                : 'پرداخت شما با موفقیت ثبت شد و پلن فعال شد.',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $summary = $this->isTopup()
            ? 'بستهٔ '.(int) config('credits.packs.'.$this->payment->plan_key.'.credits').' اعتبار با موفقیت خریداری شد و به کیف پول شما اضافه گردید.'
            : 'پرداخت پلن '.$this->payment->plan_key.' با موفقیت ثبت شد.';

        return (new MailMessage)
            ->subject('پرداخت Hale با موفقیت انجام شد')
            ->greeting('سلام '.$notifiable->name)
            ->line($summary)
            ->line('مبلغ: '.$this->payment->amount.' ریال')
            ->action('مشاهده پرداخت‌ها', url('/pricing'));
    }

    private function isTopup(): bool
    {
        return str_starts_with((string) $this->payment->plan_key, 'topup_');
    }
}
