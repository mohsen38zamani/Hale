<?php

namespace Tests\Unit\Creative;

use App\Domains\Creative\Enums\CreativeFormat;
use App\Domains\Creative\Enums\CreativeGoal;
use App\Domains\Creative\Enums\CreativeStyle;
use App\Domains\Creative\Services\CreativeEngine;
use App\Domains\Creative\Services\SeasonThemeService;
use App\Domains\Products\Models\Product;
use Tests\TestCase;

class CreativeEngineTest extends TestCase
{
    private CreativeEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new CreativeEngine(app(SeasonThemeService::class));
    }

    public function test_auto_best_suggests_luxury_style_for_perfume(): void
    {
        $product = new Product(['name' => 'عطر دیور ساواژ', 'description' => 'عطر مردانه تلخ و خنک']);
        $result = $this->engine->autoBest($product, CreativeGoal::Sales);

        $this->assertSame(CreativeGoal::Sales->value, $result['goal']);
        $this->assertSame(CreativeStyle::Colorful->value, $result['style']);
        $this->assertSame('luxury', $result['environment']);
    }

    public function test_brief_includes_custom_prompt_when_provided(): void
    {
        $product = new Product(['name' => 'ساعت مچی', 'description' => 'ساعت کلاسیک عقربه‌ای']);
        $brief = $this->engine->brief($product, [
            'goal' => CreativeGoal::Branding->value,
            'style' => CreativeStyle::Luxury->value,
            'environment' => 'studio',
            'format' => CreativeFormat::InstagramPost->value,
            'custom_prompt' => 'روی سنگ مرمر مشکی با انعکاس نور طلایی',
        ]);

        $this->assertSame('ساعت مچی', $brief['product']);
        $this->assertSame('روی سنگ مرمر مشکی با انعکاس نور طلایی', $brief['custom_prompt']);
    }

    public function test_brief_normalizes_empty_and_whitespace_custom_prompt_to_null(): void
    {
        $product = new Product(['name' => 'کفش ورزشی']);
        $brief = $this->engine->brief($product, [
            'goal' => CreativeGoal::Sales->value,
            'style' => CreativeStyle::Colorful->value,
            'environment' => 'urban',
            'format' => CreativeFormat::InstagramPost->value,
            'custom_prompt' => '   ',
        ]);

        $this->assertNull($brief['custom_prompt']);
    }

    public function test_prompt_generates_standard_prompt_without_custom_prompt(): void
    {
        $brief = [
            'product' => 'عطر سلطنتی',
            'objective' => 'sales',
            'visual_direction' => 'luxury',
            'environment' => 'luxury',
            'custom_prompt' => null,
        ];

        $prompt = $this->engine->prompt($brief, CreativeFormat::InstagramPost);

        $expected = 'Create a professional commercial advertising visual for عطر سلطنتی. Objective: sales. Aesthetic style: luxury. Environment: luxury. Composition: 1:1 ratio (instagram_post). High-end commercial production, photorealistic, cinematic lighting, ultra-sharp detail, preserve original product design and packaging, no distracting watermarks, no unwanted text.';

        $this->assertSame($expected, $prompt);
    }

    public function test_prompt_cleanly_incorporates_custom_prompt(): void
    {
        $brief = [
            'product' => 'عطر سلطنتی',
            'objective' => 'sales',
            'visual_direction' => 'luxury',
            'environment' => 'luxury',
            'custom_prompt' => 'روی صخره مرطوب بازالت، میان گل‌های ارکیده صورتی',
        ];

        $prompt = $this->engine->prompt($brief, CreativeFormat::InstagramPost);

        $expected = 'Create a professional commercial advertising visual for عطر سلطنتی. Objective: sales. Aesthetic style: luxury. Environment: luxury. Composition: 1:1 ratio (instagram_post). Custom scene details: روی صخره مرطوب بازالت، میان گل‌های ارکیده صورتی. High-end commercial production, photorealistic, cinematic lighting, ultra-sharp detail, preserve original product design and packaging, no distracting watermarks, no unwanted text.';

        $this->assertSame($expected, $prompt);
    }

    public function test_prompt_sanitizes_custom_prompt_whitespace_and_html_tags(): void
    {
        $brief = [
            'product' => 'کرم پوست',
            'objective' => 'branding',
            'visual_direction' => 'natural',
            'environment' => 'nature',
            'custom_prompt' => "  <b>روی برگ‌های مرطوب</b> \n  با نور خورشید..  ",
        ];

        $prompt = $this->engine->prompt($brief, CreativeFormat::InstagramPost);

        $this->assertStringContainsString('Custom scene details: روی برگ‌های مرطوب با نور خورشید.', $prompt);
        $this->assertStringNotContainsString('<b>', $prompt);
    }

    public function test_prompt_includes_video_motion_clause_for_video_format_with_custom_prompt(): void
    {
        $brief = [
            'product' => 'عینک آفتابی',
            'objective' => 'engagement',
            'visual_direction' => 'cinematic',
            'environment' => 'urban',
            'custom_prompt' => 'در یک خیابان بارانی با انعکاس نور نئون مغازه‌ها',
        ];

        $prompt = $this->engine->prompt($brief, CreativeFormat::InstagramReel);

        $this->assertStringContainsString('Custom scene details: در یک خیابان بارانی با انعکاس نور نئون مغازه‌ها.', $prompt);
        $this->assertStringContainsString('Dynamic motion: smooth cinematic camera pan, fluid atmospheric movement, premium brand reel aesthetic, 4K render.', $prompt);
    }

    public function test_brief_includes_scene_controls_when_provided(): void
    {
        $product = new Product(['name' => 'ادکلن خنک']);
        $brief = $this->engine->brief($product, [
            'goal' => CreativeGoal::Branding->value,
            'style' => CreativeStyle::Luxury->value,
            'environment' => 'studio',
            'format' => CreativeFormat::InstagramPost->value,
            'surface' => 'marble',
            'props' => 'botanical',
            'camera_angle' => 'hero_shot',
            'lighting_setup' => 'rim',
        ]);

        $this->assertSame('marble', $brief['surface']);
        $this->assertSame('botanical', $brief['props']);
        $this->assertSame('hero_shot', $brief['camera_angle']);
        $this->assertSame('rim', $brief['lighting_setup']);
    }

    public function test_prompt_cleanly_incorporates_scene_controls(): void
    {
        $brief = [
            'product' => 'عطر لوکس',
            'objective' => 'sales',
            'visual_direction' => 'luxury',
            'environment' => 'luxury',
            'surface' => 'obsidian',
            'props' => 'crystals',
            'camera_angle' => 'macro',
            'lighting_setup' => 'neon',
            'custom_prompt' => 'جلوه بسیار درخشان',
        ];

        $prompt = $this->engine->prompt($brief, CreativeFormat::InstagramPost);

        $this->assertStringContainsString('Elevated on a glossy black obsidian mirror surface with sharp glossy ground reflections.', $prompt);
        $this->assertStringContainsString('Flanked by floating geometric glass prisms and translucent crystal shards scattering spectrum colors.', $prompt);
        $this->assertStringContainsString('Camera angle (locked): extreme close-up macro shot with shallow depth of field, camera tight on the product surface revealing texture and craftsmanship.', $prompt);
        $this->assertStringContainsString('Lighting: futuristic duotone cyber neon backlight with subtle magenta and cyan ambient glow.', $prompt);
        $this->assertStringContainsString('Custom scene details: جلوه بسیار درخشان.', $prompt);
    }

    public function test_camera_angle_is_front_loaded_before_objective_for_every_angle(): void
    {
        $angles = config('creative.camera_angles');
        $this->assertCount(6, $angles, 'Every camera angle configured in config/creative.php must stay in sync with the studio UI.');

        foreach ($angles as $key => $angle) {
            $prompt = $this->engine->prompt([
                'product' => 'عطر لوکس',
                'objective' => 'sales',
                'visual_direction' => 'luxury',
                'environment' => 'studio',
                'camera_angle' => $key,
            ], CreativeFormat::InstagramPost);

            $directive = sprintf('Camera angle (locked): %s.', $angle['prompt']);
            $position = strpos($prompt, $directive);
            $objective = strpos($prompt, 'Objective:');

            $this->assertNotFalse($position, "Camera angle [{$key}] is missing from the prompt.");
            $this->assertNotFalse($objective, 'Objective clause must stay in the prompt.');
            $this->assertLessThan($objective, $position, "Camera angle [{$key}] must be front-loaded before the Objective clause.");
            $this->assertNotEmpty($angle['motion'] ?? null, "Camera angle [{$key}] must define a video motion clause.");
        }
    }

    public function test_video_motion_follows_the_selected_camera_angle(): void
    {
        foreach (config('creative.camera_angles') as $key => $angle) {
            $prompt = $this->engine->prompt([
                'product' => 'عطر لوکس',
                'objective' => 'engagement',
                'visual_direction' => 'cinematic',
                'environment' => 'studio',
                'camera_angle' => $key,
            ], CreativeFormat::InstagramReel);

            $this->assertStringContainsString('Dynamic motion: '.$angle['motion'].',', $prompt, "Video motion for [{$key}] must match its angle.");
            $this->assertStringNotContainsString('Dynamic motion: smooth cinematic camera pan,', $prompt, "Generic pan must not override the [{$key}] angle.");
        }
    }

    public function test_video_without_camera_angle_keeps_the_generic_pan(): void
    {
        $prompt = $this->engine->prompt([
            'product' => 'عطر لوکس',
            'objective' => 'engagement',
            'visual_direction' => 'cinematic',
            'environment' => 'studio',
            'camera_angle' => null,
        ], CreativeFormat::InstagramReel);

        $this->assertStringNotContainsString('Camera angle (locked):', $prompt);
        $this->assertStringContainsString('Dynamic motion: smooth cinematic camera pan, fluid atmospheric movement, premium brand reel aesthetic, 4K render.', $prompt);
    }

    public function test_prompt_skips_default_or_none_scene_controls(): void
    {
        $brief = [
            'product' => 'عطر لوکس',
            'objective' => 'sales',
            'visual_direction' => 'luxury',
            'environment' => 'luxury',
            'surface' => 'default',
            'props' => 'none',
            'camera_angle' => null,
            'lighting_setup' => null,
        ];

        $prompt = $this->engine->prompt($brief, CreativeFormat::InstagramPost);

        $this->assertStringNotContainsString('Surface pedestal:', $prompt);
        $this->assertStringNotContainsString('Accents and props:', $prompt);
        $this->assertStringNotContainsString('Camera composition:', $prompt);
        $this->assertStringNotContainsString('Camera angle (locked):', $prompt);
        $this->assertStringNotContainsString('Studio lighting:', $prompt);
        $this->assertStringNotContainsString('Character consistency (locked):', $prompt);
    }

    public function test_auto_best_provides_dynamic_character_consistency(): void
    {
        $product = new Product(['name' => 'عینک دودی']);
        $result = $this->engine->autoBest($product, CreativeGoal::Sales);

        $this->assertSame('dynamic', $result['character_consistency']);
    }

    public function test_brief_includes_character_consistency_defaulting_to_dynamic(): void
    {
        $product = new Product(['name' => 'تی‌شرت ورزشی']);
        $brief = $this->engine->brief($product, [
            'goal' => CreativeGoal::Sales->value,
            'style' => CreativeStyle::Colorful->value,
            'environment' => 'urban',
            'format' => CreativeFormat::InstagramPost->value,
        ]);

        $this->assertSame('dynamic', $brief['character_consistency']);
    }

    public function test_brief_honors_explicit_locked_character_consistency(): void
    {
        $product = new Product(['name' => 'کت و شلوار']);
        $brief = $this->engine->brief($product, [
            'goal' => CreativeGoal::Branding->value,
            'style' => CreativeStyle::Luxury->value,
            'environment' => 'studio',
            'format' => CreativeFormat::InstagramPost->value,
            'character_consistency' => 'locked',
        ]);

        $this->assertSame('locked', $brief['character_consistency']);
    }

    public function test_prompt_cleanly_incorporates_locked_character_consistency(): void
    {
        $brief = [
            'product' => 'کت مردانه برند',
            'objective' => 'branding',
            'visual_direction' => 'luxury',
            'environment' => 'studio',
            'character_consistency' => 'locked',
        ];

        $prompt = $this->engine->prompt($brief, CreativeFormat::InstagramPost);

        $expectedDirective = 'Character consistency (locked): strict character consistency, identical facial features, same model identity across generations, preserve facial structure and ethnicity, zero character drift.';
        $this->assertStringContainsString($expectedDirective, $prompt);

        // Verify it is front-loaded right after opening product sentence
        $position = strpos($prompt, $expectedDirective);
        $objective = strpos($prompt, 'Objective:');
        $this->assertNotFalse($position);
        $this->assertNotFalse($objective);
        $this->assertLessThan($objective, $position, 'Character consistency directive must be front-loaded before Objective.');
    }

    public function test_prompt_omits_character_consistency_clause_when_dynamic_or_null(): void
    {
        $briefDynamic = [
            'product' => 'کت مردانه برند',
            'objective' => 'branding',
            'visual_direction' => 'luxury',
            'environment' => 'studio',
            'character_consistency' => 'dynamic',
        ];

        $promptDynamic = $this->engine->prompt($briefDynamic, CreativeFormat::InstagramPost);
        $this->assertStringNotContainsString('Character consistency', $promptDynamic);

        $briefNull = [
            'product' => 'کت مردانه برند',
            'objective' => 'branding',
            'visual_direction' => 'luxury',
            'environment' => 'studio',
            'character_consistency' => null,
        ];

        $promptNull = $this->engine->prompt($briefNull, CreativeFormat::InstagramPost);
        $this->assertStringNotContainsString('Character consistency', $promptNull);
    }
}
