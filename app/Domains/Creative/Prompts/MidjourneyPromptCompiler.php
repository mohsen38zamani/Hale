<?php

namespace App\Domains\Creative\Prompts;

use App\Domains\Creative\Enums\CreativeFormat;

/**
 * Midjourney: phrases instead of sentences, parameters instead of policy.
 *
 * Everything the studio decided is compressed into comma-separated phrases
 * (the way the model is actually prompted) and the constraints move to the
 * tail: `--ar` carries the selected format, `--style raw` keeps the scene
 * literal, `--v 6.1` pins the version and `--no` says in Midjourney's own
 * grammar what the text suppression clause says everywhere else.
 */
class MidjourneyPromptCompiler extends AbstractPromptCompiler
{
    public function key(): string
    {
        return 'midjourney';
    }

    public function compile(array $brief, CreativeFormat $format): string
    {
        $product = $this->product($brief);
        $description = $this->description($brief);

        $phrases = [
            $product,
            $description,
            (string) ($brief['visual_direction'] ?? '').' product photography',
            (string) ($brief['environment'] ?? '').' setting',
            $this->phrase($this->clauses->scene($brief)),
            $this->phrase($this->clauses->camera($brief)),
            $this->phrase($this->clauses->character($brief)),
            $this->phrase($this->clauses->custom($brief)),
            $this->phrase($this->clauses->brand(is_array($brief['brand'] ?? null) ? $brief['brand'] : [])),
            'accurate product packaging, photorealistic commercial composition',
        ];

        $body = implode(', ', array_values(array_filter(
            $phrases,
            fn (string $phrase): bool => trim($phrase) !== ''
        )));

        return $body
            .' --ar '.$format->aspectRatio()
            .' --style raw --v 6.1'
            .' --no text, watermark';
    }

    /**
     * A clause is written as a sentence; Midjourney wants a phrase, so the
     * trailing period and any directive label go away.
     */
    private function phrase(string $clause): string
    {
        $trimmed = trim($clause);
        $unlabelled = preg_replace('/^(Camera angle|Character consistency) \([a-z]+\): /', '', $trimmed) ?? $trimmed;

        return rtrim($unlabelled, ". \t");
    }
}
