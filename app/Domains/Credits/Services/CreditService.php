<?php

namespace App\Domains\Credits\Services;

use App\Domains\Credits\Exceptions\InsufficientCredits;
use App\Domains\Credits\Models\CreditAccount;
use App\Domains\Generations\Models\Generation;
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
        DB::transaction(function () use ($user, $generation, $amount): void {
            $account = $this->lockedAccount($user);
            if ($account->balance < $amount) {
                throw new InsufficientCredits('اعتبار کافی نیست.');
            }

            $account->decrement('balance', $amount);
            $account->increment('reserved', $amount);
            $attempt = $account->transactions()->where('generation_id', $generation->id)->where('type', 'reserve')->count() + 1;
            $this->record($account->fresh(), $generation, 'reserve', -$amount, "generation:{$generation->id}:reserve:{$attempt}");
            $generation->update(['credits_reserved' => $amount]);
        });
    }

    public function settle(Generation $generation): void
    {
        DB::transaction(function () use ($generation): void {
            $generation->refresh();
            if ($generation->credits_charged > 0 || $generation->credits_reserved < 1) {
                return;
            }
            $account = $this->lockedAccount($generation->user);
            $amount = $generation->credits_reserved;
            $account->decrement('reserved', $amount);
            $account->increment('lifetime_used', $amount);
            $this->record($account->fresh(), $generation, 'charge', $amount, "generation:{$generation->id}:charge");
            $generation->update(['credits_charged' => $amount, 'credits_reserved' => 0]);
        });
    }

    public function refund(Generation $generation): void
    {
        DB::transaction(function () use ($generation): void {
            $generation->refresh();
            if ($generation->credits_reserved < 1) {
                return;
            }
            $account = $this->lockedAccount($generation->user);
            $amount = $generation->credits_reserved;
            $account->decrement('reserved', $amount);
            $account->increment('balance', $amount);
            $refundNumber = $account->transactions()->where('generation_id', $generation->id)->where('type', 'refund')->count() + 1;
            $this->record($account->fresh(), $generation, 'refund', $amount, "generation:{$generation->id}:refund:{$refundNumber}");
            $generation->update(['credits_reserved' => 0]);
        });
    }

    private function lockedAccount(User $user): CreditAccount
    {
        $this->account($user);

        return CreditAccount::query()->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
    }

    public function grantBonus(User $user, int $amount, string $key): int
    {
        $this->initialize($user);

        return DB::transaction(function () use ($user, $amount, $key): int {
            $account = $this->lockedAccount($user);
            if ($account->transactions()->where('idempotency_key', $key)->exists()) {
                return 0;
            }

            $account->increment('balance', $amount);
            $this->record($account->fresh(), null, 'bonus', $amount, $key, ['reason' => $key]);

            return $amount;
        });
    }

    private function record(CreditAccount $account, ?Generation $generation, string $type, int $amount, string $key, ?array $metadata = null): void
    {
        $account->transactions()->create(['user_id' => $generation?->user_id ?? $account->user_id, 'generation_id' => $generation?->id, 'type' => $type, 'amount' => $amount, 'balance_after' => $account->balance, 'idempotency_key' => $key, 'metadata' => $metadata]);
    }
}
