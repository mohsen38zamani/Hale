<?php

namespace App\Domains\Credits\Services;

class CreditEstimator
{
    public function estimate(string $type, ?int $durationSeconds = null, string $quality = 'standard'): int
    {
        if ($type === 'image') {
            return (int) config("credits.costs.image.{$quality}", config('credits.costs.image.standard'));
        }

        return (int) config('credits.costs.video.base') + ((int) $durationSeconds * (int) config('credits.costs.video.per_second'));
    }
}
