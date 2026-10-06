<?php

namespace App\Domains\Creative\Services;

use App\Domains\Brand\Models\BrandKit;
use App\Domains\Creative\Enums\CreativeFormat;
use App\Domains\Creative\Enums\CreativeGoal;
use App\Domains\Creative\Enums\CreativeStyle;
use App\Domains\Products\Models\Product;

class CreativeEngine
{
    public function __construct(private readonly SeasonThemeService $seasons) {}

    public function autoBest(Product $product, CreativeGoal $goal): array
    {
        $name = mb_strtolower($product->name.' '.($product->description ?? ''));

        $style = match ($goal) {
            CreativeGoal::Sales, CreativeGoal::Promotion => CreativeStyle::Colorful,
            CreativeGoal::Branding => CreativeStyle::Luxury,
            CreativeGoal::Engagement => CreativeStyle::Cinematic,
            CreativeGoal::Launch => CreativeStyle::Fashion,
            default => str_contains($name, 'عطر') || str_contains($name, 'perfume') || str_contains($name, 'طلا') || str_contains($name, 'jewelry')
                ? CreativeStyle::Luxury
                : CreativeStyle::Professional,
        };

        $environment = match (true) {
            str_contains($name, 'عطر') || str_contains($name, 'perfume') => 'luxury',
            str_contains($name, 'گیاه') || str_contains($name, 'طبیعی') || str_contains($name, 'natural') => 'nature',
            str_contains($name, 'لباس') || str_contains($name, 'پوشاک') || str_contains($name, 'dress') || str_contains($name, 'شهر') || str_contains($name, 'urban') => 'urban',
            str_contains($name, 'خانه') || str_contains($name, 'دکور') || str_contains($name, 'مبلمان') || str_contains($name, 'home') => 'home',
            str_contains($name, 'هنر') || str_contains($name, 'انتزاعی') || str_contains($name, 'مفهومی') || str_contains($name, 'abstract') => 'abstract',
            default => 'studio',
        };

        $format = match ($goal) {
            CreativeGoal::Engagement, CreativeGoal::Launch => CreativeFormat::InstagramReel,
            default => CreativeFormat::InstagramPost,
        };

        $duration = $format->type() === 'video' ? 5 : null;

        return [
            'goal' => $goal->value,
            'style' => $style->value,
            'format' => $format->value,
            'environment' => $environment,
            'aspect_ratio' => $format->aspectRatio(),
            'video_duration_seconds' => $duration,
            'character_consistency' => 'dynamic',
        ];
    }

    public function brief(Product $product, array $settings, ?BrandKit $brand = null): array
    {
        $customPrompt = isset($settings['custom_prompt']) && is_string($settings['custom_prompt'])
            ? trim($settings['custom_prompt'])
            : null;

        $brief = [
            'product' => $product->name,
            'description' => $product->description,
            'objective' => $settings['goal'],
            'visual_direction' => $settings['style'],
            'environment' => $settings['environment'] ?? 'studio',
            'format' => $settings['format'],
            'surface' => $settings['surface'] ?? null,
            'props' => $settings['props'] ?? null,
            'camera_angle' => $settings['camera_angle'] ?? null,
            'lighting_setup' => $settings['lighting_setup'] ?? null,
            'character_consistency' => isset($settings['character_consistency']) && filled($settings['character_consistency'])
                ? (string) $settings['character_consistency']
                : 'dynamic',
            'custom_prompt' => ($customPrompt !== null && $customPrompt !== '') ? $customPrompt : null,
            'audience' => 'Iranian social commerce shoppers',
            'generated_at' => now()->toIso8601String(),
        ];

        // Carry the user's brand identity (colors, tone, tagline) into the
        // brief so prompt() can weave it into the generated scene.
        $identity = $brand?->promptIdentity() ?? [];
        if ($identity !== []) {
            $brief['brand'] = $identity;
        }

        // Opt-in campaign style: only an explicit truthy flag plus an active
        // theme with a pack adds the campaign block to the brief, so default
        // prompts stay byte-for-byte identical to before. season_theme lets the
        // studio picker pin the theme, so the pack the inspector shows is the
        // pack the generated prompt actually gets.
        if (! empty($settings['campaign'])) {
            $pinned = (string) ($settings['season_theme'] ?? '');
            $theme = $pinned !== '' ? $this->seasons->byKey($pinned) : $this->seasons->active();
            if (is_array($theme) && filled($theme['prompt_pack'] ?? null)) {
                $brief['campaign'] = [
                    'key' => (string) $theme['key'],
                    'label' => (string) ($theme['campaign_label'] ?? $theme['name']),
                    'pack' => (string) $theme['prompt_pack'],
                ];
            }
        }

        return $brief;
    }

