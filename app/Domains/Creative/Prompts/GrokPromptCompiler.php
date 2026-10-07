<?php

namespace App\Domains\Creative\Prompts;

use App\Domains\Creative\Enums\CreativeFormat;

/**
 * Grok: short, loud, built for the feed.
 *
 * The compiler keeps its lines clipped and its headers blunt, but it quotes
 * the very same clauses as the others — a bold voice must not mean a
 * different scene.
 */
class GrokPromptCompiler extends AbstractPromptCompiler
{
    public function key(): string
    {
        return 'grok';
    }

    public function compile(array $brief, CreativeFormat $format): string
    {
        $product = $this->product($brief);
        $objectiveClause = $this->objectiveClause($brief);

        $lines = [
            'VIRAL DIRECTION',
            'Made to stop the scroll. Bold product, one clear idea, zero stock-photo energy.',
            '',
            'THE CONCEPT',
            '- '.$product.' x '.(string) ($brief['visual_direction'] ?? '').' in '.(string) ($brief['environment'] ?? '').'.',
            '- Audience: '.$this->audience($brief),
            '- Format: '.$format->aspectRatio().' ('.$format->value.')',
            '- Angle (فارسی): '.$objectiveClause,
            '- Scene (فارسی): '.$this->persianScene($brief),
            '',
            'LOCKED DECISIONS',
        ];

        foreach ($this->clauseLines($brief) as $line) {
            $lines[] = '- '.$line;
        }

        $lines[] = '';
        $lines[] = 'IMAGE PROMPT';
        $lines[] = $this->imagePrompt($brief, $format);
        $lines[] = '';
        $lines[] = 'CAPTION HOOK (فارسی)';
        $lines[] = '«چرا '.$product.'؟ '.$objectiveClause.'.»';

        return implode("\n", $lines);
    }
}
