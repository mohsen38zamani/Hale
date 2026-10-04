<?php

namespace App\Domains\Credits\Services;

use App\Domains\Billing\Services\SubscriptionService;
use App\Domains\Credits\Exceptions\PlanLimitReached;
use App\Models\User;
use Illuminate\Support\Carbon;

class PlanLimitService
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function ensureCanGenerate(User $user, string $type): void
    {
        $this->subscriptions->syncExpired($user);

        $this->assertCanGenerate($user, $type);
    }

    public function ensureCanGenerateLocked(User $user, string $type): void
    {
        $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
        $this->subscriptions->syncExpired($lockedUser);
        $this->assertCanGenerate($lockedUser, $type);
    }

    private function assertCanGenerate(User $user, string $type): void
    {
        $plan = config('plans.'.($user->plan_key ?: 'free'));
        $limit = (int) ($plan[$type.'_limit'] ?? 0);

        $since = $this->windowStart($user);

        $used = $this->countSince($user, $type, $since);

        if ($used >= $limit) {
            $label = $type === 'video' ? 'ویدئوی' : 'تصویر';
            throw new PlanLimitReached("محدودیت تولید {$label} پلن شما به پایان رسیده است.");
        }
    }

    /**
     * Monthly (or subscription-scoped) usage of every limited output type,
     * exposed on the profile endpoint so the UI can show used/limit quota.
     *
     * Callers that already synced subscriptions and resolved the window
     * (like the profile endpoint) should pass `$since` so the profile
     * stays inside its query-audit budget: both counts share one grouped
     * query and no subscription lookup runs here.
     *
     * @return array<string, array{used: int, limit: int, remaining: int}>
     */
    public function usage(User $user, ?Carbon $since = null): array
    {
        $plan = config('plans.'.($user->plan_key ?: 'free'));
        $since ??= $this->windowStart($user);

        $counts = $user->generations()
            ->selectRaw('type, COUNT(*) as aggregate')
            ->whereIn('status', ['queued', 'processing', 'completed'])
            ->where('created_at', '>=', $since)
            ->groupBy('type')
            ->pluck('aggregate', 'type');

        $usage = [];
        foreach (['image', 'video'] as $type) {
            $limit = (int) ($plan[$type.'_limit'] ?? 0);
            $used = (int) ($counts[$type] ?? 0);
            $usage[$type] = [
                'used' => $used,
                'limit' => $limit,
                'remaining' => max(0, $limit - $used),
            ];
        }

        return $usage;
    }

    private function windowStart(User $user): Carbon
    {
        $activeSubscription = $user->subscriptions()
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->latest('ends_at')
            ->first();

        return $activeSubscription?->starts_at ?? Carbon::now()->startOfMonth();
    }

    private function countSince(User $user, string $type, Carbon $since): int
    {
        return $user->generations()
            ->where('type', $type)
            ->whereIn('status', ['queued', 'processing', 'completed'])
            ->where('created_at', '>=', $since)
            ->count();
    }

    public function syncExpired(User $user): void
    {
        $this->subscriptions->syncExpired($user);
    }
}
