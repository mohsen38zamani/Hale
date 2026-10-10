<?php

namespace App\Domains\Creative\Prompts;

use App\Domains\Creative\Enums\CreativeFormat;

/**
 * ChatGPT: a rich, structured campaign brief in English and Persian that ends
 * with the image prompt itself, ready to hand to DALL-E 3 or GPT-Image.
 *
 * The brief is what the user is really buying from a direction model: the
 * marketing framing (objective, audience, hook, caption) around a scene that
 * has already been decided. Nothing here re-decides the scene.
 */
class ChatGptPromptCompiler extends AbstractPromptCompiler
{
    public function key(): string
    {
        return 'chatgpt';
    }

    public function compile(array $brief, CreativeFormat $format): string
    {
        $product = $this->product($brief);
        $description = $this->description($brief);
        $objective = (string) ($brief['objective'] ?? '');
        $objectiveClause = $this->objectiveClause($brief);
        $tagline = $this->clauses->sanitize((string) ($brief['brand']['tagline'] ?? ''));

        return implode("\n", [
            '# CAMPAIGN BRIEF',
            '- Product: '.$product.($description !== '' ? ' — '.$description : ''),
            '- Objective: '.$objective,
            '- Audience: '.$this->audience($brief),
            '- Visual direction: '.(string) ($brief['visual_direction'] ?? '').' (environment: '.(string) ($brief['environment'] ?? '').')',
            '- Format: '.$format->aspectRatio().' ('.$format->value.')',
            '',
            '# SCENE',
            ...$this->clauseLines($brief),
            '',
            '# IMAGE PROMPT (ready to paste into any image model)',
            $this->imagePrompt($brief, $format),
            '',
            '# CAPTION & HOOK (فارسی)',
            '- خلاصهٔ صحنه: '.$this->persianScene($brief),
            '- قلاب: چرا '.$product.'؟ '.$objectiveClause.'.',
            '- کپشن پیشنهادی: «'.$product.'؛ '.$objectiveClause.'.'.($tagline !== '' ? ' '.$tagline : '').'»',
        ]);
    }
}
