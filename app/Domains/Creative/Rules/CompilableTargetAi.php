<?php

namespace App\Domains\Creative\Rules;

use App\Domains\Creative\Enums\CreativeFormat;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The generation gate for a chosen target: our pipeline only builds what our
 * own providers can build, and only when the format suits it.
 *
 * Two failures, both explained in Persian: a `copy` target (Midjourney,
 * ChatGPT, Flux, ...) has no in-house service behind it, so the prompt is
 * compiled for the user to paste elsewhere - and a target that only serves
 * images cannot answer a video request. Preview stays permissive on purpose:
 * the inspector must be able to quote a prompt the user cannot generate yet.
 */
class CompilableTargetAi implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $key = is_string($value) ? trim($value) : '';
        if ($key === '') {
            return;
        }

        $target = config("creative.target_ais.{$key}");
        if (! is_array($target)) {
            return; // The `in` rule reports the unknown key.
        }

        $label = (string) ($target['label'] ?? $key);

        if (($target['mode'] ?? 'generate') !== 'generate') {
            $fail(sprintf(
                'تولید با «%s» از این استودیو انجام نمی‌شود؛ پرامپت این مدل آمادهٔ کپی است و باید در همان ابزار اجرا شود.',
                $label
            ));

            return;
        }

        // An invalid or missing format is reported by its own rule; there is
        // nothing to compare the target's types against yet.
        $format = CreativeFormat::tryFrom((string) ($this->data['format'] ?? ''));
        if ($format === null) {
            return;
        }

        $types = array_values((array) ($target['types'] ?? []));
        if ($types === [] || in_array($format->type(), $types, true)) {
            return;
        }

        $labels = ['image' => 'عکس', 'video' => 'ویدیو'];

        $fail(sprintf(
            '«%s» فقط %s می‌سازد و با قالب انتخاب‌شده نمی‌خواند.',
            $label,
            implode(' و ', array_map(fn (string $type): string => $labels[$type] ?? $type, $types))
        ));
    }
}
