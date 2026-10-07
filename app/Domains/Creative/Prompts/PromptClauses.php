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
     * The trailing scene clause (surface, props, lighting), leading space
     * included so the base sentence can concatenate it directly.
     *
     * @param  array<string, mixed>  $brief
     */
    public function scene(array $brief): string
    {
        $groups = ['surface' => 'surfaces', 'props' => 'props', 'lighting_setup' => 'lighting_setups'];
        $parts = [];

        foreach ($groups as $field => $group) {
            if (! empty($brief[$field]) && ($prompt = config("creative.{$group}.{$brief[$field]}.prompt"))) {
                $parts[] = ucfirst($prompt).'.';
            }
        }

        return $parts !== [] ? ' '.implode(' ', $parts) : '';
    }

    /**
     * The locked camera directive, front-loaded into the prompt.
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

        return filled($prompt) ? sprintf(' Camera angle (locked): %s.', $prompt) : '';
    }

    /**
     * The locked character directive, front-loaded into the prompt.
     *
     * The catalogue in config/creative.php is the only source: a state whose
     * prompt is empty (dynamic) contributes nothing, and the directive title
     * carries the state key, so a future state with its own prompt needs no
     * change here.
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

        return filled($prompt) ? sprintf(' Character consistency (%s): %s.', $key, $prompt) : '';
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
    private function requestsRenderedText(array $brief): bool
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