    public function prompt(array $brief, CreativeFormat $format): string
    {
        $sceneParts = [];

        if (! empty($brief['surface']) && ($surfacePrompt = config("creative.surfaces.{$brief['surface']}.prompt"))) {
            $sceneParts[] = ucfirst($surfacePrompt).'.';
        }

        if (! empty($brief['props']) && ($propsPrompt = config("creative.props.{$brief['props']}.prompt"))) {
            $sceneParts[] = ucfirst($propsPrompt).'.';
        }

        if (! empty($brief['lighting_setup']) && ($lightingPrompt = config("creative.lighting_setups.{$brief['lighting_setup']}.prompt"))) {
            $sceneParts[] = ucfirst($lightingPrompt).'.';
        }

        $sceneClause = ! empty($sceneParts) ? ' '.implode(' ', $sceneParts) : '';

        // Front-load the camera directive right after the opening sentence so
        // the model treats it as a locked instruction instead of set dressing;
        // surface/props/lighting stay in the trailing scene clause.
        $cameraClause = $this->cameraClause($brief);
        $characterClause = $this->characterClause($brief);

        $customPromptPart = '';
        if (! empty($brief['custom_prompt']) && is_string($brief['custom_prompt'])) {
            $sanitized = $this->sanitizeCustomPrompt($brief['custom_prompt']);
            if ($sanitized !== '') {
                $customPromptPart = sprintf(' Custom scene details: %s.', $sanitized);
            }
        }

        $brandPart = $this->brandClause(is_array($brief['brand'] ?? null) ? $brief['brand'] : []);
        $campaignPart = $this->campaignClause(is_array($brief['campaign'] ?? null) ? $brief['campaign'] : []);

        $base = sprintf(
            'Create a professional commercial advertising visual for %s.%s%s Objective: %s. Aesthetic style: %s. Environment: %s. Composition: %s ratio (%s).%s%s%s%s High-end commercial production, photorealistic, cinematic lighting, ultra-sharp detail, preserve original product design and packaging, no distracting watermarks, no unwanted text.',
            $brief['product'],
            $cameraClause,
            $characterClause,
            $brief['objective'],
            $brief['visual_direction'],
            $brief['environment'],
            $format->aspectRatio(),
            $format->value,
            $sceneClause,
            $customPromptPart,
            $brandPart,
            $campaignPart
        );

        if ($format->type() === 'video') {
            $base .= sprintf(
                ' Dynamic motion: %s, fluid atmospheric movement, premium brand reel aesthetic, 4K render.',
                $this->cameraMotion($brief)
            );
        }

        return $base;
    }

    /**
     * The locked camera directive, front-loaded into the prompt.
     *
     * @param  array<string, mixed>  $brief
     */
    private function cameraClause(array $brief): string
    {
        $angle = filled($brief['camera_angle'] ?? null) ? (string) $brief['camera_angle'] : '';
        if ($angle === '') {
            return '';
        }

        $prompt = config("creative.camera_angles.{$angle}.prompt");

        return filled($prompt) ? sprintf(' Camera angle (locked): %s.', $prompt) : '';
    }

    /**
     * The locked character consistency directive, front-loaded into the prompt.
     *
     * @param  array<string, mixed>  $brief
     */
    private function characterClause(array $brief): string
    {
        $consistency = filled($brief['character_consistency'] ?? null)
            ? (string) $brief['character_consistency']
            : 'dynamic';

        if ($consistency !== 'locked') {
            return '';
        }

        $prompt = config('creative.character_consistencies.locked.prompt');

        return filled($prompt) ? sprintf(' Character consistency (locked): %s.', $prompt) : '';
    }

    /**
     * Video camera movement that matches the chosen angle, falling back to the
     * generic pan so prompts without a camera angle stay byte-for-byte stable.
     *
     * @param  array<string, mixed>  $brief
     */
    private function cameraMotion(array $brief): string
    {
        $angle = filled($brief['camera_angle'] ?? null) ? (string) $brief['camera_angle'] : '';
        $motion = $angle === '' ? null : config("creative.camera_angles.{$angle}.motion");

        return filled($motion) ? (string) $motion : 'smooth cinematic camera pan';
    }

    public function sanitizeCustomPrompt(string $input): string
    {
        $stripped = strip_tags($input);
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', '', $stripped) ?? '';
        $normalized = preg_replace('/\s+/u', ' ', $clean) ?? '';

        return rtrim(trim($normalized), '. ');
    }

    /**
     * Turn the brand identity stored in the brief into prompt language.
     *
     * @param  array<string, string>  $brand
     */
    private function brandClause(array $brand): string
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
            $bits[] = 'tone '.$this->sanitizeCustomPrompt((string) $brand['tone']);
        }

        $clause = $bits !== [] ? sprintf(' Brand identity: %s.', implode('; ', $bits)) : '';

        if (filled($brand['tagline'] ?? null)) {
            $sanitizedTagline = $this->sanitizeCustomPrompt((string) $brand['tagline']);
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
    private function campaignClause(array $campaign): string
    {
        if (! filled($campaign['pack'] ?? null)) {
            return '';
        }

        $pack = $this->sanitizeCustomPrompt((string) $campaign['pack']);
        if ($pack === '') {
            return '';
        }

        return sprintf(' Campaign mood: %s.', $pack);
    }
}
