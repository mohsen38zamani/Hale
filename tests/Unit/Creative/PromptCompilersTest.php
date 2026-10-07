<?php

namespace Tests\Unit\Creative;

use App\Domains\Creative\Enums\CreativeFormat;
use App\Domains\Creative\Enums\CreativeGoal;
use App\Domains\Creative\Enums\CreativeStyle;
use App\Domains\Creative\Prompts\GenericPromptCompiler;
use App\Domains\Creative\Prompts\PromptClauses;
use App\Domains\Creative\Prompts\PromptCompilerFactory;
use App\Domains\Creative\Services\CreativeEngine;
use App\Domains\Creative\Services\SeasonThemeService;
use App\Domains\Products\Models\Product;
use Tests\TestCase;

class PromptCompilersTest extends TestCase
{
    private CreativeEngine $engine;

    private PromptCompilerFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new CreativeEngine(app(SeasonThemeService::class));
        $this->factory = new PromptCompilerFactory;
    }

    public function test_the_generic_compiler_says_exactly_what_the_engine_says(): void
    {
        foreach ($this->briefs() as [$brief, $format]) {
            $this->assertSame(
                $this->engine->prompt($brief, $format),
                $this->factory->for('generic')->compile($brief, $format),
                'The generic compiler must be the engine sentence itself, not a rewrite of it.'
            );

            $this->assertSame(
                $this->engine->prompt($brief, $format),
                $this->engine->prompt([...$brief, 'target_ai' => 'generic'], $format),
                'Naming the default target must not change a single byte of the prompt.'
            );
        }
    }

    public function test_the_factory_falls_back_to_the_generic_compiler(): void
    {
        foreach ([null, '', '   ', 'unknown-model', 'GENERIC', 'Claude'] as $key) {
            $compiler = $this->factory->for($key);

            $this->assertInstanceOf(GenericPromptCompiler::class, $compiler, "Target [{$key}] must not fail the request.");
            $this->assertSame('generic', $compiler->key());
        }
    }

    public function test_the_target_catalogue_describes_every_choice_for_the_studio(): void
    {
        $categories = ['default', ...array_keys((array) config('creative.target_ai_categories'))];
        $labels = [];

        foreach ((array) config('creative.target_ais') as $key => $entry) {
            $this->assertIsArray($entry, "Target [{$key}] must be an array.");
            $this->assertMatchesRegularExpression('/^[a-z][a-z0-9_]*$/', (string) $key, "Target [{$key}] is not a valid field name.");

            $label = trim((string) ($entry['label'] ?? ''));
            $this->assertNotSame('', $label, "Target [{$key}] needs a label.");
            $this->assertNotContains($label, $labels, "Two targets share the label [{$label}].");
            $labels[] = $label;

            $this->assertContains($entry['category'] ?? null, $categories, "Target [{$key}] points at an unknown tab.");
            $this->assertContains($entry['mode'] ?? null, ['generate', 'copy'], "Target [{$key}] must say how it is served.");

            $types = array_values((array) ($entry['types'] ?? []));
            $this->assertNotSame([], $types, "Target [{$key}] must declare which format types it serves.");
            foreach ($types as $type) {
                $this->assertContains($type, ['image', 'video'], "Target [{$key}] serves an unknown format type [{$type}].");
            }

            $note = trim((string) ($entry['note'] ?? ''));
            $this->assertNotSame('', $note, "Target [{$key}] needs the clause the selector shows.");
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $note, "Target [{$key}] note must be readable by the studio's Persian UI.");
        }

        $generatable = array_keys(array_filter(
            (array) config('creative.target_ais'),
            fn (array $entry): bool => $entry['mode'] === 'generate'
        ));

        $this->assertSame(
            ['generic', 'imagen', 'veo'],
            $generatable,
            'Only the targets our own pipeline can build may be offered as generate; the rest are prompts to copy.'
        );
    }

    public function test_the_selector_splits_into_the_four_required_tabs(): void
    {
        $categories = array_keys((array) config('creative.target_ai_categories'));

        $this->assertSame(['text', 'image', 'video', 'edit'], $categories, 'The four required tabs are the only tabs.');

        foreach ((array) config('creative.target_ai_categories') as $key => $tab) {
            $this->assertNotSame('', trim((string) ($tab['label'] ?? '')), "Tab [{$key}] needs a label.");
        }

        $used = array_values(array_unique(array_filter(
            array_column((array) config('creative.target_ais'), 'category'),
            fn (string $category): bool => $category !== 'default'
        )));

        $this->assertSame($categories, $used, 'Every tab must hold at least one model and the catalogue must not invent a fifth.');
    }

    public function test_the_brief_carries_only_a_filled_target_ai(): void
    {
        $product = new Product(['name' => 'عطر تست', 'description' => 'محصول آزمایشی']);
        $settings = [
            'goal' => CreativeGoal::Sales->value,
            'style' => CreativeStyle::Luxury->value,
            'environment' => 'studio',
            'format' => CreativeFormat::InstagramPost->value,
        ];

        $this->assertArrayNotHasKey('target_ai', $this->engine->brief($product, $settings));

        $blank = $this->engine->brief($product, [...$settings, 'target_ai' => '   ']);
        $this->assertArrayNotHasKey('target_ai', $blank, 'A blank target must leave the brief generic.');

        $targeted = $this->engine->brief($product, [...$settings, 'target_ai' => 'midjourney']);
        $this->assertSame('midjourney', $targeted['target_ai'], 'The target has to reach the compilers through the brief.');
    }

    public function test_the_generic_compiler_is_assembled_from_the_shared_clauses(): void
    {
        $clauses = $this->factory->clauses();
        $brief = [
            'product' => 'عطر مردانه',
            'objective' => 'launch',
            'visual_direction' => 'cinematic',
            'environment' => 'urban',
            'surface' => 'marble',
            'props' => 'botanical',
            'lighting_setup' => 'softbox',
            'camera_angle' => 'eye_level',
            'character_consistency' => 'locked',
            'custom_prompt' => 'با یک برگ سبز کنار شیشه',
            'brand' => ['tagline' => 'بوی ماندگار'],
            'campaign' => ['pack' => 'pomegranate and warm light'],
        ];

        $shared = [
            'scene' => $clauses->scene($brief),
            'camera' => $clauses->camera($brief),
            'character' => $clauses->character($brief),
            'custom' => $clauses->custom($brief),
            'brand' => $clauses->brand($brief['brand']),
            'campaign' => $clauses->campaign($brief['campaign']),
            'suppression' => $clauses->textSuppression($brief),
        ];

        $this->assertSame('', $clauses->character([]), 'A brief without a state contributes no character clause.');
        $this->assertSame('', $clauses->scene([]), 'A brief without scene controls contributes no scene clause.');
        $this->assertSame('smooth cinematic camera pan', $clauses->cameraMotion([]), 'The generic pan is the documented fallback.');

        $prompt = $this->factory->for('generic')->compile($brief, CreativeFormat::InstagramPost);

        foreach ($shared as $name => $clause) {
            $this->assertNotSame('', $clause, "The [{$name}] clause must say something for this brief.");
            $this->assertStringContainsString($clause, $prompt, "The compiled prompt must carry the shared [{$name}] clause.");
        }

        // A second instance must resolve to the same wording, otherwise a
        // compiler could start carrying its own private copy of the rules.
        $this->assertSame($prompt, (new GenericPromptCompiler(new PromptClauses))->compile($brief, CreativeFormat::InstagramPost));
    }

    /**
     * @return list<array{array<string, mixed>, CreativeFormat}>
     */
    private function briefs(): array
    {
        return [
            [
                [
                    'product' => 'عطر سلطنتی',
                    'objective' => 'sales',
                    'visual_direction' => 'luxury',
                    'environment' => 'luxury',
                    'custom_prompt' => null,
                ],
                CreativeFormat::InstagramPost,
            ],
            [
                [
                    'product' => 'کیف چرم دست‌دوز',
                    'objective' => 'branding',
                    'visual_direction' => 'natural',
                    'environment' => 'nature',
                    'surface' => 'wood',
                    'props' => 'botanical',
                    'camera_angle' => 'hero_shot',
                    'lighting_setup' => 'sunlight',
                    'character_consistency' => 'locked',
                    'custom_prompt' => 'کنار پنجره با نور طلایی عصرگاهی',
                    'brand' => [
                        'primary_color' => '#111827',
                        'tone' => 'گرم و صمیمی',
                        'tagline' => 'چرمی که سال‌ها می‌ماند',
                    ],
                    'campaign' => [
                        'key' => 'yald',
                        'label' => 'Yalda',
                        'pack' => 'pomegranate and warm light',
                    ],
                ],
                CreativeFormat::InstagramPost,
            ],
            [
                [
                    'product' => 'عطر مردانه',
                    'objective' => 'launch',
                    'visual_direction' => 'cinematic',
                    'environment' => 'urban',
                    'surface' => 'obsidian',
                    'props' => 'smoke',
                    'camera_angle' => 'side_angle',
                    'lighting_setup' => 'neon',
                    'character_consistency' => 'dynamic',
                    'custom_prompt' => null,
                ],
                CreativeFormat::InstagramReel,
            ],
        ];
    }
}
