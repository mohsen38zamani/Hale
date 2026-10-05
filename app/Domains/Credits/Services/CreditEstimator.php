<?php

namespace App\Domains\Credits\Services;

class CreditEstimator
{
    public function estimate(string $type, ?int $durationSeconds = null, string $quality = 'standard'): int
    {
        if ($type === 'text') {
            return (int) config('credits.costs.text', 3);
        }

        if ($type === 'image') {
            return (int) config("credits.costs.image.{$quality}", config('credits.costs.image.standard'));
        }

        $duration = max(5, $durationSeconds ?? 5);

        return (int) config('credits.costs.video.base') + ($duration * (int) config('credits.costs.video.per_second'));
    }

    /**
     * One-shot cost of an AI utility tool run (charged upfront).
     *
     * upscale is priced per target resolution, the other operations are
     * flat. Unknown operations fall back to the cheapest tier.
     */
    public function estimateEdit(string $operation, ?string $target = null): int
    {
        if ($operation === 'upscale') {
            return (int) config(
                'credits.costs.edit.upscale.'.($target ?: 'hd'),
                (int) config('credits.costs.edit.upscale.hd', 5),
            );
        }

        return (int) config("credits.costs.edit.{$operation}", 5);
    }
}
