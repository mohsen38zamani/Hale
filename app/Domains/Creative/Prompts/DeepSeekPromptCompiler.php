<?php

namespace App\Domains\Creative\Prompts;

use App\Domains\Creative\Enums\CreativeFormat;

/**
 * DeepSeek: reason in the open, then write.
 *
 * The model is asked to read the Iranian social commerce shopper, pick the
 * winning angle and lock the scene before it is allowed to produce the image
 * prompt — the CoT is the point of this compiler, not decoration on top of it.
 */
class DeepSeekPromptCompiler extends AbstractPromptCompiler
{
    public function key(): string
    {
        return 'deepseek';
    }

    public function compile(array $brief, CreativeFormat $format): string
    {
        $objective = (string) ($brief['objective'] ?? '');

        $lines = [
            'CHAIN OF THOUGHT',
            '',
            'STEP 1 - READ THE AUDIENCE',
            '- Market: '.$this->audience($brief).', shopping from a phone, deciding in seconds.',
            '- Product: '.$this->product($brief).($this->description($brief) !== '' ? ' - '.$this->description($brief) : ''),
            '- Objective: '.$objective,
            '- Ask what stops the thumb for this shopper before writing a single word.',
            '',
            'STEP 2 - FIND THE WINNING ANGLE',
            '- Goal effect (فارسی): '.$this->objectiveClause($brief),
            '- Scene (فارسی): '.$this->persianScene($brief),
            '- Angle to argue: '.(string) ($brief['visual_direction'] ?? '').' x '.(string) ($brief['environment'] ?? '').' serving '.$objective.'.',
            '',
            'STEP 3 - LOCK THE SCENE',
        ];

        foreach ($this->clauseLines($brief) as $line) {
            $lines[] = '- '.$line;
        }

        $lines[] = '';
        $lines[] = 'STEP 4 - WRITE FOR CONVERSION';
        $lines[] = '- Text policy: '.trim($this->clauses->textSuppression($brief)).'.';
        $lines[] = '- The angle decided above outranks any adjective: prefer one concrete visual fact over three vague ones.';
        $lines[] = '';
        $lines[] = 'FINAL IMAGE PROMPT';
        $lines[] = $this->imagePrompt($brief, $format);

        return implode("\n", $lines);
    }
}
