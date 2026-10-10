<?php

namespace App\Domains\Creative\Prompts;

/**
 * Every clause the prompt compilers share.
 *
 * The scene, camera, character, custom-scene, brand, campaign and text
 * suppression wording is the same no matter which model the prompt is written
 * for; only the assembly differs between compilers. Keeping the clauses here
 * means a compiler stays a few lines of structure and can never drift away
 * from the wording the studio has been sending all along.
 */
class PromptClauses
{
    /**
     * The three scene clauses the studio composes, still separate: a flat
     * prompt joins them, a structured one (XML blocks) places them apart.
     *
     * @param  array<string, mixed>  $brief
     * @return array<string, string>
     */
    public function sceneParts(array $brief): array
    {
        $groups = ['surface' => 'surfaces', 'props' => 'props', 'lighting_setup' => 'lighting_setups'];
        $parts = [];

        foreach ($groups as $field => $group) {
            if (! empty($brief[$field]) && ($prompt = config("creative.{$group}.{$brief[$field]}.prompt"))) {
                $parts[$field] = ucfirst($prompt).'.';
            }
        }

        return $parts;
    }

    /**
     * The trailing scene clause (surface, props, lighting), leading space
     * included so the base sentence can concatenate it directly.
     *
     * @param  array<string, mixed>  $brief
     */
    public function scene(array $brief): string
    {
        $parts = $this->sceneParts($brief);

        return $parts !== [] ? ' '.implode(' ', $parts) : '';
    }

    /**
     * The camera directive, front-loaded into the prompt.
     *
     * It reads as a field of the brief, not as a screen state: "(locked)" was
     * the studio's own control vocabulary, and an image model given a choice
     * it never had only gets noise from it.
     *
     * @param  array<string, mixed>  $brief
     */
    public function camera(array $brief): string
    {
        $angle = filled($brief['camera_angle'] ?? null) ? (string) $brief['camera_angle'] : '';
        if ($angle === '') {
            return '';
        }

        $prompt = config("creative.camera_angles.{$angle}.prompt");

        return filled($prompt) ? sprintf(' Camera: %s.', $prompt) : '';
    }

    /**
     * A clause with the label the flat sentence gives it taken back off, for
     * the frames that carry their own title: a bullet, an XML tag, a shot
     * sheet department. Two labels ("Custom scene details: Custom scene
     * details: ...") read as a bug to whoever is about to paste the prompt
     * and dilute the field for a model. Nothing but the label is touched, so
     * the body keeps its wording and its full stop, and it is capitalised so
     * it can stand as a sentence of its own.
     */
    public static function body(string $clause): string
    {
        $trimmed = trim($clause);
        $unlabelled = (string) preg_replace(
            '/^(?:Camera|Character consistency|Custom scene details|Brand identity|Campaign mood|Lighting): /u',
            '',
            $trimmed
        );

        return ucfirst($unlabelled);
    }

    /**
     * The character directive, front-loaded into the prompt.
     *
     * The catalogue in config/creative.php is the only source: a state whose
     * prompt is empty (dynamic) contributes nothing, and only the body of the
     * state reaches the model - the key is a studio control name, not prompt
     * language - so a future state with its own prompt needs no change here.
     *
     * @param  array<string, mixed>  $brief
     */
    public function character(array $brief): string
    {
        $key = filled($brief['character_consistency'] ?? null)
            ? (string) $brief['character_consistency']
            : self::defaultCharacterConsistency();

        $entry = config("creative.character_consistencies.{$key}");
        $prompt = is_array($entry) ? (string) ($entry['prompt'] ?? '') : '';

        return filled($prompt) ? sprintf(' Character consistency: %s.', $prompt) : '';
    }

    /**
     * The user's own scene details, sanitized, leading space included.
     *
     * @param  array<string, mixed>  $brief
     */
    public function custom(array $brief): string
    {
        if (empty($brief['custom_prompt']) || ! is_string($brief['custom_prompt'])) {
            return '';
        }

        $sanitized = $this->sanitize($brief['custom_prompt']);

        return $sanitized === '' ? '' : sprintf(' Custom scene details: %s.', $sanitized);
    }

    /**
     * Turn the brand identity stored in the brief into prompt language.
     *
     * @param  array<string, string>  $brand
     */
    public function brand(array $brand): string
    {
        $palette = [];
        foreach (['primary_color' => 'primary', 'secondary_color' => 'secondary', 'accent_color' => 'accent'] as $key => $label) {
            if (filled($brand[$key] ?? null)) {
                $palette[] = $label.' '.$brand[$key];
            }
        }

        $bits = [];
        if ($palette !== []) {
            $bits[] = 'palette '.implode(', ', $palette);
        }
        if (filled($brand['tone'] ?? null)) {
            $bits[] = 'tone '.$this->sanitize((string) $brand['tone']);
        }

        $clause = $bits !== [] ? sprintf(' Brand identity: %s.', implode('; ', $bits)) : '';

        if (filled($brand['tagline'] ?? null)) {
            $sanitizedTagline = $this->sanitize((string) $brand['tagline']);
            if ($sanitizedTagline !== '') {
                $clause .= sprintf(' Keep the brand tagline "%s" legible in the frame.', $sanitizedTagline);
            }
        }

        return $clause;
    }

    /**
     * Turn the campaign block stored in the brief into prompt language.
     *
     * @param  array<string, mixed>  $campaign
     */
    public function campaign(array $campaign): string
    {
        if (! filled($campaign['pack'] ?? null)) {
            return '';
        }

        $pack = $this->sanitize((string) $campaign['pack']);
        if ($pack === '') {
            return '';
        }

        return sprintf(' Campaign mood: %s.', $pack);
    }

