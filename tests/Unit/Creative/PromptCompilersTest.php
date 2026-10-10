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
        foreach ([null, '', '   ', 'unknown-model', 'GENERIC', 'gpt5-not-configured'] as $key) {
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

    public function test_every_direction_compiler_carries_the_same_image_prompt(): void
    {
        [$brief, $format] = $this->briefs()[1];
        $image = $this->factory->for('generic')->compile($brief, $format);
        $clauses = $this->factory->clauses();

        $openings = [];

        foreach (['chatgpt', 'claude', 'deepseek', 'grok'] as $key) {
            $compiled = $this->factory->for($key)->compile($brief, $format);
            // Claude wraps everything in tags, so decode it before comparing.
            $readable = $key === 'claude' ? html_entity_decode($compiled, ENT_QUOTES | ENT_XML1, 'UTF-8') : $compiled;

            $this->assertStringContainsString(
                $image,
                $readable,
                "[{$key}] must hand over the image prompt unchanged: a direction target may not re-render the scene."
            );

            $shared = [
                'camera' => trim($clauses->camera($brief)),
                'character' => trim($clauses->character($brief)),
                'custom' => trim($clauses->custom($brief)),
                'brand' => trim($clauses->brand($brief['brand'])),
                'campaign' => trim($clauses->campaign($brief['campaign'])),
            ];

            if ($key === 'claude') {
                // Placed one tag at a time instead of joined into a sentence.
                foreach ($clauses->sceneParts($brief) as $part) {
                    $shared['scene part'] = trim($part);
                }
            } else {
                $shared['scene'] = trim($clauses->scene($brief));
                $shared['summary'] = $clauses->sceneSummary($brief);
            }

            foreach ($shared as $name => $clause) {
                $this->assertNotSame('', $clause, "[{$key}] has nothing to check for [{$name}].");
                $this->assertStringContainsString($clause, $readable, "[{$key}] dropped the shared [{$name}] clause.");
            }

            $openings[$key] = explode("\n", $compiled)[0];
        }

        $this->assertCount(4, array_unique($openings), 'Each compiler must open with its own structure; four copies would be one compiler in four files.');
    }

    public function test_the_chatgpt_compiler_frames_a_bilingual_brief(): void
    {
        [$brief, $format] = $this->briefs()[1];
        $compiled = $this->factory->for('chatgpt')->compile($brief, $format);

        foreach (['# CAMPAIGN BRIEF', '# SCENE', '# IMAGE PROMPT (ready to paste into any image model)', '# CAPTION & HOOK (فارسی)'] as $section) {
            $this->assertStringContainsString($section, $compiled, "The ChatGPT brief is missing the [{$section}] section.");
        }

        $this->assertStringContainsString('- Product: '.$brief['product'], $compiled);
        $this->assertStringContainsString('- Audience: Iranian social commerce shoppers', $compiled, 'A brief without an audience still names the one the engine uses.');
        $this->assertStringContainsString('- قلاب: چرا '.$brief['product'].'؟ ', $compiled, 'The hook is written in Persian for the Persian caption.');
        $this->assertStringContainsString('- کپشن پیشنهادی: «', $compiled);
        $this->assertStringContainsString($brief['brand']['tagline'], $compiled, 'The brand tagline belongs in the caption.');
        $this->assertStringContainsString($this->factory->clauses()->sceneSummary($brief), $compiled, 'The caption section must open with the studio scene line.');
    }

    public function test_the_claude_compiler_emits_well_formed_xml(): void
    {
        [$brief, $format] = $this->briefs()[1];
        $compiled = $this->factory->for('claude')->compile($brief, $format);

        $this->assertStringStartsWith('<claude_prompt>', $compiled);
        $this->assertStringEndsWith('</claude_prompt>', $compiled);

        foreach (['product_context', 'creative_direction', 'visual_style', 'lighting_and_atmosphere', 'output_format'] as $section) {
            $this->assertStringContainsString("<{$section}>", $compiled, "Claude section [{$section}] is required by the brief.");
        }

        foreach (['surface', 'props', 'lighting', 'environment', 'aspect_ratio'] as $leaf) {
            $this->assertStringContainsString("<{$leaf}>", $compiled, "Scene leaf [{$leaf}] must be readable on its own.");
        }

        // Nesting, not just presence: a tag soup would satisfy contains().
        preg_match_all('/<(\/)?([a-z_]+)>/', $compiled, $matches, PREG_SET_ORDER);
        $stack = [];
        foreach ($matches as $match) {
            if ($match[1] === '/') {
                $this->assertNotEmpty($stack, 'Closing tag ['.$match[2].'] has nothing open.');
                $this->assertSame(array_pop($stack), $match[2], 'Tags must nest, not overlap.');
            } else {
                $stack[] = $match[2];
            }
        }
        $this->assertSame([], $stack, 'Every opened tag must be closed.');
    }

    public function test_the_claude_compiler_escapes_what_the_user_typed(): void
    {
        $brief = [
            'product' => 'عطر "ویژه" & محدود',
            'description' => 'نسخه تابستانی <جدید>',
            'objective' => 'sales',
            'visual_direction' => 'luxury',
            'environment' => 'studio',
            'custom_prompt' => 'با نور کم <لرزان>',
        ];
        $compiled = $this->factory->for('claude')->compile($brief, CreativeFormat::InstagramPost);

        $this->assertStringContainsString('&quot;', $compiled, 'Quotes must not be able to end an attribute or a value.');
        $this->assertStringContainsString('&amp;', $compiled, 'An ampersand must not start an entity.');
        $this->assertStringNotContainsString('<جدید>', $compiled, 'User markup must never reach the document.');
        $this->assertStringContainsString('عطر &quot;ویژه&quot; &amp; محدود', $compiled);
        $this->assertStringContainsString(
            'عطر "ویژه" & محدود',
            html_entity_decode($compiled, ENT_QUOTES | ENT_XML1, 'UTF-8'),
            'After decoding, the reader still sees exactly what the user typed.'
        );
    }

    public function test_the_deepseek_compiler_reasons_before_it_writes(): void
    {
        [$brief, $format] = $this->briefs()[1];
        $compiled = $this->factory->for('deepseek')->compile($brief, $format);

        $this->assertStringStartsWith('CHAIN OF THOUGHT', $compiled);

        $position = -1;
        foreach (['STEP 1 - READ THE AUDIENCE', 'STEP 2 - FIND THE WINNING ANGLE', 'STEP 3 - LOCK THE SCENE', 'STEP 4 - WRITE FOR CONVERSION', 'FINAL IMAGE PROMPT'] as $step) {
            $found = strpos($compiled, $step);
            $this->assertNotFalse($found, "The chain of thought is missing [{$step}].");
            $this->assertGreaterThan($position, $found, "[{$step}] must come in order: no prompt before the angle is chosen.");
            $position = $found;
        }

        $this->assertStringContainsString('- Goal effect (فارسی): ', $compiled, 'The Iranian audience insight is quoted from the effects catalogue.');
        $this->assertStringContainsString('- Angle to argue: ', $compiled);
        $this->assertStringContainsString('- Text policy: ', $compiled);
    }

    public function test_the_grok_compiler_keeps_its_direction_short(): void
    {
        [$brief, $format] = $this->briefs()[1];
        $compiled = $this->factory->for('grok')->compile($brief, $format);

        $this->assertStringStartsWith('VIRAL DIRECTION', $compiled);
        foreach (['THE CONCEPT', 'LOCKED DECISIONS', 'IMAGE PROMPT', 'CAPTION HOOK (فارسی)'] as $section) {
            $this->assertStringContainsString($section, $compiled, "The Grok brief is missing [{$section}].");
        }
        $this->assertStringContainsString('«چرا '.$brief['product'].'؟ ', $compiled);

        // Its own voice stays clipped; the scene and the clauses below it are
        // quoted material the studio wrote, not Grok's prose.
        $ownVoice = strstr($compiled, '- Scene (فارسی)', true);
        $this->assertNotFalse($ownVoice);
        foreach (explode("\n", $ownVoice) as $line) {
            $this->assertLessThan(200, mb_strlen($line), 'Grok speaks in clipped lines: ['.$line.']');
        }
    }

    public function test_the_persian_scene_summary_reads_like_the_studio_bar(): void
    {
        $clauses = $this->factory->clauses();

        [$brief] = $this->briefs()[1];
        $full = $clauses->sceneSummary($brief);

        $this->assertDoesNotMatchRegularExpression('/\{\w+\}/', $full, 'Every slot of the template must resolve.');
        $this->assertStringContainsString($brief['product'].' شما', $full, 'The line names the product the way the studio does.');
        $this->assertStringContainsString((string) config('creative.effects.surface.'.$brief['surface']), $full, 'A chosen control is described by its own effect clause.');
        $this->assertStringNotContainsString('null', $full, 'A missing value must never leak as code.');

        $empty = $clauses->sceneSummary(['product' => 'عطر تست']);
        $defaults = PromptClauses::studioDefaults();

        $this->assertDoesNotMatchRegularExpression('/\{\w+\}/', $empty);
        $this->assertStringContainsString((string) config('creative.effects.surface.'.$defaults['surface']), $empty, 'Without a selection the server quotes what the studio pre-checks.');
        $this->assertStringContainsString((string) config('creative.effects.props.'.$defaults['props']), $empty);
        $this->assertStringNotContainsString('null', $empty);

        // The browser pre-checks its own defaults; if the two drift, the
        // caption and the canvas would describe two different scenes.
        $script = (string) file_get_contents(resource_path('js/app.js'));
        foreach ($defaults as $control => $value) {
            if ($control === 'character_consistency') {
                $this->assertStringContainsString('|| defaultCharacterKey', $script, 'Character state comes from the server-rendered default in both places.');
                $this->assertSame(config('creative.character_consistency_default'), $value);

                continue;
            }

            $this->assertStringContainsString("|| '{$value}'", $script, "The studio must pre-check [{$value}] for [{$control}], the same value the server falls back to.");
        }
    }

    public function test_the_image_compilers_keep_the_scene_and_the_text_policy(): void
    {
        [$brief, $format] = $this->briefs()[1];
        $clauses = $this->factory->clauses();

        // This brief carries a tagline, so every target relaxes its own way.
        $policies = [
            'imagen' => rtrim(trim($clauses->textSuppression($brief)), '.'),
            'flux' => rtrim(trim($clauses->textSuppression($brief)), '.'),
            'midjourney' => '--no watermark',
            'stable_diffusion' => 'Negative prompt: ',
        ];

        foreach ($policies as $key => $policy) {
            $compiled = $this->factory->for($key)->compile($brief, $format);

            foreach ($clauses->sceneParts($brief) as $part) {
                $this->assertStringContainsString(
                    rtrim(trim($part), '.'),
                    $compiled,
                    "[{$key}] dropped the scene clause: the target may not re-imagine the scene."
                );
            }

            $this->assertStringContainsString($policy, $compiled, "[{$key}] must carry the text policy in its own grammar.");
            $this->assertStringContainsString('packaging', $compiled, "[{$key}] must keep the original packaging intact.");
            $this->assertStringNotContainsString('High-end commercial production', $compiled, "[{$key}] describes the frame, not the aspiration.");
            $this->assertStringNotContainsString('ultra-sharp detail', $compiled, "[{$key}] must not reuse the generic hype tail.");
        }
    }

    public function test_the_imagen_compiler_speaks_in_photographic_terms(): void
    {
        [$brief, $format] = $this->briefs()[1];
        $clauses = $this->factory->clauses();
        $compiled = $this->factory->for('imagen')->compile($brief, $format);

        $this->assertStringStartsWith($brief['product'].', commercial product photograph', $compiled);
        $this->assertStringContainsString('85mm lens at f/4', $compiled, 'The lens specification is the point of this target.');
        $this->assertStringContainsString('three-point lighting', $compiled, 'Studio lighting vocabulary instead of slogans.');
        $this->assertStringContainsString('no wide-angle distortion', $compiled, 'An unambiguous angle means an undistorted package.');
        $this->assertStringContainsString(PromptClauses::body($clauses->camera($brief)), $compiled, 'The chosen framing survives.');
        $this->assertStringContainsString('Square 1:1 frame.', $compiled);
        $this->assertStringNotContainsString("\n", $compiled, 'An image model receives a single prompt.');
    }

    public function test_the_midjourney_compiler_ends_with_its_parameters(): void
    {
        [$brief] = $this->briefs()[1];
        $compiler = $this->factory->for('midjourney');

        $post = $compiler->compile($brief, CreativeFormat::InstagramPost);
        $reel = $compiler->compile($brief, CreativeFormat::InstagramReel);

        // This brief carries a tagline, so Midjourney may not be told to ban
        // text - it would drop the line the brand asked for.
        $this->assertStringEndsWith('--ar 1:1 --style raw --v 6.1 --no watermark', $post);
        $this->assertStringEndsWith('--ar 9:16 --style raw --v 6.1 --no watermark', $reel, 'The selected format drives --ar.');

        // A brief that asked for no wording of its own keeps the full ban.
        [$plain] = $this->briefs()[2];
        $this->assertStringEndsWith(
            '--ar 1:1 --style raw --v 6.1 --no text, watermark',
            $compiler->compile($plain, CreativeFormat::InstagramPost),
            'Nothing was requested, so nothing may be written.'
        );

        $this->assertStringNotContainsString("\n", $post, 'A compact prompt is one line of phrases.');
        $this->assertStringNotContainsString('Camera:', $post, 'Labels are dropped; the framing itself stays.');
        $this->assertStringNotContainsString('Character consistency:', $post);
        $this->assertStringContainsString(
            ucfirst((string) config('creative.camera_angles.hero_shot.prompt')),
            $post,
            'The framing survives even though its directive label does not.'
        );
        $this->assertStringContainsString(', ', $post, 'Phrases are comma-separated, not written as sentences.');
    }

    public function test_the_flux_compiler_stays_literal(): void
    {
        [$brief, $format] = $this->briefs()[1];
        $clauses = $this->factory->clauses();
        $compiled = $this->factory->for('flux')->compile($brief, $format);

        $this->assertStringStartsWith($brief['product'].' is the single subject of the frame.', $compiled);
        $this->assertStringContainsString(
            'natural styling in a nature environment.',
            $compiled,
            'The brief is folded into prose instead of printed as fields.'
        );

        foreach (['High-end commercial production', 'ultra-sharp detail', 'premium brand reel aesthetic', 'masterpiece'] as $cliche) {
            $this->assertStringNotContainsString($cliche, $compiled, 'FLUX renders sentences, not slogans: ['.$cliche.'].');
        }

        $this->assertStringContainsString('Square 1:1 frame.', $compiled);
        $this->assertStringEndsWith(
            rtrim(trim($clauses->textSuppression($brief)), '.').'.',
            $compiled,
            'The text policy stays the last word.'
        );
    }

    public function test_the_stable_diffusion_compiler_splits_positive_and_negative(): void
    {
        [$brief, $format] = $this->briefs()[1];
        $compiled = $this->factory->for('stable_diffusion')->compile($brief, $format);

        $this->assertStringStartsWith('Positive prompt: ', $compiled);
        $this->assertStringContainsString("\nNegative prompt: ", $compiled);
        $this->assertStringContainsString('(masterpiece:1.2)', $compiled, 'The weighting syntax the model expects.');
        $this->assertStringContainsString('(photorealistic:1.1)', $compiled);

        // This brief carries a tagline: a negative that bans `text` would
        // cancel the wording the positive block promises to render.
        $this->assertStringNotContainsString('(text:1.2)', $compiled, 'The negative must not undo the positive.');
        $this->assertStringNotContainsString('(font:1.1)', $compiled, 'Neither may the keyword catalogue that bans it.');
        $this->assertStringContainsString('(watermark:1.1)', $compiled, 'A watermark is never wanted, wording or not.');

        // A brief that asked for nothing of the sort keeps the whole
        // catalogue out of the frame.
        [$plain] = $this->briefs()[2];
        $strict = $this->factory->for('stable_diffusion')->compile($plain, $format);
        $this->assertStringContainsString('(text:1.2)', $strict, 'Rendered wording is the first thing to avoid.');
        $this->assertStringContainsString('(font:1.1)', $strict, 'The negative list is built from the studio keyword catalogue.');

        preg_match_all('/\([^()]+:\d\.\d\)/', $compiled, $matches);
        $this->assertGreaterThan(6, count($matches[0]), 'Both blocks must be weighted, not flat lists.');

        $this->assertDoesNotMatchRegularExpression(
            '/\((متن|نوشته|حروف)/u',
            $compiled,
            'A Persian negative would be ignored by the sampler; the English half is the one that applies.'
        );
        $this->assertStringNotContainsString('High-end commercial production', $compiled);
    }

    public function test_the_factory_owns_every_catalogue_target(): void
    {
        $catalogue = array_keys((array) config('creative.target_ais'));

        $this->assertSame(
            $catalogue,
            $this->factory->keys(),
            'The catalogue and the factory must name the same targets in the same order: a target without a compiler would silently fall back to the generic prompt.'
        );

        [$brief] = $this->briefs()[1];

        foreach ((array) config('creative.target_ais') as $key => $entry) {
            $compiler = $this->factory->for($key);

            $this->assertSame($key, $compiler->key(), "[{$key}] resolved to a compiler claiming another identity.");

            $format = in_array('image', (array) $entry['types'], true)
                ? CreativeFormat::InstagramPost
                : CreativeFormat::InstagramReel;

            $compiled = $compiler->compile($brief, $format);

            $this->assertNotSame('', trim($compiled), "[{$key}] compiled an empty prompt.");
            $this->assertStringContainsString($brief['product'], $compiled, "[{$key}] must name the product it is written for.");
        }
    }

    public function test_the_veo_compiler_leads_with_the_camera_move(): void
    {
        [$brief, $format] = $this->briefs()[2];
        $clauses = $this->factory->clauses();
        $compiled = $this->factory->for('veo')->compile($brief, $format);

        $motion = trim($clauses->cameraMotion($brief));
        $this->assertStringStartsWith(ucfirst($motion).'.', $compiled, 'Veo front-loads the movement: a move written last is read as an afterthought.');

        $productAt = strpos($compiled, $brief['product']);
        $this->assertNotFalse($productAt);
        $this->assertLessThan($productAt, strpos($compiled, $motion), 'The move opens the prompt, the product follows it.');

        $this->assertStringContainsString('The subject stays still while the atmosphere moves.', $compiled);
        $this->assertStringContainsString(trim($clauses->camera($brief)), $compiled);
        $this->assertStringContainsString(rtrim(trim($clauses->scene($brief)), '.'), $compiled);
        $this->assertStringContainsString('Vertical 9:16 frame.', $compiled);
        $this->assertStringEndsWith(rtrim(trim($clauses->textSuppression($brief)), '.').'.', $compiled, 'The text policy closes the shot.');
    }

    public function test_the_runway_compiler_labels_the_departments(): void
    {
        [$brief, $format] = $this->briefs()[2];
        $clauses = $this->factory->clauses();
        $compiled = $this->factory->for('runway')->compile($brief, $format);

        $this->assertStringStartsWith('DIRECTING NOTES', $compiled);
        foreach (['- Camera: ', '- Movement: ', '- Light: ', 'SHOT', 'PROMPT FOR GEN-3'] as $line) {
            $this->assertStringContainsString($line, $compiled, "The shot sheet is missing [{$line}].");
        }

        $this->assertStringContainsString($clauses->cameraMotion($brief), $compiled, 'The move is written in the notes.');
        $this->assertStringContainsString(trim($clauses->camera($brief)), $compiled);
        $this->assertLessThan(
            strpos($compiled, 'PROMPT FOR GEN-3'),
            strpos($compiled, '- Light: '),
            'The departments come before the paste block.'
        );
        $this->assertStringContainsString(
            $this->factory->for('generic')->compile($brief, $format),
            $compiled,
            'The paste block is the very video prompt Hale would send itself.'
        );

        // A brief that arrived without a camera or a light still gets a full
        // sheet, falling back to what the studio pre-checks - and the note
        // still carries its label exactly once.
        $bare = $this->factory->for('runway')->compile(
            ['product' => 'عطر تست', 'objective' => 'sales', 'visual_direction' => 'luxury', 'environment' => 'studio'],
            CreativeFormat::InstagramReel
        );

        $this->assertStringContainsString('never looking down or up.', $bare, 'The empty camera note falls back to the selection the studio pre-checks.');
        $this->assertStringContainsString('diffused even illumination.', $bare, 'The empty light note falls back to the selection the studio pre-checks.');
        $this->assertStringNotContainsString('- Camera: Camera:', $bare, 'The department label must not be repeated inside its own note.');
        $this->assertStringNotContainsString('- Light: Lighting:', $bare, 'The department label must not be repeated inside its own note.');
    }

    public function test_the_video_and_edit_compilers_keep_the_shared_clauses(): void
    {
        [$brief] = $this->briefs()[1];
        $clauses = $this->factory->clauses();

        foreach (['veo', 'runway', 'gemini_edit'] as $key) {
            $format = in_array('image', (array) config("creative.target_ais.{$key}.types"), true)
                ? CreativeFormat::InstagramPost
                : CreativeFormat::InstagramReel;

            $compiled = $this->factory->for($key)->compile($brief, $format);

            foreach ($clauses->sceneParts($brief) as $part) {
                $this->assertStringContainsString(rtrim(trim($part), '.'), $compiled, "[{$key}] lost a scene clause.");
            }

            $this->assertStringContainsString(trim($clauses->custom($brief)), $compiled, "[{$key}] lost the user's own scene details.");
            $this->assertStringContainsString(trim($clauses->brand($brief['brand'])), $compiled, "[{$key}] lost the brand identity.");
            $this->assertStringContainsString(
                rtrim(trim($clauses->textSuppression($brief)), '.'),
                $compiled,
                "[{$key}] lost the text policy."
            );
        }
    }

    public function test_the_gemini_edit_compiler_states_what_must_not_change(): void
    {
        [$brief, $format] = $this->briefs()[1];
        $clauses = $this->factory->clauses();
        $compiled = $this->factory->for('gemini_edit')->compile($brief, $format);

        $keep = strpos($compiled, 'KEEP - must not change');
        $change = strpos($compiled, 'CHANGE - the new scene');
        $this->assertNotFalse($keep, 'An edit prompt needs an invariant.');
        $this->assertNotFalse($change, 'An edit prompt needs a change.');
        $this->assertLessThan($change, $keep, 'What must not change is stated before what does.');

        $invariant = substr($compiled, $keep, $change - $keep);
        $this->assertStringContainsString($brief['product'], $invariant, 'The product is the invariant.');
        $this->assertStringContainsString('label artwork', $invariant, 'Packaging artwork is named explicitly.');
        $this->assertStringContainsString(
            trim($clauses->camera($brief)),
            $invariant,
            'The framing is preserved: the edit must not re-shoot the product.'
        );

        $moving = substr($compiled, $change);
        foreach ($clauses->sceneParts($brief) as $part) {
            $this->assertStringContainsString(rtrim(trim($part), '.'), $moving, 'The new surroundings are what changes.');
        }

        $this->assertStringContainsString(
            'stays untouched while the surroundings become',
            $compiled,
            'The paste paragraph says the same thing as the two blocks above it.'
        );
    }

    /**
     * The targets whose whole output is pasted into an image or video model.
     * The others carry a campaign brief for a language model, where naming
     * the platform, the format key or a section heading is useful context
     * rather than noise.
     *
     * @return list<string>
     */
    private function modelFacingTargets(): array
    {
        return ['generic', 'imagen', 'veo', 'midjourney', 'flux', 'stable_diffusion', 'gemini_edit'];
    }

    public function test_a_model_facing_prompt_never_quotes_the_studios_own_vocabulary(): void
    {
        $leaked = [
            '(locked)' => 'a screen state the model has no say in',
            'ratio (' => 'a framing formula where the frame itself was asked for',
            'instagram_post' => 'the studio format key instead of the framing',
            'instagram_reel' => 'the studio format key instead of the framing',
            'instagram_story' => 'the studio format key instead of the framing',
            'Aesthetic style' => 'a label the sentence already states in words',
            'ethnicity' => 'a trait the model would have to negotiate over',
        ];

        foreach ($this->briefs() as [$brief, $format]) {
            foreach ($this->modelFacingTargets() as $key) {
                $compiled = $this->factory->for($key)->compile($brief, $format);

                foreach ($leaked as $vocabulary => $why) {
                    $this->assertStringNotContainsString(
                        $vocabulary,
                        $compiled,
                        "[{$key}] leaked [{$vocabulary}] - {$why} - into a prompt the model reads literally."
                    );
                }
            }
        }
    }

    public function test_no_target_prints_the_same_label_twice_in_a_row(): void
    {
        $doubled = [
            'Camera: Camera:',
            'Character consistency: Character consistency:',
            'Custom scene details: Custom scene details:',
            'Brand identity: Brand identity:',
            'Brand: Brand identity:',
            'Campaign mood: Campaign mood:',
            'Campaign: Campaign mood:',
            'Lighting: Lighting:',
            '- Camera: Camera:',
            '- Light: Lighting:',
            '<camera_angle>Camera:',
            '<character_consistency>Character consistency:',
            '<custom_scene_details>Custom scene details:',
            '<brand>Brand identity:',
        ];

        foreach ($this->briefs() as [$brief, $format]) {
            foreach ($this->factory->keys() as $key) {
                $compiled = $this->factory->for($key)->compile($brief, $format);

                foreach ($doubled as $label) {
                    $this->assertStringNotContainsString(
                        $label,
                        $compiled,
                        "[{$key}] prints [{$label}] - a frame written that way reads as a defect to whoever pastes it."
                    );
                }
            }
        }
    }

    public function test_a_negative_never_cancels_the_wording_the_brief_asked_for(): void
    {
        foreach ($this->briefs() as [$brief, $format]) {
            $requestsWording = $this->factory->clauses()->requestsRenderedText($brief);
            $where = $requestsWording ? 'the brief asked for wording' : 'the brief asked for none';

            $midjourney = $this->factory->for('midjourney')->compile($brief, $format);
            $stableDiffusion = $this->factory->for('stable_diffusion')->compile($brief, $format);
            $negative = substr($stableDiffusion, (int) strpos($stableDiffusion, "\n"));

            if ($requestsWording) {
                $this->assertStringEndsWith('--no watermark', $midjourney, "[midjourney] {$where}: `--no text` would drop it.");
                $this->assertStringNotContainsString('(text:', $negative, "[stable_diffusion] {$where}: a negative banning `text` would cancel it.");
                $this->assertStringNotContainsString('(font:', $negative, "[stable_diffusion] {$where}: a negative banning `font` would cancel it.");
            } else {
                $this->assertStringEndsWith('--no text, watermark', $midjourney, "[midjourney] {$where}: the ban stays complete.");
                $this->assertStringContainsString('(text:1.2)', $negative, "[stable_diffusion] {$where}: rendered wording is still the first thing to avoid.");
            }

            $this->assertStringContainsString('(watermark:1.1)', $negative, 'A watermark is never wanted, wording or not.');
        }
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
