<?php

namespace App\Domains\Editing\Services;

use InvalidArgumentException;

/**
 * Builds the instruction prompt sent next to the source photo.
 *
 * Every prompt ends with the same guardrail line so the model keeps the
 * subject intact across all four operations: no repositioning, no new
 * objects, no text/watermark artefacts. Expand is the only prompt that
 * carries the requested aspect ratio, because the framing lives in text
 * (the generateContent call has no imageConfig).
 */
class EditPromptBuilder
{
    private const GUARD = 'Keep the main subject fully visible and unchanged in position, colour and proportions. Photorealistic result with consistent lighting and focus. No new objects, no text, no watermark, no logos.';

    public function build(string $operation, array $options = []): string
    {
        return match ($operation) {
            'remove_bg' => $this->removeBg($options),
            'upscale' => $this->upscale($options),
            'expand' => $this->expand($options),
            'shadow' => $this->shadow($options),
            default => throw new InvalidArgumentException("عملیات ویرایش پشتیبانی نمی‌شود: {$operation}"),
        };
    }

    private function removeBg(array $options): string
    {
        // Transparent is the default export for compositing; white is the
        // standard e-commerce catalogue backdrop (Amazon/Shopify style).
        if (($options['background'] ?? 'transparent') === 'white') {
            return 'Remove the background completely and place the subject on a pure white (#FFFFFF) seamless studio backdrop with a very soft natural shadow directly under the subject, standard e-commerce catalogue style. '.self::GUARD;
        }

        return 'Remove the background completely and keep only the main subject with clean, precise edges on a fully transparent background. '.self::GUARD;
    }

    private function upscale(array $options): string
    {
        $frame = match ($options['target'] ?? 'hd') {
            '2k' => '2K (2048 px on the long edge)',
            '4k' => '4K (4096 px on the long edge)',
            default => 'HD (1024 px on the long edge)',
        };

        return "Increase the resolution and fine detail of this image to {$frame} without changing the composition, colours or content. ".self::GUARD;
    }

    private function expand(array $options): string
    {
        $ratio = str_replace(':', 'x', (string) ($options['aspect_ratio'] ?? '4:5'));

        return "Extend the canvas to a {$ratio} aspect ratio by continuing the scene naturally around the subject. Do not crop, move or resize the product, and keep its original proportions and lighting. ".self::GUARD;
    }

    private function shadow(array $options): string
    {
        if (($options['effect'] ?? 'shadow') === 'reflection') {
            return 'Add a photorealistic subtle reflection of the product on a glossy surface beneath it, fading with distance. '.self::GUARD;
        }

        return 'Add a natural photorealistic grounding shadow beneath the product on a clean surface, matching the existing light direction. '.self::GUARD;
    }
}
