<?php

namespace Tests\Feature\Generations;

use App\Domains\Creative\Enums\FidelityMode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Batch 6 - the studio's trade surface. The ten trades, their fidelity
 * promise and their one-click packages are catalogue content, so the page
 * renders them from config rather than from hardcoded markup; and the result
 * page must carry the original photograph next to the generated frame, which
 * is the only way the before/after slider can answer "did the stone survive?".
 */
class StudioTradeUiTest extends TestCase
{
    use RefreshDatabase;

    private const TRADE_MARKERS = [
        'data-trade-field',
        'data-trade-grid',
        'data-trade-note',
        'data-fidelity-field',
        'data-fidelity-mode',
        'data-trade-presets',
        'data-trade-preset-row',
        'data-trade-preset-message',
        'data-chip-trade',
    ];

    public function test_the_studio_renders_the_trade_surface_from_the_catalogue(): void
    {
        $view = (string) $this->get('/create')->assertOk()->baseResponse->getContent();

        foreach (self::TRADE_MARKERS as $marker) {
            $this->assertStringContainsString($marker, $view, "The studio must render {$marker}.");
        }

        // The fidelity promise is a select, not a chip row: its options are
        // the enum rendered server-side so a label change lands everywhere.
        foreach (FidelityMode::cases() as $mode) {
            $this->assertStringContainsString('value="'.$mode->value.'"', $view, 'Every fidelity pipeline must be offered.');
            $this->assertStringContainsString(e($mode->label()), $view, 'Fidelity labels come from the enum.');
        }
    }

    public function test_the_trade_and_fidelity_controls_refresh_the_prompt_inspector(): void
    {
        $view = (string) $this->get('/create')->assertOk()->baseResponse->getContent();
        $script = (string) file_get_contents(resource_path('js/app.js'));

        // The trade grid is a choice grid like any other, so the shared
        // wiring pass has to list it; the fidelity select rides with it.
        preg_match_all('/class="choice-grid[^"]*"\s+(data-[a-z-]+)/', $view, $matches);
        $this->assertContains('data-trade-grid', $matches[1], 'The trade grid must be one of the studio choice grids.');

        $anchor = strpos($script, '// One wiring pass over every choice grid');
        $this->assertNotFalse($anchor);
        $marker = '.forEach((selector) => document.querySelector(selector)?.addEventListener(\'change\', updateCanvasState)';
        $position = strpos($script, $marker);
        $this->assertNotFalse($position);
        $wiring = substr($script, $anchor, $position + strlen($marker) - $anchor);

        $this->assertStringContainsString("'[data-trade-grid]'", $wiring, 'The trade grid must be wired.');
        $this->assertStringContainsString("'[data-fidelity-mode]'", $wiring, 'The fidelity select must be wired.');

        // A named trade makes the prompt a server dialect even for the generic
        // target: the scale lock and the guardrail live only in the compilers.
        $this->assertStringContainsString('const asksServer = (targetKey && targetKey !== mirrorTargetKey) || Boolean(tradeKey)', $script);
        $this->assertStringContainsString('category: tradeKey || null', $script);
        $this->assertStringContainsString('fidelity_mode: fidelityKey || null', $script);

        // ...and both reach the creation request, optional as they are.
        $this->assertStringContainsString('category: values.category || null', $script);
        $this->assertStringContainsString('fidelity_mode: values.fidelity_mode || null', $script);
    }

    public function test_the_trade_packages_apply_through_real_form_fields(): void
    {
        $script = (string) file_get_contents(resource_path('js/app.js'));

        // A package is not a hidden payload: it ticks the controls it decides
        // so the canvas, the summary and the estimate all follow.
        $this->assertStringContainsString('const renderTradePresets = (tradeKey)', $script);
        $this->assertStringContainsString('const applyTradePreset = (preset)', $script);
        foreach (['goal', 'style', 'surface', 'props', 'camera_angle', 'lighting_setup'] as $control) {
            $this->assertStringContainsString("setRadio('{$control}', preset.{$control})", $script, "A package must set {$control} through its radio.");
        }

        // The saved template has to bring the trade back too, and restoring it
        // must repaint the packages the trade owns.
        $this->assertStringContainsString("setRadio('category', String(settings.category))", $script);
        $this->assertStringContainsString('renderTradePresets(String(settings.category))', $script);
    }

    public function test_the_result_page_carries_the_before_and_after_slider(): void
    {
        $view = (string) $this->get('/generations/1')->assertOk()->baseResponse->getContent();

        foreach (['data-ba-panel', 'data-ba-slider', 'data-ba-before', 'data-ba-after', 'data-ba-after-img', 'data-ba-handle', 'data-ba-range', 'data-ba-hint'] as $marker) {
            $this->assertStringContainsString($marker, $view, "The result page must render {$marker}.");
        }

        $script = (string) file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('const setupCompare = async (generation, generatedUrl)', $script);
        $this->assertStringContainsString('/download`', $script, 'The original photo is fetched through the asset download endpoint.');
        // A video has no "before" worth lining up against.
        $this->assertStringContainsString("if (isVideo) {\n\t\t\t\tif (baPanel) baPanel.hidden = true;", $script);
        // When there is no original to show the panel says so instead of
        // pretending the slider works.
        $this->assertStringContainsString('compareUnavailable(', $script);
    }

    public function test_the_generation_payload_carries_only_the_primary_photo(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = $user->products()->create(['name' => 'انگشتر نقره']);
        $primary = $user->mediaAssets()->create(['disk' => 's3', 'path' => "products/{$user->id}/primary.png", 'mime' => 'image/png', 'size' => 10]);
        $secondary = $user->mediaAssets()->create(['disk' => 's3', 'path' => "products/{$user->id}/secondary.png", 'mime' => 'image/png', 'size' => 10]);
        $product->assets()->attach($primary->id, ['is_primary' => true]);
        $product->assets()->attach($secondary->id, ['is_primary' => false]);

        $project = $user->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'brief' => [],
            'prompt' => 'prompt',
        ]);
        $generation = $project->generations()->create([
            'user_id' => $user->id,
            'type' => 'image',
            'status' => 'completed',
            'prompt_hash' => hash('sha256', 'prompt'),
        ]);

        $this->getJson("/api/generations/{$generation->id}")->assertOk()
            ->assertJsonPath('data.creative_project.product.id', $product->id)
            ->assertJsonPath('data.creative_project.product.assets.0.id', $primary->id)
            ->assertJsonCount(1, 'data.creative_project.product.assets');
    }

    public function test_the_generation_payload_falls_back_to_first_asset_when_no_primary_is_flagged(): void
    {
        Storage::fake('s3');
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $product = $user->products()->create(['name' => 'عطر بهار']);
        $asset = $user->mediaAssets()->create(['disk' => 's3', 'path' => "products/{$user->id}/bottle.png", 'mime' => 'image/png', 'size' => 10]);
        $product->assets()->attach($asset->id, ['is_primary' => false]);

        $project = $user->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'brief' => [],
            'prompt' => 'prompt',
        ]);
        $generation = $project->generations()->create([
            'user_id' => $user->id,
            'type' => 'image',
            'status' => 'completed',
            'prompt_hash' => hash('sha256', 'prompt'),
        ]);

        $this->getJson("/api/generations/{$generation->id}")->assertOk()
            ->assertJsonPath('data.creative_project.product.assets.0.id', $asset->id)
            ->assertJsonCount(1, 'data.creative_project.product.assets');
    }
}
