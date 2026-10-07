<?php

namespace App\Domains\Creative\Prompts;

use App\Domains\Creative\Enums\CreativeFormat;

/**
 * Imagen: a photograph described in photographic terms.
 *
 * The model reads specs, not slogans, so the marketing tail of the generic
 * prompt is replaced by lens, light and framing language: 85mm at f/4,
 * three-point lighting, no wide-angle distortion of the packaging. The scene
 * clauses still arrive untouched underneath them.
 */
class ImagenPromptCompiler extends AbstractPromptCompiler
{
    public function key(): string
    {
        return 'imagen';
    }

    public function compile(array $brief, CreativeFormat $format): string
    {
        $product = $this->product($brief);
        $description = $this->description($brief);

        $parts = [
            sprintf(
                '%s%s, commercial product photograph, studio-grade capture.',
                $product,
                $description !== '' ? ' ('.$description.')' : ''
            ),
            'Shot on an 85mm lens at f/4 with three-point lighting: key light, soft fill, and a rim separating the product from the background.',
            'Original product design and packaging are preserved exactly as supplied.',
            'No stylised rendering, no heavy retouch, no wide-angle distortion of the packaging.',
        ];

        foreach ([
            $this->clauses->camera($brief),
            $this->clauses->character($brief),
            $this->clauses->scene($brief),
            $this->clauses->custom($brief),
            $this->clauses->brand(is_array($brief['brand'] ?? null) ? $brief['brand'] : []),
        ] as $clause) {
            $trimmed = trim($clause);
            if ($trimmed !== '') {
                $parts[] = $trimmed;
            }
        }

        $parts[] = sprintf(
            '%s styling in a %s environment, composed for %s.',
            (string) ($brief['visual_direction'] ?? ''),
            (string) ($brief['environment'] ?? ''),
            (string) ($brief['objective'] ?? '')
        );
        $parts[] = sprintf('Composition: %s ratio (%s).', $format->aspectRatio(), $format->value);
        $parts[] = rtrim(trim($this->clauses->textSuppression($brief)), '.').'.';

        return implode(' ', $parts);
    }
}
