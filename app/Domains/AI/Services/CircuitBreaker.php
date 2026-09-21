<?php

namespace App\Domains\AI\Services;

use App\Domains\Generations\Models\UsageLog;
use RuntimeException;

class CircuitBreaker
{
    public function isAvailable(): bool
    {
        $budget = (float) config('ai.daily_budget_usd', 50.0);
        if ($budget <= 0) {
            return true;
        }

        $todayCost = (float) UsageLog::query()
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('cost_usd');

        return $todayCost < $budget;
    }

    public function ensureAvailable(): void
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('سقف بودجه روزانه مصرف هوش مصنوعی تکمیل شده است.');
        }
    }
}
