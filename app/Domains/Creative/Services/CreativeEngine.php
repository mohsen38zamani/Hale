<?php

namespace App\Domains\Creative\Services;

use App\Domains\AI\Services\EnglishTranslator;
use App\Domains\Brand\Models\BrandKit;
use App\Domains\Creative\Enums\CreativeFormat;
use App\Domains\Creative\Enums\CreativeGoal;
use App\Domains\Creative\Enums\CreativeStyle;
use App\Domains\Creative\Prompts\PromptClauses;
use App\Domains\Creative\Prompts\PromptCompilerFactory;
use App\Domains\Products\Models\Product;

class CreativeEngine
{
    /**
     * The compiler factory and the translator have defaults so
     * `new CreativeEngine($seasons)` keeps working in tests; the container
     * injects its own otherwise.
     */
    public function __construct(
        private readonly SeasonThemeService $seasons,
        private readonly PromptCompilerFactory $compilers = new PromptCompilerFactory,
        private readonly EnglishTranslator $translator = new EnglishTranslator
    ) {}

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
            'character_consistency' => PromptClauses::defaultCharacterConsistency(),
        ];
    }

    public function brief(Product $product, array $settings, ?BrandKit $brand = null): array
    {
        $customPrompt = isset($settings['custom_prompt']) && is_string($settings['custom_prompt'])
            ? trim($settings['custom_prompt'])
            : null;

        $brief = [
            // The name stays as the studio typed it: it is the product's
            // identity, and the one line the Persian scene belongs to.
            'product' => $product->name,
            'description' => $this->translator->toEnglish($product->description),
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
                : PromptClauses::defaultCharacterConsistency(),
            'custom_prompt' => ($customPrompt !== null && $customPrompt !== '')
                ? $this->translator->toEnglish($customPrompt)
                : null,
            'audience' => 'Iranian social commerce shoppers',
            'generated_at' => now()->toIso8601String(),
        ];

        // Carry the user's brand identity (colors, tone, tagline) into the
        // brief so prompt() can weave it into the generated scene. The tone
        // is prose an image model has to read, so it travels in English; the
        // tagline is rendered as text on the image, so it keeps the language
        // the brand wrote it in.
        $identity = $brand?->promptIdentity() ?? [];
        if ($identity !== []) {
            if (isset($identity['tone']) && is_string($identity['tone'])) {
                $identity['tone'] = $this->translator->toEnglish($identity['tone']);
            }
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

        // Redesign 6: which model the prompt is written for travels inside the
        // brief, exactly like the campaign pack, so the compiled prompt, the
        // stored project and the preview endpoint all read one value. An empty
        // target keeps the brief free of the key and prompt() stays generic.
        if (filled($settings['target_ai'] ?? null)) {
            $brief['target_ai'] = (string) $settings['target_ai'];
        }

        return $brief;
    }

    /**
     * Compile the brief for the target it carries. A brief without one (or
     * with a key no compiler answers to) takes the generic path, which is the
     * sentence the studio itself renders in the browser mirror.
     *
     * @param  array<string, mixed>  $brief
     */
    public function prompt(array $brief, CreativeFormat $format): string
    {
        // The Persian scene line quotes the frame out of the brief while the
        // sentence is built from the format argument, so a brief built without
        // one would describe a frame the prompt is not. One source decides both.
        if (! filled($brief['format'] ?? null)) {
            $brief['format'] = $format->value;
        }

        $target = is_string($brief['target_ai'] ?? null) ? $brief['target_ai'] : null;

        return $this->compilers->for($target)->compile($brief, $format);
    }

    /**
     * The clause builder owns the wording rules; the engine keeps this entry
     * point because sanitizing user text is part of its public contract.
     */
    public function sanitizeCustomPrompt(string $input): string
    {
        return $this->compilers->clauses()->sanitize($input);
    }
}
