<?php

namespace App\Domains\AI\Services;

use App\Domains\AI\Models\AiBudgetReservation;
use App\Domains\AI\Models\AiDailyBudget;
use App\Domains\Notifications\Notifications\AiBudgetAlertNotification;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CircuitBreaker
{
    public function isAvailable(): bool
    {
        $budget = (float) config('ai.daily_budget_usd', 50.0);
        if ($budget <= 0) {
            return true;
        }

        $dailyBudget = AiDailyBudget::query()
            ->whereDate('budget_date', now())
            ->first();
        $todayCost = $dailyBudget === null
            ? 0.0
            : (float) $dailyBudget->spent_usd + (float) $dailyBudget->reserved_usd;

        return $todayCost < $budget;
    }

    public function ensureAvailable(): void
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('سقف بودجه روزانه مصرف هوش مصنوعی تکمیل شده است.');
        }
    }

    public function reserve(int $generationId, float $estimatedUsd): void
    {
        if ($generationId < 1 || $estimatedUsd < 0) {
            throw new RuntimeException('رزرو بودجهٔ AI معتبر نیست.');
        }

        DB::transaction(function () use ($generationId, $estimatedUsd): void {
            $date = now()->toDateString();
            AiDailyBudget::query()->firstOrCreate(['budget_date' => $date]);
            $budget = AiDailyBudget::query()->where('budget_date', $date)->lockForUpdate()->firstOrFail();
            $existing = AiBudgetReservation::query()->where('generation_id', $generationId)->lockForUpdate()->first();
            if ($existing !== null) {
                return;
            }

            $limit = (float) config('ai.daily_budget_usd', 50.0);
            if ($limit > 0 && ((float) $budget->spent_usd + (float) $budget->reserved_usd + $estimatedUsd) > $limit) {
                throw new RuntimeException('سقف بودجه روزانه مصرف هوش مصنوعی تکمیل شده است.');
            }

            $budget->increment('reserved_usd', $estimatedUsd);
            AiBudgetReservation::query()->create([
                'generation_id' => $generationId,
                'ai_daily_budget_id' => $budget->id,
                'estimated_usd' => $estimatedUsd,
            ]);
        });

        $this->checkBudgetAlert();
    }

    public function settle(int $generationId, float $actualUsd): void
    {
        DB::transaction(function () use ($generationId, $actualUsd): void {
            $reservation = AiBudgetReservation::query()->where('generation_id', $generationId)->lockForUpdate()->first();
            if ($reservation === null || $reservation->settled_at !== null) {
                return;
            }
            $budget = AiDailyBudget::query()->whereKey($reservation->ai_daily_budget_id)->lockForUpdate()->firstOrFail();
            $budget->decrement('reserved_usd', (float) $reservation->estimated_usd);
            $budget->increment('spent_usd', max(0, $actualUsd));
            $reservation->update(['settled_at' => now()]);
        });
    }

    public function release(int $generationId): void
    {
        DB::transaction(function () use ($generationId): void {
            $reservation = AiBudgetReservation::query()->where('generation_id', $generationId)->lockForUpdate()->first();
            if ($reservation === null || $reservation->settled_at !== null) {
                return;
            }
            $budget = AiDailyBudget::query()->whereKey($reservation->ai_daily_budget_id)->lockForUpdate()->firstOrFail();
            $budget->decrement('reserved_usd', (float) $reservation->estimated_usd);
            $reservation->delete();
        });
    }

    public function checkBudgetAlert(float $thresholdPercentage = 80.0): bool
    {
        $limit = (float) config('ai.daily_budget_usd', 50.0);
        if ($limit <= 0) {
            return false;
        }

        $date = now()->toDateString();
        $dailyBudget = AiDailyBudget::query()
            ->whereDate('budget_date', $date)
            ->first();

        if ($dailyBudget === null) {
            return false;
        }

        $todayCost = (float) $dailyBudget->spent_usd + (float) $dailyBudget->reserved_usd;
        $usagePercentage = ($todayCost / $limit) * 100.0;

        if ($usagePercentage < $thresholdPercentage) {
            return false;
        }

        $cacheKey = "ai_daily_budget_alert_sent:{$date}";
        if (Cache::has($cacheKey)) {
            return false;
        }

        Cache::put($cacheKey, true, now()->endOfDay());

        $adminEmails = array_filter(array_map('trim', explode(',', (string) config('auth.admin_emails', ''))));
        if (! empty($adminEmails)) {
            $admins = User::query()->whereIn('email', $adminEmails)->get();
            foreach ($admins as $admin) {
                $admin->notify(new AiBudgetAlertNotification($todayCost, $limit, round($usagePercentage, 1)));
            }
        }

        return true;
    }
}
