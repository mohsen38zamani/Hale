<?php

namespace App\Domains\Creative\Prompts;

use App\Domains\Creative\Enums\CreativeFormat;

/**
 * Claude: the same decided scene wearing valid XML, so the model can read the
 * brief section by section instead of parsing one long line.
 *
 * Every leaf is escaped: product names and custom scene details come from the
 * user, and a stray `<` must not be able to break the document (or worse,
 * close a tag on our behalf).
 */
class ClaudePromptCompiler extends AbstractPromptCompiler
{
    public function key(): string
    {
        return 'claude';
    }

    public function compile(array $brief, CreativeFormat $format): string
    {
        $scene = $this->clauses->sceneParts($brief);
        $lines = ['<claude_prompt>', '  <product_context>'];
        $lines[] = $this->tag('product', $this->product($brief), 2);
        $lines[] = $this->tag('description', $this->description($brief), 2);
        $lines[] = $this->tag('audience', $this->audience($brief), 2);
        $lines[] = '  </product_context>';

        // The locked decisions: what the model may not re-imagine.
        $lines[] = '  <creative_direction>';
        $lines[] = $this->tag('objective', (string) ($brief['objective'] ?? ''), 2);
        $lines[] = $this->tag('visual_direction', (string) ($brief['visual_direction'] ?? ''), 2);
        foreach (['camera_angle' => $this->clauses->camera($brief), 'character_consistency' => $this->clauses->character($brief)] as $name => $directive) {
            $trimmed = trim($directive);
            if ($trimmed !== '') {
                $lines[] = $this->tag($name, $trimmed, 2);
            }
        }
        $custom = trim($this->clauses->custom($brief));
        if ($custom !== '') {
            $lines[] = $this->tag('custom_scene_details', $custom, 2);
        }
        $lines[] = '  </creative_direction>';

        $lines[] = '  <visual_style>';
        $lines[] = $this->tag('surface', (string) ($scene['surface'] ?? ''), 2);
        $lines[] = $this->tag('props', (string) ($scene['props'] ?? ''), 2);
        $lines[] = '  </visual_style>';

        $lines[] = '  <lighting_and_atmosphere>';
        $lines[] = $this->tag('environment', (string) ($brief['environment'] ?? ''), 2);
        $lines[] = $this->tag('lighting', (string) ($scene['lighting_setup'] ?? ''), 2);
        $lines[] = '  </lighting_and_atmosphere>';

        $brand = trim($this->clauses->brand(is_array($brief['brand'] ?? null) ? $brief['brand'] : []));
        $campaign = trim($this->clauses->campaign(is_array($brief['campaign'] ?? null) ? $brief['campaign'] : []));
        if ($brand !== '' || $campaign !== '') {
            $lines[] = '  <brand_and_campaign>';
            if ($brand !== '') {
                $lines[] = $this->tag('brand', $brand, 2);
            }
            if ($campaign !== '') {
                $lines[] = $this->tag('campaign', $campaign, 2);
            }
            $lines[] = '  </brand_and_campaign>';
        }

        $lines[] = '  <output_format>';
        $lines[] = $this->tag('type', $format->type(), 2);
        $lines[] = $this->tag('aspect_ratio', $format->aspectRatio(), 2);
        $lines[] = $this->tag('format_key', $format->value, 2);
        $lines[] = $this->tag('prompt', $this->imagePrompt($brief, $format), 2);
        $lines[] = '  </output_format>';
        $lines[] = '</claude_prompt>';

        return implode("\n", $lines);
    }

    private function tag(string $name, string $value, int $depth): string
    {
        $safe = htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $pad = str_repeat('  ', $depth);

        return "{$pad}<{$name}>{$safe}</{$name}>";
    }
}
