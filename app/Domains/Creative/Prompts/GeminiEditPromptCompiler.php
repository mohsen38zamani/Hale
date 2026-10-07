<?php

namespace App\Domains\Creative\Prompts;

use App\Domains\Creative\Enums\CreativeFormat;

/**
 * Gemini Edit: a differential prompt - what must survive, and what changes.
 *
 * An edit model is not asked to imagine a picture, it is asked to change one
 * part of it, so the compiler splits the brief in two: KEEP names the product
 * (shape, materials, label artwork) that may not move a pixel, CHANGE names
 * the new surroundings the scene clauses describe. The closing paragraph is
 * the paste-ready form of the same instruction, so the two halves can never
 * drift apart.
 */
class GeminiEditPromptCompiler extends AbstractPromptCompiler
{
    public function key(): string
    {
        return 'gemini_edit';
    }

    public function compile(array $brief, CreativeFormat $format): string
    {
        $product = $this->product($brief);
        $description = $this->description($brief);
        $scene = $this->clauses->sceneParts($brief);

        $lines = [
            'EDIT INSTRUCTION',
            'Swap the surroundings for the target scene; the product stays exactly as photographed.',
            '',
            'KEEP - must not change',
            '- '.$product.($description !== '' ? ' ('.$description.')' : '').': shape, materials, label artwork and colors, as supplied.',
            '- Placement: the product keeps its position and scale in the '.$format->aspectRatio().' frame.',
        ];

        $angle = trim($this->clauses->camera($brief));
        if ($angle !== '') {
            $lines[] = '- '.$angle.' The framing must be preserved.';
        }

        $character = trim($this->clauses->character($brief));
        if ($character !== '') {
            $lines[] = '- '.$character;
        }

        $lines[] = '';
        $lines[] = 'CHANGE - the new scene';
        $lines[] = '- Environment: '.(string) ($brief['environment'] ?? '');
        $lines[] = '- Direction: '.(string) ($brief['visual_direction'] ?? '');

        foreach ($scene as $part) {
            $lines[] = '- '.$part;
        }

        $custom = trim($this->clauses->custom($brief));
        if ($custom !== '') {
            $lines[] = '- '.$custom;
        }

        $brand = trim($this->clauses->brand(is_array($brief['brand'] ?? null) ? $brief['brand'] : []));
        if ($brand !== '') {
            $lines[] = '- '.$brand;
        }

        $lines[] = '';
        $lines[] = 'PROMPT (paste)';

        $target = implode(', ', array_values(array_filter([
            rtrim(trim($this->clauses->scene($brief)), '.'),
            rtrim(trim($custom), '.'),
        ], fn (string $value): bool => $value !== '')));

        $lines[] = sprintf(
            '%s stays untouched while the surroundings become%s. %s setting, %s direction. Composition: %s ratio (%s). %s.',
            $product,
            $target !== '' ? ': '.$target : ' unrecognisably different',
            (string) ($brief['environment'] ?? ''),
            (string) ($brief['visual_direction'] ?? ''),
            $format->aspectRatio(),
            $format->value,
            rtrim(trim($this->clauses->textSuppression($brief)), '.')
        );

        return implode("\n", $lines);
    }
}
