<?php

namespace App\Domains\Creative\Prompts;

use App\Domains\Creative\Contracts\PromptCompilerInterface;
use App\Domains\Creative\Enums\CreativeFormat;

/**
 * The studio's default prompt: the sentence Hale has always sent.
 *
 * Every other compiler reshapes the same clauses; this one assembles them in
 * the original order, so a brief without a target (or with an unknown one)
 * produces exactly the prompt the engine itself writes.
 *
 * It is also the prompt the browser mirrors byte for byte in updateCanvasState,
 * so the frame line, the labels and the product description have to be built
 * identically on both sides or the inspector would quote one prompt while the
 * engine sends another.
 */
class GenericPromptCompiler implements PromptCompilerInterface
{
    public function __construct(private readonly PromptClauses $clauses = new PromptClauses) {}

    public function key(): string
    {
        return 'generic';
    }

    public function compile(array $brief, CreativeFormat $format): string
    {
        $customPromptPart = $this->clauses->custom($brief);
        $brandPart = $this->clauses->brand(is_array($brief['brand'] ?? null) ? $brief['brand'] : []);
        $campaignPart = $this->clauses->campaign(is_array($brief['campaign'] ?? null) ? $brief['campaign'] : []);

        // The product's own description is the only sentence that says what the
        // product looks like, so it belongs next to its name rather than being
        // left to the compilers that happen to reach for it.
        $description = $this->clauses->sanitize((string) ($brief['description'] ?? ''));
        $descriptionPart = $description === '' ? '' : ' '.$description.'.';

        $base = sprintf(
            'Create a professional commercial advertising visual for %s.%s%s%s Objective: %s. Style: %s. Environment: %s. %s%s%s%s%s High-end commercial production, photorealistic, cinematic lighting, ultra-sharp detail, preserve original product design and packaging, no distracting watermarks, %s.',
            $brief['product'],
            $descriptionPart,
            $this->clauses->camera($brief),
            $this->clauses->character($brief),
            $brief['objective'],
            $brief['visual_direction'],
            $brief['environment'],
            $format->frame(),
            $this->clauses->scene($brief),
            $customPromptPart,
            $brandPart,
            $campaignPart,
            $this->clauses->textSuppression($brief)
        );

        if ($format->type() === 'video') {
            $base .= sprintf(
                ' Dynamic motion: %s, fluid atmospheric movement, premium brand reel aesthetic, 4K render.',
                $this->clauses->cameraMotion($brief)
            );
        }

        return $base;
    }
}