    /**
     * What may be written inside the frame: nothing, unless the brief itself
     * asks for wording. The strict tail would otherwise contradict the brand
     * clause that keeps a tagline legible and the custom scene details the
     * user explicitly filled in.
     *
     * @param  array<string, mixed>  $brief
     */
    public function textSuppression(array $brief): string
    {
        return $this->requestsRenderedText($brief)
            ? (string) config('creative.text_suppression.permissive')
            : (string) config('creative.text_suppression.strict');
    }

    /**
     * Video camera movement that matches the chosen angle, falling back to the
     * generic pan so prompts without a camera angle stay byte-for-byte stable.
     *
     * @param  array<string, mixed>  $brief
     */
    public function cameraMotion(array $brief): string
    {
        $angle = filled($brief['camera_angle'] ?? null) ? (string) $brief['camera_angle'] : '';
        $motion = $angle === '' ? null : config("creative.camera_angles.{$angle}.motion");

        return filled($motion) ? (string) $motion : 'smooth cinematic camera pan';
    }

    /**
     * The Persian line the studio reads under the canvas, rendered on the
     * server as well so a compiler can quote it. The grammar comes from
     * summary_template and every slot resolves through the effects catalogue,
     * exactly like updateSceneSummary() does in the browser: one sentence for
     * the same scene in both places.
     *
     * A brief built without a scene control (a preset, a bulk batch, an API
     * caller) gets the selection the studio pre-checks, so the line never
     * claims a scene the user cannot see.
     *
     * @param  array<string, mixed>  $brief
     */
    public function sceneSummary(array $brief): string
    {
        $template = (string) config('creative.summary_template');
        $defaults = self::studioDefaults();
        $resolved = [
            '{product}' => filled($brief['product'] ?? null) ? (string) $brief['product'].' شما' : 'محصول شما',
        ];

        preg_match_all('/\{(\w+)\}/', $template, $slots);

        foreach (array_unique($slots[1]) as $slot) {
            if ($slot === 'product') {
                continue;
            }

            $value = $this->summaryValue($brief, (string) $slot, $defaults);
            $clause = config("creative.effects.{$slot}.{$value}");

            if (! filled($clause)) {
                $clause = $this->summaryLabel((string) $slot, $value);
            }

            $resolved['{'.$slot.'}'] = filled($clause) ? (string) $clause : $value;
        }

        return strtr($template, $resolved);
    }

    /**
     * The selections the studio pre-checks for every control (mirrors the
     * fallbacks in updateCanvasState()), used where a brief arrives without
     * one so the Persian line still reads as a sentence.
     *
     * @return array<string, string>
     */
    public static function studioDefaults(): array
    {
        return [
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'environment' => 'studio',
            'surface' => 'default',
            'props' => 'none',
            'camera_angle' => 'eye_level',
            'lighting_setup' => 'softbox',
            'character_consistency' => self::defaultCharacterConsistency(),
        ];
    }

    /**
     * The brief renames two controls (goal -> objective, style ->
     * visual_direction); everything else keeps its studio field name.
     *
     * @param  array<string, mixed>  $brief
     * @param  array<string, string>  $defaults
     */
    private function summaryValue(array $brief, string $control, array $defaults): string
    {
        $field = ['goal' => 'objective', 'style' => 'visual_direction'][$control] ?? $control;
        $value = $brief[$field] ?? $defaults[$control] ?? '';

        return filled($value) ? (string) $value : (string) ($defaults[$control] ?? '');
    }

    /**
     * Fallback wording for a control whose effects entry is missing, taken
     * from the catalogue the studio labels its options with.
     */
    private function summaryLabel(string $control, string $value): ?string
    {
        $group = [
            'surface' => 'surfaces',
            'props' => 'props',
            'camera_angle' => 'camera_angles',
            'lighting_setup' => 'lighting_setups',
            'character_consistency' => 'character_consistencies',
        ][$control] ?? null;

        return $group !== null ? config("creative.{$group}.{$value}.label") : null;
    }

    /**
     * The state a request without one inherits, decided by a single config key
     * so autoBest(), the brief and every compiled clause can never disagree.
     */
    public static function defaultCharacterConsistency(): string
    {
        $key = (string) config('creative.character_consistency_default', '');

        return $key !== '' ? $key : 'dynamic';
    }

    public function sanitize(string $input): string
    {
        $stripped = strip_tags($input);
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', '', $stripped) ?? '';
        $normalized = preg_replace('/\s+/u', ' ', $clean) ?? '';

        return rtrim(trim($normalized), '. ');
    }

    /**
     * Does the brief ask for rendered wording, either through the brand
     * tagline or through a text request inside the custom scene details?
     *
     * Keywords match as whole words in both languages, so `متناسب` does not
     * count as `متن` and `context` does not count as `text`.
     *
     * @param  array<string, mixed>  $brief
     */
    public function requestsRenderedText(array $brief): bool
    {
        if (filled($brief['brand']['tagline'] ?? null)) {
            return true;
        }

        $custom = is_string($brief['custom_prompt'] ?? null) ? mb_strtolower($brief['custom_prompt']) : '';

        if ($custom === '') {
            return false;
        }

        foreach ((array) config('creative.text_suppression.request_keywords', []) as $keyword) {
            $keyword = mb_strtolower((string) $keyword);

            if ($keyword !== '' && preg_match('/(?<!\p{L})'.preg_quote($keyword, '/').'(?!\p{L})/u', $custom) === 1) {
                return true;
            }
        }

        return false;
    }
}
