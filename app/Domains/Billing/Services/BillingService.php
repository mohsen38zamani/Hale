<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Contracts\PaymentGateway;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Notifications\Notifications\PaymentSucceededNotification;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly CreditService $credits,
    ) {}

    public function checkout(User $user, string $planKey): array
    {
        $plan = config('plans.'.$planKey);
        $payment = Payment::create([
            'user_id' => $user->id,
            'plan_key' => $planKey,
            'amount' => $plan['price_irr'],
            'gateway' => config('payment.driver'),
            'status' => 'pending',
            'idempotency_key' => 'checkout:'.$user->id.':'.str()->uuid(),
        ]);
        $gatewayPayment = $this->gateway->createPayment($user, $planKey, (int) $plan['price_irr']);
        $payment->update(['authority' => $gatewayPayment['authority']]);

        return ['payment_id' => $payment->id, 'authority' => $payment->authority, 'redirect_url' => $gatewayPayment['redirect_url']];
    }

    public function settle(string $authority, string $status): Payment
    {
        return DB::transaction(function () use ($authority, $status): Payment {
            $payment = Payment::query()->where('authority', $authority)->lockForUpdate()->firstOrFail();
            if ($payment->status === 'paid') {
                return $payment;
            }
            if ($status !== 'paid') {
                $payment->update(['status' => 'failed']);

                return $payment->fresh();
            }

            $verification = $this->gateway->verifyPayment($authority, (int) $payment->amount);
            abort_unless($verification !== false, 422, 'پرداخت توسط درگاه تأیید نشد.');
            $now = Carbon::now();
            $endsAt = $now->copy()->addMonths((int) config('payment.subscription_months'));
            $payment->update(['status' => 'paid', 'reference' => $verification['reference'], 'paid_at' => $now]);
            Subscription::query()->where('user_id', $payment->user_id)->where('status', 'active')->update(['status' => 'expired']);
            Subscription::create(['user_id' => $payment->user_id, 'plan_key' => $payment->plan_key, 'status' => 'active', 'starts_at' => $now, 'ends_at' => $endsAt]);
            User::query()->whereKey($payment->user_id)->update(['plan_key' => $payment->plan_key]);
            $this->credits->grantPurchase($payment->user, (int) config('plans.'.$payment->plan_key.'.monthly_credits'), 'payment:'.$payment->id, ['payment_id' => $payment->id, 'plan_key' => $payment->plan_key]);
            $payment->user->notify(new PaymentSucceededNotification($payment->fresh()));

            return $payment->fresh();
        });
    }
}
