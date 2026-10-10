<?php

namespace App\Domains\Creative\Prompts;

use App\Domains\Creative\Enums\CreativeFormat;

/**
 * Runway: a shot sheet first, a paste-ready prompt underneath.
 *
 * Gen-3 is directed through departments - camera, movement, light - so those
 * become labelled notes at the top, exactly as a director would read them.
 * When a control arrived empty the note falls back to the selection the
 * studio pre-checks (the same fallbacks the scene summary uses), so the sheet
 * never shows a blank department. The prompt below is the video prompt Hale
 * itself would send, so pasting it changes nothing about the scene.
 */
class RunwayPromptCompiler extends AbstractPromptCompiler
{
    public function key(): string
    {
        return 'runway';
    }

    public function compile(array $brief, CreativeFormat $format): string
    {
        $scene = $this->clauses->sceneParts($brief);
        $defaults = PromptClauses::studioDefaults();
        $description = $this->description($brief);

        // The departments are labelled by the sheet itself, so each note is
        // the body of its clause - a "- Camera: Camera: ..." line would be
        // the studio talking to itself.
        $angle = PromptClauses::body($this->clauses->camera($brief))
            ?: PromptClauses::body((string) config('creative.camera_angles.'.$defaults['camera_angle'].'.prompt').'.');
        $light = PromptClauses::body((string) ($scene['lighting_setup'] ?? ''))
            ?: PromptClauses::body((string) config('creative.lighting_setups.'.$defaults['lighting_setup'].'.prompt').'.');

        // Light already has its own department below, so the scene note carries
        // only what stands under it: the surface and the props.
        $set = implode(' ', array_filter([
            $scene['surface'] ?? '',
            $scene['props'] ?? '',
        ]));

        $lines = [
            'DIRECTING NOTES',
            '- Camera: '.$angle,
            '- Movement: '.trim($this->clauses->cameraMotion($brief)),
            '- Light: '.$light,
            '',
            'SHOT',
            '- Product: '.$this->product($brief).($description !== '' ? ' - '.$description : ''),
        ];

        $sheet = [
            'Scene' => $set,
            'Subject' => PromptClauses::body($this->clauses->character($brief)),
            'Custom' => PromptClauses::body($this->clauses->custom($brief)),
            'Brand' => PromptClauses::body($this->clauses->brand(is_array($brief['brand'] ?? null) ? $brief['brand'] : [])),
            'Campaign' => PromptClauses::body($this->clauses->campaign(is_array($brief['campaign'] ?? null) ? $brief['campaign'] : [])),
        ];

        foreach ($sheet as $label => $value) {
            if ($value !== '') {
                $lines[] = '- '.$label.': '.$value;
            }
        }

        $lines[] = '';
        $lines[] = 'PROMPT FOR GEN-3';
        $lines[] = $this->imagePrompt($brief, $format);

        return implode("\n", $lines);
    }
}
