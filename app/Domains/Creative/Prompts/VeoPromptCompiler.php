<?php

namespace App\Domains\Creative\Prompts;

use App\Domains\Creative\Enums\CreativeFormat;

/**
 * Veo: the camera move opens the prompt, then the world it moves through.
 *
 * Veo front-loads motion - a movement written at the end is read as an
 * afterthought - so the compiler leads with the movement the chosen angle
 * implies and only then introduces the scene. The product holds still while
 * the atmosphere moves, which is the whole trick of a product reel, and the
 * text suppression policy still closes the prompt.
 */
class VeoPromptCompiler extends AbstractPromptCompiler
{
    public function key(): string
    {
        return 'veo';
    }

    public function compile(array $brief, CreativeFormat $format): string
    {
        $product = $this->product($brief);
        $description = $this->description($brief);

        // Front-loaded: movement first, always.
        $parts = [ucfirst(trim($this->clauses->cameraMotion($brief))).'.'];

        foreach ([
            $this->clauses->camera($brief),
            $this->clauses->character($brief),
            $this->clauses->scene($brief),
            $this->clauses->custom($brief),
        ] as $clause) {
            $trimmed = trim($clause);
            if ($trimmed !== '') {
                $parts[] = $trimmed;
            }
        }

        $parts[] = sprintf(
            '%s%s holds the frame in a %s setting, %s direction.',
            $product,
            $description !== '' ? ' ('.$description.')' : '',
            (string) ($brief['environment'] ?? ''),
            (string) ($brief['visual_direction'] ?? '')
        );
        $parts[] = 'The subject stays still while the atmosphere moves.';

        foreach ([
            $this->clauses->brand(is_array($brief['brand'] ?? null) ? $brief['brand'] : []),
            $this->clauses->campaign(is_array($brief['campaign'] ?? null) ? $brief['campaign'] : []),
        ] as $clause) {
            $trimmed = trim($clause);
            if ($trimmed !== '') {
                $parts[] = $trimmed;
            }
        }

        $parts[] = sprintf('Composition: %s ratio (%s).', $format->aspectRatio(), $format->value);
        $parts[] = rtrim(trim($this->clauses->textSuppression($brief)), '.').'.';

        return implode(' ', $parts);
    }
}
