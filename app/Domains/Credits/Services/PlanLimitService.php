<?php

namespace App\Domains\Credits\Services;

use App\Domains\Credits\Exceptions\PlanLimitReached;
use App\Models\User;
use Illuminate\Support\Carbon;

class PlanLimitService
{
    public function ensureCanGenerate(User $user, string $type): void
    {
        if ($type !== 'video') {
            return;
        }

        $plan = config('plans.' . $user->plan_key);
        $limit = (int) ($plan['video_limit'] ?? 0);
        $used = $user->generations()
            ->where('type', 'video')
            ->where('created_at', '>=', Carbon::now()->startOfMonth())
            ->count();

        if ($used >= $limit) {
            throw new PlanLimitReached('محدودیت تولید ویدئوی پلن شما به پایان رسیده است.');
        }
    }
}
