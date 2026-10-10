<?php

namespace App\Domains\Creative\Prompts;

use App\Domains\Creative\Enums\CreativeFormat;

/**
 * Stable Diffusion: two blocks, weighted the way the model expects.
 *
 * The positive prompt stacks phrases with `(tag:1.x)` weights - the scene
 * facts that must survive carry more weight than the generic quality tags -
 * and the negative prompt says what must never appear. That negative list is
 * built from the same English keywords the studio uses to decide whether the
 * text suppression clause may relax, so both ends of the prompt can never
 * disagree about what counts as rendered wording: a brief that asks for its
 * own wording gets it in the positive block and is spared from the negative
 * one.
 */
class StableDiffusionPromptCompiler extends AbstractPromptCompiler
{
    public function key(): string
    {
        return 'stable_diffusion';
    }

    public function compile(array $brief, CreativeFormat $format): string
    {
        $positive = [
            '(masterpiece:1.2)',
            '(photorealistic:1.1)',
            $this->product($brief),
            (string) ($brief['visual_direction'] ?? ''),
            (string) ($brief['environment'] ?? '').' environment',
        ];

        foreach ($this->clauses->sceneParts($brief) as $part) {
            $positive[] = $this->weighted(rtrim($part, '. '));
        }

        $positive[] = '(sharp product detail:1.1)';
        $positive[] = '(accurate product packaging:1.1)';

        foreach ([
            PromptClauses::body($this->clauses->camera($brief)),
            PromptClauses::body($this->clauses->custom($brief)),
            PromptClauses::body($this->clauses->brand(is_array($brief['brand'] ?? null) ? $brief['brand'] : [])),
        ] as $clause) {
            $trimmed = rtrim($clause, '. ');
            if ($trimmed !== '') {
                $positive[] = $this->weighted($trimmed);
            }
        }

        $positive[] = rtrim($format->frame(), '.');

        // The sampler reads `(...:1.x)` by hunting for its closing bracket, so
        // a parenthesis inside the phrase - a user's "(limited edition)", the
        // old camera note - closed the weight early and left the whole prompt
        // unbalanced.
        $renderedWording = $this->clauses->requestsRenderedText($brief);

        // Banning `text` while the positive prompt promises to render the
        // brand's own wording would make the two halves cancel each other; a
        // brief that asked for wording only keeps the watermark and a broken
        // product out of the frame.
        $negative = $renderedWording
            ? ['(watermark:1.1)', '(misshapen product:1.1)']
            : ['(text:1.2)', '(watermark:1.1)', '(logo:1.1)', '(misshapen product:1.1)', '(blurry label:1.0)'];

        if (! $renderedWording) {
            foreach ($this->negativeKeywords() as $keyword) {
                // Already asserted above, at the weight that matters most.
                if ($keyword === 'text') {
                    continue;
                }

                $negative[] = sprintf('(%s:1.1)', $keyword);
            }
        }

        return 'Positive prompt: '.implode(', ', $positive)."\n"
            .'Negative prompt: '.implode(', ', array_unique($negative));
    }

    private function weighted(string $phrase, string $weight = '1.1'): string
    {
        return sprintf('(%s:%s)', str_replace(['(', ')'], '', $phrase), $weight);
    }

    /**
     * The English half of the keyword catalogue: a negative prompt written in
     * Persian would be ignored by the sampler, and dropping the Persian ones
     * silently is better than pretending they apply.
     *
     * @return list<string>
     */
    private function negativeKeywords(): array
    {
        $keywords = [];

        foreach ((array) config('creative.text_suppression.request_keywords', []) as $keyword) {
            $keyword = (string) $keyword;

            if (preg_match('/^[a-z ]+$/u', $keyword) === 1) {
                $keywords[] = $keyword;
            }
        }

        return $keywords;
    }
}
