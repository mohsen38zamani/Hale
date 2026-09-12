<?php

namespace App\Domains\Billing\Services;

use App\Domains\Billing\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function syncExpired(User $user): void
    {
        $subscription = $user->subscriptions()
            ->where('status', 'active')
            ->where('ends_at', '<', now())
            ->latest('ends_at')
            ->first();

        if ($subscription === null) {
            return;
        }

        DB::transaction(function () use ($user, $subscription): void {
            $subscription->update(['status' => 'expired']);
            $user->update(['plan_key' => 'free']);
        });
    }

    public function active(User $user): ?Subscription
    {
        $this->syncExpired($user);

        return $user->subscriptions()->where('status', 'active')->latest('ends_at')->first();
    }
}
