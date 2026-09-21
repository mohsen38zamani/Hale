<?php

namespace App\Domains\Generations\Services;

use App\Domains\Credits\Exceptions\PlanLimitReached;
use App\Domains\Credits\Services\CreditEstimator;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Credits\Services\PlanLimitService;
use App\Domains\Generations\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RetryGeneration
{
    private const MAX_MANUAL_RETRIES = 2;

    public function __construct(
        private readonly CreditEstimator $estimator,
        private readonly CreditService $credits,
        private readonly PlanLimitService $limits,
    ) {}

    /** @throws PlanLimitReached */
    public function execute(User $user, int $generationId): Generation
    {
        return DB::transaction(function () use ($user, $generationId): Generation {
            $generation = Generation::query()->whereKey($generationId)->lockForUpdate()->firstOrFail();
            if ($generation->user_id !== $user->id) {
                abort(404);
            }
            if ($generation->status !== 'failed') {
                throw new ConflictHttpException('فقط تولید ناموفق قابل تلاش مجدد است.');
            }
            if ($generation->manual_retry_count >= self::MAX_MANUAL_RETRIES) {
                throw new ConflictHttpException('حداکثر تعداد تلاش مجدد برای این تولید انجام شده است.');
            }

            $this->limits->ensureCanGenerateLocked($user, $generation->type);
            $this->credits->reserve($user, $generation, $this->estimator->estimate($generation->type, $generation->creativeProject->video_duration_seconds));
            $generation->update([
                'status' => 'queued',
                'error_message' => null,
                'manual_retry_count' => $generation->manual_retry_count + 1,
            ]);

            return $generation->fresh();
        });
    }
}
