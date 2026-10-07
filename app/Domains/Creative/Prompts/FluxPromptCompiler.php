<?php

namespace App\Domains\Creative\Prompts;

use App\Domains\Creative\Enums\CreativeFormat;

/**
 * FLUX: one literal, linear description of a physical scene.
 *
 * No labels, no marketing adjectives, no stacked slogans: what the material
 * is, where the light comes from, what the perspective does. FLUX renders
 * what it can verify in the sentence, so the sentence stays observable -
 * which is also why the generic "high-end commercial production" tail is
 * gone: it describes an ambition, not a frame.
 */
class FluxPromptCompiler extends AbstractPromptCompiler
{
    public function key(): string
    {
        return 'flux';
    }

    public function compile(array $brief, CreativeFormat $format): string
    {
        $product = $this->product($brief);
        $description = $this->description($brief);

        $segments = [
            sprintf(
                '%s%s is the single subject of the frame.',
                $product,
                $description !== '' ? ', '.$description : ''
            ),
            'The original product design and packaging are preserved exactly as supplied.',
            trim($this->clauses->scene($brief)),
            trim($this->clauses->camera($brief)),
            trim($this->clauses->custom($brief)),
            sprintf(
                'The scene serves %s in a %s direction, inside a %s setting.',
                (string) ($brief['objective'] ?? ''),
                (string) ($brief['visual_direction'] ?? ''),
                (string) ($brief['environment'] ?? '')
            ),
            trim($this->clauses->character($brief)),
            trim($this->clauses->brand(is_array($brief['brand'] ?? null) ? $brief['brand'] : [])),
            trim($this->clauses->campaign(is_array($brief['campaign'] ?? null) ? $brief['campaign'] : [])),
            sprintf('A %s ratio frame (%s).', $format->aspectRatio(), $format->value),
            trim($this->clauses->textSuppression($brief)).'.',
        ];

        return implode(' ', array_values(array_filter(
            $segments,
            fn (string $segment): bool => trim($segment) !== ''
        )));
    }
}
