<?php

namespace App\Domains\Credits\Services;

use App\Domains\Credits\Exceptions\InsufficientCredits;
use App\Domains\Credits\Models\CreditAccount;
use App\Domains\Generations\Models\Generation;
use App\Domains\Notifications\Notifications\CreditsLowNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreditService
{
    public function initialize(User $user, int $balance = 0): CreditAccount
    {
        return $user->creditAccount()->firstOrCreate([], ['balance' => $balance]);
    }

    public function account(User $user): CreditAccount
    {
        return $user->creditAccount()->firstOrCreate([], ['balance' => config('credits.initial_balance')]);
    }

    public function reserve(User $user, Generation $generation, int $amount): void
    {
        $remaining = DB::transaction(function () use ($user, $generation, $amount): int {
            $account = $this->lockedAccount($user);
            if ($account->balance < $amount) {
                throw new InsufficientCredits('اعتبار کافی نیست.');
            }

            $account->decrement('balance', $amount);
            $account->increment('reserved', $amount);
            $attempt = $account->transactions()->where('generation_id', $generation->id)->where('type', 'reserve')->count() + 1;
            $this->record($account->fresh(), $generation, 'reserve', -$amount, "generation:{$generation->id}:reserve:{$attempt}");
            $generation->update(['credits_reserved' => $amount]);

            return (int) $account->fresh()->balance;
        });

        if ($remaining <= (int) config('credits.low_balance_threshold')) {
            $alreadyNotified = $user->unreadNotifications()
                ->where('type', CreditsLowNotification::class)
                ->whereJsonContains('data->kind', 'credits_low')
                ->exists();
            if (! $alreadyNotified) {
                $user->notify(new CreditsLowNotification($remaining));
            }
        }
    }

    public function settle(Generation $generation): void
    {
        DB::transaction(function () use ($generation): void {
            // Lock the account first so concurrent settle/refund calls
            // serialize on the same row instead of passing a stale guard.
            $account = $this->lockedAccount($generation->user);
            $generation->refresh();
            if ($generation->credits_charged > 0 || $generation->credits_reserved < 1) {
                return;
            }
            $amount = $generation->credits_reserved;

            // Atomic claim: exactly one caller can consume the reservation.
            $claimed = Generation::query()
                ->whereKey($generation->id)
                ->where('credits_charged', 0)
                ->where('credits_reserved', $amount)
                ->update(['credits_charged' => $amount, 'credits_reserved' => 0]);
            if ($claimed === 0) {
                $generation->refresh();

                return;
            }

            $account->decrement('reserved', $amount);
            $account->increment('lifetime_used', $amount);
            $this->record($account->fresh(), $generation, 'charge', $amount, "generation:{$generation->id}:charge");
            $generation->refresh();
        });
    }

    public function refund(Generation $generation): void
    {
        DB::transaction(function () use ($generation): void {
            // Lock the account first so concurrent settle/refund calls
            // serialize on the same row instead of passing a stale guard.
            $account = $this->lockedAccount($generation->user);
            $generation->refresh();
            if ($generation->credits_reserved < 1) {
                return;
            }
            $amount = $generation->credits_reserved;

            // Atomic claim: exactly one caller can return the reservation.
            $claimed = Generation::query()
                ->whereKey($generation->id)
                ->where('credits_charged', 0)
                ->where('credits_reserved', $amount)
                ->update(['credits_reserved' => 0]);
            if ($claimed === 0) {
                $generation->refresh();

                return;
            }

            $account->decrement('reserved', $amount);
            $account->increment('balance', $amount);
            $refundNumber = $account->transactions()->where('generation_id', $generation->id)->where('type', 'refund')->count() + 1;
            $this->record($account->fresh(), $generation, 'refund', $amount, "generation:{$generation->id}:refund:{$refundNumber}");
            $generation->refresh();
        });
    }

    private function lockedAccount(User $user): CreditAccount
    {
        $this->account($user);

        return CreditAccount::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
    }

    public function grantBonus(User $user, int $amount, string $key): int
    {
        return $this->grant($user, $amount, $key, 'bonus', ['reason' => $key]);
    }

    public function grantPurchase(User $user, int $amount, string $key, array $metadata = []): int
    {
        return $this->grant($user, $amount, $key, 'purchase', $metadata);
    }

    public function manualRefund(User $user, int $amount, string $reason, ?int $adminId = null): int
    {
        $key = 'admin:refund:'.$user->id.':'.uniqid('', true);

        return $this->grant($user, $amount, $key, 'refund', [
            'reason' => $reason,
            'admin_id' => $adminId,
            'manual' => true,
        ]);
    }

    private function grant(User $user, int $amount, string $key, string $type, array $metadata): int
    {
        $this->initialize($user);

        return DB::transaction(function () use ($user, $amount, $key, $type, $metadata): int {
            $account = $this->lockedAccount($user);
            if ($account->transactions()->where('idempotency_key', $key)->exists()) {
                return 0;
            }

            $account->increment('balance', $amount);
            $this->record($account->fresh(), null, $type, $amount, $key, $metadata);

            return $amount;
        });
    }

    private function record(CreditAccount $account, ?Generation $generation, string $type, int $amount, string $key, ?array $metadata = null): void
    {
        $account->transactions()->create(['user_id' => $generation?->user_id ?? $account->user_id, 'generation_id' => $generation?->id, 'type' => $type, 'amount' => $amount, 'balance_after' => $account->balance, 'idempotency_key' => $key, 'metadata' => $metadata]);
    }
}
