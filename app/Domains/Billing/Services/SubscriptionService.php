<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Notifications\Notifications\SubscriptionExpiredNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function syncExpired(User $user): void
    {
        DB::transaction(fn () => $this->expireForUser($user));
    }

    public function expireAll(): int
    {
        $expired = 0;

        Subscription::query()
            ->where('status', 'active')
            ->where('ends_at', '<=', now())
            ->chunkById(100, function ($subscriptions) use (&$expired): void {
                foreach ($subscriptions as $subscription) {
                    $user = User::query()->find($subscription->user_id);
                    if ($user === null) {
                        continue;
                    }

                    DB::transaction(function () use ($user, &$expired): void {
                        $expired += $this->expireForUser($user);
                    });
                }
            });

        return $expired;
    }

    private function expireForUser(User $user): int
    {
        $expired = $user->subscriptions()
            ->where('status', 'active')
            ->where('ends_at', '<=', now())
            ->lockForUpdate()
            ->get();

        $expiredCount = $expired->count();
        if ($expiredCount > 0) {
            $expired->each->update(['status' => 'expired']);
        }

        $active = $user->subscriptions()
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->latest('ends_at')
            ->first();

        $oldPlan = $user->plan_key;
        $newPlan = $active?->plan_key ?? 'free';

        if ($oldPlan !== $newPlan) {
            $user->update(['plan_key' => $newPlan]);

            if (! empty($oldPlan) && $oldPlan !== 'free' && $newPlan === 'free') {
                try {
                    $user->notify(new SubscriptionExpiredNotification($oldPlan));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return $expiredCount;
    }

    public function active(User $user): ?Subscription
    {
        $this->syncExpired($user);

        return $user->subscriptions()->where('status', 'active')->latest('ends_at')->first();
    }
}
