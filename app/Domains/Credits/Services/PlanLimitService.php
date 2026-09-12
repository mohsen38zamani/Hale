<?php

namespace App\Domains\Credits\Services;

use App\Domains\Credits\Exceptions\PlanLimitReached;
use App\Domains\Billing\Services\SubscriptionService;
use App\Models\User;
use Illuminate\Support\Carbon;

class PlanLimitService
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function ensureCanGenerate(User $user, string $type): void
    {
        $this->subscriptions->syncExpired($user);

        $plan = config('plans.' . ($user->plan_key ?: 'free'));
        $limit = (int) ($plan[$type . '_limit'] ?? 0);
        $used = $user->generations()
            ->where('type', $type)
            ->whereIn('status', ['queued', 'processing', 'completed'])
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->count();

        if ($used >= $limit) {
            $label = $type === 'video' ? 'ویدئوی' : 'تصویر';
            throw new PlanLimitReached("محدودیت تولید {$label} پلن شما به پایان رسیده است.");
        }
    }

    public function syncExpired(User $user): void
    {
        $this->subscriptions->syncExpired($user);
    }
}
