<?php

namespace App\Domains\Creative\Prompts;

use App\Domains\Creative\Contracts\PromptCompilerInterface;
use App\Domains\Creative\Enums\CreativeFormat;

/**
 * Base for the compilers that wrap the image prompt in their own structure.
 *
 * A target that works by *direction* (ChatGPT, Claude, DeepSeek, Grok) never
 * replaces the renderable prompt: it frames it, so the user can hand the same
 * brief to the model and still paste the image prompt underneath into an
 * image model. Only the framing differs between them, and every clause they
 * quote comes from PromptClauses so no target invents its own wording.
 */
abstract class AbstractPromptCompiler implements PromptCompilerInterface
{
    public function __construct(protected readonly PromptClauses $clauses = new PromptClauses) {}

    /**
     * The ready-to-paste image prompt this brief would generate, compiled by
     * the default compiler so no target can quietly change what gets rendered.
     *
     * @param  array<string, mixed>  $brief
     */
    protected function imagePrompt(array $brief, CreativeFormat $format): string
    {
        return (new GenericPromptCompiler($this->clauses))->compile($brief, $format);
    }

    /**
     * The shared clauses as separate lines. A frame built from bullet points
     * shows which wording the studio decided; camera and character directives
     * already carry their own title, so they stand without a label.
     *
     * @param  array<string, mixed>  $brief
     * @return list<string>
     */
    protected function clauseLines(array $brief): array
    {
        $lines = [];

        foreach ([$this->clauses->camera($brief), $this->clauses->character($brief)] as $directive) {
            $trimmed = trim($directive);
            if ($trimmed !== '') {
                $lines[] = $trimmed;
            }
        }

        $labelled = [
            'Scene' => $this->clauses->scene($brief),
            'Custom scene details' => $this->clauses->custom($brief),
            'Brand' => $this->clauses->brand(is_array($brief['brand'] ?? null) ? $brief['brand'] : []),
            'Campaign' => $this->clauses->campaign(is_array($brief['campaign'] ?? null) ? $brief['campaign'] : []),
        ];

        foreach ($labelled as $label => $clause) {
            $trimmed = trim($clause);
            if ($trimmed !== '') {
                $lines[] = $label.': '.$trimmed;
            }
        }

        return $lines;
    }

    /**
     * The product name, flattened: a structured frame breaks on line breaks
     * and must not carry markup from user input.
     *
     * @param  array<string, mixed>  $brief
     */
    protected function product(array $brief): string
    {
        return $this->clauses->sanitize((string) ($brief['product'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $brief
     */
    protected function description(array $brief): string
    {
        return $this->clauses->sanitize((string) ($brief['description'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $brief
     */
    protected function audience(array $brief): string
    {
        $audience = $this->clauses->sanitize((string) ($brief['audience'] ?? ''));

        return $audience !== '' ? $audience : 'Iranian social commerce shoppers';
    }

    /**
     * The brief's marketing objective in the studio's own Persian wording.
     *
     * @param  array<string, mixed>  $brief
     */
    protected function objectiveClause(array $brief): string
    {
        $objective = (string) ($brief['objective'] ?? '');
        $clause = $objective === '' ? '' : (string) config("creative.effects.goal.{$objective}");

        return $clause !== '' ? $clause : $objective;
    }

    /**
     * The Persian scene line, quoted by the direction compilers.
     *
     * @param  array<string, mixed>  $brief
     */
    protected function persianScene(array $brief): string
    {
        return $this->clauses->sceneSummary($brief);
    }
}
