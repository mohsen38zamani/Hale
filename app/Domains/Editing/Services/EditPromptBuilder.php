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
 *
 * `custom` is the exception and has to be: its guard protects only what
 * the user did not ask about, because a guard that says "keep the subject
 * unchanged in position" would veto "turn the bottle to face left".
 */
class EditPromptBuilder
{
    private const GUARD = 'Keep the main subject fully visible and unchanged in position, colour and proportions. Photorealistic result with consistent lighting and focus. No new objects, no text, no watermark, no logos.';

    /**
     * What a variant is allowed to change.
     *
     * Three named axes rather than "the model will randomize": an identical
     * prompt asked three times is three chances at the same picture, and
     * nothing in the API promises otherwise. Naming the axis makes each take
     * a request Hale can be held to, and lets the costs endpoint say what it
     * is selling instead of hoping.
     */
    public const TAKES = [
        ['key' => 'angle', 'label' => 'زاویهٔ دوربین', 'clause' => 'the camera angle, framing the same product from a different direction'],
        ['key' => 'surface', 'label' => 'صحنه و سطح', 'clause' => 'the setting and the surface the product rests on'],
        ['key' => 'light', 'label' => 'حس نور', 'clause' => 'the mood and direction of the light, keeping the product colours true'],
    ];

    /**
     * The takes, minus their English clauses, for the API to expose.
     *
     * @return list<array{key: string, label: string}>
     */
    public static function takes(): array
    {
        return array_map(
            static fn (array $take): array => ['key' => $take['key'], 'label' => $take['label']],
            self::TAKES,
        );
    }

    public function build(string $operation, array $options = []): string
    {
        return match ($operation) {
            'remove_bg' => $this->removeBg($options),
            'upscale' => $this->upscale($options),
            'expand' => $this->expand($options),
            'shadow' => $this->shadow($options),
            'custom' => $this->custom($options),
            'variant' => $this->variant($options),
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

    /**
     * A conversational instruction is the one prompt whose guard must not be
     * the tool guard: "keep the subject unchanged in position" would veto
     * "turn the bottle to face left". The user's words are the whole request,
     * so the guard only protects the rest of the frame from the model's
     * habit of redrawing what it was not asked to touch.
     */
    private function custom(array $options): string
    {
        $instruction = trim((string) ($options['message'] ?? ''));
        if ($instruction === '') {
            throw new InvalidArgumentException('پیام ویرایش سفارشی خالی است.');
        }

        return "{$instruction}. Keep everything you were not asked to change exactly as it is: same subject, same position, same colours, same lighting, same framing. Photorealistic result with consistent focus. No new objects, no text, no watermark, no logos.";
    }

    /**
     * One named take of the three above. The product is protected absolutely
     * - a variant that changes the packaging has not varied the image, it has
     * replaced the thing being advertised.
     */
    private function variant(array $options): string
    {
        $take = self::TAKES[(int) ($options['take_index'] ?? 0)] ?? self::TAKES[0];

        return "Make a fresh creative take of this product image by changing only {$take['clause']}. The product itself - its shape, colour, packaging, logo and proportions - must stay exactly as it is. Photorealistic result with consistent focus. No text, no watermark, no logos.";
    }
}
