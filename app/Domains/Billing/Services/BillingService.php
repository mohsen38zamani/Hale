<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Contracts\PaymentGateway;
use App\Domains\Billing\Models\Invoice;
use App\Domains\Billing\Models\Payment;
use App\Domains\Billing\Models\Subscription;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Notifications\Notifications\PaymentSucceededNotification;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BillingService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly CreditService $credits,
    ) {}

    public function checkout(User $user, string $planKey, ?string $idempotencyKey = null): array
    {
        $amount = (int) config('plans.'.$planKey.'.price_irr');

        return $this->requestGatewayPayment('checkout', $user, $planKey, $amount, $idempotencyKey, []);
    }

    /**
     * One-time credit pack purchase: settles into the wallet without any
     * subscription or plan change once the gateway callback verifies it.
     */
    public function checkoutTopup(User $user, string $packKey, ?string $idempotencyKey = null): array
    {
        $amount = (int) config('credits.packs.'.$packKey.'.price_irr');
        $metadata = [
            'kind' => 'topup',
            'pack' => $packKey,
            'credits' => (int) config('credits.packs.'.$packKey.'.credits'),
        ];

        return $this->requestGatewayPayment('topup', $user, $packKey, $amount, $idempotencyKey, $metadata);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function requestGatewayPayment(string $scope, User $user, string $itemKey, int $amount, ?string $idempotencyKey, array $metadata): array
    {
        $key = $idempotencyKey !== null && trim($idempotencyKey) !== ''
            ? $scope.':'.$user->id.':'.trim($idempotencyKey)
            : $scope.':'.$user->id.':'.str()->uuid();
        try {
            $payment = DB::transaction(function () use ($user, $itemKey, $amount, $key): Payment {
                $existing = Payment::query()->where('idempotency_key', $key)->lockForUpdate()->first();
                if ($existing !== null) {
                    if ($existing->authority !== null) {
                        return $existing;
                    }
                    if ($existing->status === 'initializing') {
                        throw new ConflictHttpException('در حال ایجاد پرداخت است؛ با همان کلید دوباره تلاش کنید.');
                    }
                    $existing->update(['status' => 'initializing', 'metadata' => null]);

                    return $existing->fresh();
                }

                return Payment::query()->create([
                    'user_id' => $user->id,
                    'plan_key' => $itemKey,
                    'amount' => $amount,
                    'gateway' => config('payment.driver'),
                    'status' => 'initializing',
                    'idempotency_key' => $key,
                ]);
            });
        } catch (QueryException $exception) {
            // A concurrent insert won the unique key; its request owns gateway creation.
            throw new ConflictHttpException('در حال ایجاد پرداخت است؛ با همان کلید دوباره تلاش کنید.', $exception);
        }
        if ($payment->authority !== null) {
            return $this->paymentPayload($payment);
        }

        try {
            $gatewayPayment = $this->gateway->createPayment($user, $itemKey, $amount);
        } catch (\Throwable $exception) {
            $payment->update(['status' => 'failed', 'metadata' => ['gateway_error' => $exception->getMessage()] + $metadata]);
            throw $exception;
        }
        $payment->update(['authority' => $gatewayPayment['authority'], 'status' => 'pending', 'metadata' => ['redirect_url' => $gatewayPayment['redirect_url']] + $metadata]);

        return $this->paymentPayload($payment->fresh());
    }

    public function settle(string $authority, string $status): Payment
    {
        $snapshot = Payment::query()->where('authority', $authority)->firstOrFail();
        if ($snapshot->status === 'paid') {
            return $snapshot;
        }
        if ($status !== 'paid') {
            return DB::transaction(function () use ($authority): Payment {
                $payment = Payment::query()->where('authority', $authority)->lockForUpdate()->firstOrFail();
                if ($payment->status !== 'paid') {
                    $payment->update(['status' => 'failed']);
                }

                return $payment->fresh();
            });
        }

        // HTTP is deliberately outside the short database transaction/row lock.
        $verification = $this->gateway->verifyPayment($authority, (int) $snapshot->amount);
        abort_unless($verification !== false, 422, 'پرداخت توسط درگاه تأیید نشد.');

        return DB::transaction(function () use ($authority, $verification): Payment {
            $payment = Payment::query()->where('authority', $authority)->lockForUpdate()->firstOrFail();
            if ($payment->status === 'paid') {
                $this->ensureInvoice($payment);

                return $payment;
            }
            $now = Carbon::now();

            $payment->update(['status' => 'paid', 'reference' => $verification['reference'], 'paid_at' => $now]);
            $this->ensureInvoice($payment->fresh());

            // Credit packs settle straight into the wallet: no subscription
            // is created, no plan changes, and the grant idempotency key
            // ("payment:{id}") protects against webhook replays.
            if (str_starts_with((string) $payment->plan_key, 'topup_')) {
                $packCredits = (int) config('credits.packs.'.$payment->plan_key.'.credits');
                if ($packCredits > 0) {
                    $this->credits->grantPurchase($payment->user, $packCredits, 'payment:'.$payment->id, [
                        'payment_id' => $payment->id,
                        'pack' => $payment->plan_key,
                    ]);
                }
                DB::afterCommit(fn () => $payment->user->notify(new PaymentSucceededNotification($payment->fresh())));

                return $payment->fresh();
            }

            $months = (int) config('payment.subscription_months');
            $activeSub = Subscription::query()
                ->where('user_id', $payment->user_id)
                ->where('status', 'active')
                ->where('ends_at', '>', $now)
                ->first();

            if ($activeSub !== null && $activeSub->plan_key === $payment->plan_key) {
                $endsAt = $activeSub->ends_at->copy()->addMonths($months);
                $activeSub->update(['ends_at' => $endsAt]);
            } else {
                Subscription::query()->where('user_id', $payment->user_id)->where('status', 'active')->update(['status' => 'expired']);
                $endsAt = $now->copy()->addMonths($months);
                Subscription::create(['user_id' => $payment->user_id, 'plan_key' => $payment->plan_key, 'status' => 'active', 'starts_at' => $now, 'ends_at' => $endsAt]);
            }

            User::query()->whereKey($payment->user_id)->update(['plan_key' => $payment->plan_key]);
            $this->credits->grantPurchase($payment->user, (int) config('plans.'.$payment->plan_key.'.monthly_credits'), 'payment:'.$payment->id, ['payment_id' => $payment->id, 'plan_key' => $payment->plan_key]);
            DB::afterCommit(fn () => $payment->user->notify(new PaymentSucceededNotification($payment->fresh())));

            return $payment->fresh();
        });
    }

    private function ensureInvoice(Payment $payment): Invoice
    {
        return $payment->invoice()->firstOrCreate(
            [],
            [
                'user_id' => $payment->user_id,
                'number' => 'INV-'.$payment->id,
                'amount' => $payment->amount,
                'status' => 'paid',
                'issued_at' => $payment->paid_at ?? now(),
                'metadata' => ['gateway' => $payment->gateway, 'reference' => $payment->reference],
            ],
        );
    }

    private function paymentPayload(Payment $payment): array
    {
        return [
            'payment_id' => $payment->id,
            'authority' => $payment->authority,
            'redirect_url' => $payment->metadata['redirect_url'] ?? null,
        ];
    }
}
