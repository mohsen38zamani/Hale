<?php

namespace App\Domains\Credits\Services;

use App\Domains\Admin\Models\SystemSetting;

class CreditEstimator
{
    /**
     * Estimated cost for the in-app AI generation track (Track 1).
     *
     * Rates are read from dynamic system settings when customized by the
     * admin, falling back to static config values.
     */
    public function estimate(string $type, ?int $durationSeconds = null, string $quality = 'standard'): int
    {
        if ($type === 'text') {
            return (int) SystemSetting::get('credit_cost_text', config('credits.costs.text', 3));
        }

        if ($type === 'image') {
            $default = (int) config("credits.costs.image.{$quality}", $quality === 'premium' ? 25 : 10);

            return (int) SystemSetting::get("credit_cost_image_{$quality}", $default);
        }

        $duration = max(5, $durationSeconds ?? 5);
        $base = (int) SystemSetting::get('credit_cost_video_base', config('credits.costs.video.base', 20));
        $perSecond = (int) SystemSetting::get('credit_cost_video_per_second', config('credits.costs.video.per_second', 5));

        return $base + ($duration * $perSecond);
    }

    /**
     * Estimated cost for the prompt-only engineering track (Track 2, spec item 13).
     *
     * - video direction prompt: 5 credits (SystemSetting credit_cost_prompt_video)
     * - trade-specific image prompt (gold, watches, etc.): 2 credits (SystemSetting credit_cost_prompt_image)
     * - simple/generic prompt: 1 credit (SystemSetting credit_cost_prompt_simple)
     */
    public function estimatePrompt(string $type = 'image', bool $hasTrade = false): int
    {
        if ($type === 'video') {
            return (int) SystemSetting::get(
                'credit_cost_prompt_video',
                config('credits.costs.prompt.video', 5),
            );
        }

        if ($type === 'image' && $hasTrade) {
            return (int) SystemSetting::get(
                'credit_cost_prompt_image',
                config('credits.costs.prompt.image', 2),
            );
        }

        return (int) SystemSetting::get(
            'credit_cost_prompt_simple',
            config('credits.costs.prompt.simple', 1),
        );
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
