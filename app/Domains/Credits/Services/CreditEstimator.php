<?php

namespace App\Domains\Credits\Services;

class CreditEstimator
{
    public function estimate(string $type, ?int $durationSeconds = null, string $quality = 'standard'): int
    {
        if ($type === 'image') {
            return (int) config("credits.costs.image.{$quality}", config('credits.costs.image.standard'));
        }

        $duration = max(5, $durationSeconds ?? 5);

        return (int) config('credits.costs.video.base') + ($duration * (int) config('credits.costs.video.per_second'));
    }
}
