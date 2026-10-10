<?php

namespace Tests\Unit\Creative;

use App\Domains\Brand\Models\BrandKit;
use App\Domains\Creative\Enums\CreativeFormat;
use App\Domains\Creative\Enums\CreativeGoal;
use App\Domains\Creative\Enums\CreativeStyle;
use App\Domains\Creative\Services\CreativeEngine;
use App\Domains\Creative\Services\SeasonThemeService;
use App\Domains\Products\Models\Product;
use Illuminate\Support\Facades\Http;
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

    public function test_brief_reads_the_studios_persian_prose_to_the_image_model_in_english(): void
    {
        config(['ai.providers.google.api_key' => 'test-key']);

        // The translation passes the source through after the prompt's own
        // header, so this test can see exactly which line was handed over.
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => function ($request): mixed {
                $sent = json_decode($request->body(), true);
                $lines = explode("\n\n", (string) ($sent['contents'][0]['parts'][0]['text'] ?? ''), 2);

                return Http::response([
                    'candidates' => [['content' => ['parts' => [['text' => 'English of '.($lines[1] ?? '')]]]]],
                ]);
            },
        ]);

        $engine = new CreativeEngine(app(SeasonThemeService::class));
        $product = new Product(['name' => 'عطر سلطنتی', 'description' => 'رایحه چرم و وانیل']);
        $brand = new BrandKit(['tone' => 'گرم و صمیمی', 'tagline' => 'ساخت ایران']);

        $brief = $engine->brief($product, [
            'goal' => CreativeGoal::Sales->value,
            'style' => CreativeStyle::Luxury->value,
            'environment' => 'studio',
            'format' => CreativeFormat::InstagramPost->value,
            'custom_prompt' => 'روی سنگ مرمر مشکی',
        ], $brand);

        $this->assertSame('English of رایحه چرم و وانیل', $brief['description'], 'The product description is prose the model has to read.');
        $this->assertSame('English of روی سنگ مرمر مشکی', $brief['custom_prompt'], 'The scene the studio wrote is prose too.');
        $this->assertSame('English of گرم و صمیمی', $brief['brand']['tone'], 'A tone the model must be able to hear.');

        $this->assertSame('عطر سلطنتی', $brief['product'], 'A name is identity, not prose.');
        $this->assertSame('ساخت ایران', $brief['brand']['tagline'], 'A tagline is rendered on the image, so it keeps its language.');
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

        $expected = 'Create a professional commercial advertising visual for عطر سلطنتی. Objective: sales. Style: luxury. Environment: luxury. Square 1:1 frame. High-end commercial production, photorealistic, cinematic lighting, ultra-sharp detail, preserve original product design and packaging, no distracting watermarks, strictly clean composition, no text, no words, no letters, no typography, no fake labels, no pseudo-writing, no artificial watermark or signage, keep the original product packaging and label artwork exactly as it is.';

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

        $expected = 'Create a professional commercial advertising visual for عطر سلطنتی. Objective: sales. Style: luxury. Environment: luxury. Square 1:1 frame. Custom scene details: روی صخره مرطوب بازالت، میان گل‌های ارکیده صورتی. High-end commercial production, photorealistic, cinematic lighting, ultra-sharp detail, preserve original product design and packaging, no distracting watermarks, strictly clean composition, no text, no words, no letters, no typography, no fake labels, no pseudo-writing, no artificial watermark or signage, keep the original product packaging and label artwork exactly as it is.';

        $this->assertSame($expected, $prompt);
    }

    public function test_the_persian_scene_line_describes_the_frame_the_engine_was_asked_for(): void
    {
        // No `format` in the brief: the sentence itself is built from the
        // format argument, so the Persian line beside it has to describe that
        // same frame instead of whatever default the template falls back to.
        $brief = [
            'product' => 'عطر لوکس',
            'objective' => 'sales',
            'visual_direction' => 'luxury',
            'environment' => 'studio',
            'target_ai' => 'chatgpt',
        ];

        $reel = $this->engine->prompt($brief, CreativeFormat::InstagramReel);
        $post = $this->engine->prompt($brief, CreativeFormat::InstagramPost);

        $this->assertStringContainsString('قاب عمودی ۹:۱۶ با حرکت دوربین برای ریلز', $reel, 'The scene line must follow the frame the engine was given.');
        $this->assertStringNotContainsString('قاب مربعی', $reel, 'A vertical shot may not be described as a square one.');
        $this->assertStringContainsString('قاب مربعی ۱:۱ برای پست اینستاگرام', $post, 'The square frame still describes itself as square.');
    }

    public function test_prompt_describes_the_product_next_to_its_name(): void
    {
        $brief = [
            'product' => 'کیف چرم دست‌دوز',
            'description' => 'کیف دوشی چرم طبیعی با دوخت دستی',
            'objective' => 'branding',
            'visual_direction' => 'natural',
            'environment' => 'nature',
            'custom_prompt' => null,
        ];

        $prompt = $this->engine->prompt($brief, CreativeFormat::InstagramPost);

        // The only sentence that says what the product looks like must reach
        // the model instead of stopping at the name it cannot read.
        $this->assertStringContainsString(
            'visual for کیف چرم دست‌دوز. کیف دوشی چرم طبیعی با دوخت دستی. Objective:',
            $prompt
        );

        // A brief without a description adds nothing at all - no orphan full
        // stop, no empty clause.
        $bare = $this->engine->prompt(['product' => 'عطر سلطنتی', 'objective' => 'sales', 'visual_direction' => 'luxury', 'environment' => 'luxury'], CreativeFormat::InstagramPost);
        $this->assertStringContainsString('visual for عطر سلطنتی. Objective:', $bare);
    }

    public function test_prompt_quotes_the_frame_not_the_format_key(): void
    {
        $brief = ['product' => 'عطر سلطنتی', 'objective' => 'sales', 'visual_direction' => 'luxury', 'environment' => 'luxury'];

        $square = $this->engine->prompt($brief, CreativeFormat::InstagramPost);
        $vertical = $this->engine->prompt($brief, CreativeFormat::InstagramReel);

        $this->assertStringContainsString('Environment: luxury. Square 1:1 frame.', $square);
        $this->assertStringContainsString('Environment: luxury. Vertical 9:16 frame.', $vertical);

        // The studio's own key is a screen name, not framing instructions.
        foreach ([$square, $vertical] as $prompt) {
            $this->assertStringNotContainsString('instagram_post)', $prompt);
            $this->assertStringNotContainsString('instagram_reel)', $prompt);
            $this->assertStringNotContainsString('ratio (', $prompt);
        }
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
        $this->assertStringContainsString('Camera: extreme close-up macro shot with shallow depth of field, camera tight on the product surface revealing texture and craftsmanship.', $prompt);
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

            $directive = sprintf('Camera: %s.', $angle['prompt']);
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

        $this->assertStringNotContainsString('Camera:', $prompt);
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
        $this->assertStringNotContainsString('Camera:', $prompt);
        $this->assertStringNotContainsString('Studio lighting:', $prompt);
        $this->assertStringNotContainsString('Character consistency:', $prompt);
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

        $expectedDirective = 'Character consistency: '.config('creative.character_consistencies.locked.prompt').'.';
        $this->assertStringContainsString($expectedDirective, $prompt);

        // The directive is a field of the brief, not a screen state: the
        // studio's own key never reaches the model, and neither does a trait
        // it would have to guess at - naming one to "preserve" only invites a
        // safety filter on a shot that may not hold a person at all.
        $this->assertStringNotContainsString('Character consistency (', $prompt);
        $this->assertStringNotContainsString('ethnicity', $prompt);

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

    public function test_character_clause_follows_the_config_catalogue_not_a_hardcoded_key(): void
    {
        // A third state must work without touching CreativeEngine: only the
        // catalogue decides which states speak and what they say.
        config(['creative.character_consistencies.semi_locked' => [
            'key' => 'semi_locked',
            'label' => 'نیمه‌قفل',
            'prompt' => 'consistent model identity with minor styling variation',
            'icon' => '🔐',
        ]]);

        $brief = [
            'product' => 'کت مردانه برند',
            'objective' => 'branding',
            'visual_direction' => 'luxury',
            'environment' => 'studio',
            'character_consistency' => 'semi_locked',
        ];

        $prompt = $this->engine->prompt($brief, CreativeFormat::InstagramPost);

        $this->assertStringContainsString(
            ' Character consistency: consistent model identity with minor styling variation.',
            $prompt,
            'The directive title and body must come from the configured state.'
        );

        // A key that is not in the catalogue says nothing at all.
        $unknown = $brief;
        $unknown['character_consistency'] = 'removed_state';
        $this->assertStringNotContainsString(
            'Character consistency',
            $this->engine->prompt($unknown, CreativeFormat::InstagramPost)
        );
    }

    public function test_the_default_character_state_comes_from_one_config_key(): void
    {
        $key = (string) config('creative.character_consistency_default');
        $this->assertArrayHasKey(
            $key,
            config('creative.character_consistencies'),
            'The configured default must name a real catalogue state.'
        );

        // One key has to move every layer at once: auto best, the brief and
        // the clause that speaks when a request carries no choice at all.
        config(['creative.character_consistency_default' => 'locked']);

        $product = new Product(['name' => 'کت مردانه']);

        $this->assertSame('locked', $this->engine->autoBest($product, CreativeGoal::Sales)['character_consistency']);

        $brief = $this->engine->brief($product, [
            'goal' => CreativeGoal::Sales->value,
            'style' => CreativeStyle::Luxury->value,
            'format' => CreativeFormat::InstagramPost->value,
            'environment' => 'studio',
        ]);

        $this->assertSame('locked', $brief['character_consistency']);
        $this->assertStringContainsString(
            'Character consistency: if a person appears in the frame',
            $this->engine->prompt($brief, CreativeFormat::InstagramPost)
        );
    }

    public function test_prompt_forbids_generated_text_but_keeps_the_products_own_label(): void
    {
        $prompt = $this->engine->prompt([
            'product' => 'عطر سلطنتی',
            'objective' => 'sales',
            'visual_direction' => 'luxury',
            'environment' => 'luxury',
            'custom_prompt' => null,
        ], CreativeFormat::InstagramPost);

        $this->assertStringContainsString(
            ' '.config('creative.text_suppression.strict').'.',
            $prompt,
            'A brief without wording must end on the strict suppression directive.'
        );
        $this->assertStringNotContainsString('no unwanted text', $prompt, 'The old weak tail must be gone.');
        $this->assertStringNotContainsString(
            (string) config('creative.text_suppression.permissive'),
            $prompt,
            'Nothing grants written wording when the brief asked for none.'
        );
    }

    public function test_brand_tagline_switches_the_suppression_to_the_permissive_directive(): void
    {
        $prompt = $this->engine->prompt([
            'product' => 'عطر سلطنتی',
            'objective' => 'sales',
            'visual_direction' => 'luxury',
            'environment' => 'luxury',
            'custom_prompt' => null,
            'brand' => ['primary_color' => '#111827', 'tagline' => 'حس سلطنتی'],
        ], CreativeFormat::InstagramPost);

        $this->assertStringContainsString(' Keep the brand tagline "حس سلطنتی" legible in the frame.', $prompt);
        $this->assertStringContainsString(
            ' '.config('creative.text_suppression.permissive').'.',
            $prompt,
            'The strict tail would contradict the tagline the brand clause keeps legible.'
        );
        $this->assertStringNotContainsString(' '.config('creative.text_suppression.strict').'.', $prompt);
    }

    public function test_custom_scene_details_asking_for_wording_switch_to_the_permissive_directive(): void
    {
        $prompt = $this->engine->prompt([
            'product' => 'عطر سلطنتی',
            'objective' => 'sales',
            'visual_direction' => 'luxury',
            'environment' => 'luxury',
            'custom_prompt' => 'شعار برند را زیر محصول بنویس',
        ], CreativeFormat::InstagramPost);

        $this->assertStringContainsString(' '.config('creative.text_suppression.permissive').'.', $prompt);
        $this->assertStringNotContainsString(' '.config('creative.text_suppression.strict').'.', $prompt);
    }

    public function test_wording_detection_only_matches_whole_words(): void
    {
        // «متناسب» hides «متن» and context hides text: neither asks for wording,
        // so the strict directive must survive both languages.
        $prompt = $this->engine->prompt([
            'product' => 'عطر سلطنتی',
            'objective' => 'sales',
            'visual_direction' => 'luxury',
            'environment' => 'luxury',
            'custom_prompt' => 'چیدمان متناسب با فصل و پس‌زمینه context محور',
        ], CreativeFormat::InstagramPost);

        $this->assertStringContainsString(' '.config('creative.text_suppression.strict').'.', $prompt);
        $this->assertStringNotContainsString(' '.config('creative.text_suppression.permissive').'.', $prompt);
    }
}
