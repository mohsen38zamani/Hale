<?php

namespace Tests\Feature\Creative;

use App\Domains\Generations\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The API contract of redesign 6: the preview compiles for any target the
 * catalogue knows, generation only runs the ones our own pipeline can build,
 * and the target travels in the brief and the metadata - never into a
 * creative_projects column.
 */
class PromptCompilerTest extends TestCase
{
    use RefreshDatabase;

    private function actingUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * Decode the persisted brief whether the column casts to array or not.
     *
     * @return array<string, mixed>
     */
    private function briefOf(mixed $brief): array
    {
        if (is_string($brief)) {
            $brief = json_decode($brief, true);
        }

        return is_array($brief) ? $brief : [];
    }

    /**
     * @return list<string>
     */
    private function targetErrors(mixed $response): array
    {
        $errors = (array) ($response->json('errors') ?? []);

        return isset($errors['target_ai']) ? array_values((array) $errors['target_ai']) : [];
    }

    public function test_preview_compiles_the_prompt_for_the_requested_target(): void
    {
        $product = $this->actingUser()->products()->create(['name' => 'عطر']);

        $data = $this->postJson('/api/creative/preview', [
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'surface' => 'marble',
            'target_ai' => 'claude',
        ])->assertOk()->json('data');

        $this->assertSame('claude', $data['brief']['target_ai'] ?? null, 'The target has to reach the brief.');
        $this->assertStringStartsWith('<claude_prompt>', $data['prompt_preview']);
        $this->assertStringContainsString('<product>', $data['prompt_preview']);
        $this->assertStringContainsString(
            ucfirst((string) config('creative.surfaces.marble.prompt')),
            $data['prompt_preview'],
            'The chosen surface is quoted inside the structure.'
        );
    }

    public function test_preview_quotes_the_studio_settings_it_was_given(): void
    {
        $product = $this->actingUser()->products()->create(['name' => 'عطر']);

        $data = $this->postJson('/api/creative/preview', [
            'product_id' => $product->id,
            'goal' => 'sales',
            'format' => 'instagram_story',
            'surface' => 'obsidian',
            'camera_angle' => 'macro',
        ])->assertOk()->json('data');

        $this->assertSame('instagram_story', $data['format'], 'The chosen format wins over the autoBest suggestion.');
        $this->assertStringContainsString('Vertical 9:16 frame.', $data['prompt_preview']);
        $this->assertStringContainsString(
            ucfirst((string) config('creative.surfaces.obsidian.prompt')),
            $data['prompt_preview']
        );
        $this->assertStringContainsString(
            'Camera: '.config('creative.camera_angles.macro.prompt').'.',
            $data['prompt_preview']
        );
        $this->assertArrayNotHasKey('target_ai', $data['brief'], 'No target means the generic prompt.');
        $this->assertStringStartsWith('Create a professional commercial advertising visual for', $data['prompt_preview']);
    }

    public function test_preview_still_compiles_a_copy_only_target(): void
    {
        $product = $this->actingUser()->products()->create(['name' => 'عطر']);

        $data = $this->postJson('/api/creative/preview', [
            'product_id' => $product->id,
            'goal' => 'sales',
            'target_ai' => 'midjourney',
        ])->assertOk()->json('data');

        $this->assertSame('midjourney', $data['brief']['target_ai'] ?? null);
        $this->assertStringContainsString('--ar 1:1', $data['prompt_preview']);
        $this->assertStringEndsWith('--style raw --v 6.1 --no text, watermark', $data['prompt_preview']);
    }

    public function test_preview_rejects_an_unknown_target(): void
    {
        $product = $this->actingUser()->products()->create(['name' => 'عطر']);

        $response = $this->postJson('/api/creative/preview', [
            'product_id' => $product->id,
            'target_ai' => 'hal-9000',
        ])->assertUnprocessable();

        $this->assertSame(
            ['مدل هوش مصنوعی انتخاب‌شده نامعتبر است.'],
            $this->targetErrors($response)
        );
    }

    public function test_the_inspector_payload_the_studio_posts_is_accepted(): void
    {
        $product = $this->actingUser()->products()->create(['name' => 'عطر']);

        // Exactly the shape the hybrid inspector builds: every control the
        // page knows, blanks already dropped, nulls for what it has not
        // decided. If this drifts, the inspector would quote an error where a
        // prompt belongs.
        $data = $this->postJson('/api/creative/preview', [
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'environment' => 'studio',
            'surface' => 'marble',
            'props' => 'none',
            'camera_angle' => 'eye_level',
            'lighting_setup' => 'softbox',
            'character_consistency' => config('creative.character_consistency_default'),
            'custom_prompt' => null,
            'video_duration_seconds' => null,
            'campaign' => false,
            'season_theme' => null,
            'target_ai' => 'claude',
        ])->assertOk()->json('data');

        $this->assertStringStartsWith('<claude_prompt>', $data['prompt_preview']);
        $this->assertSame('claude', $data['brief']['target_ai'] ?? null);
    }

    public function test_generation_refuses_a_copy_only_target(): void
    {
        $product = $this->actingUser()->products()->create(['name' => 'عطر']);

        $response = $this->postJson('/api/generations', [
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'target_ai' => 'midjourney',
        ])->assertUnprocessable();

        $this->assertStringContainsString(
            'کپی',
            implode(' ', $this->targetErrors($response)),
            'The refusal must say what the user can do instead: copy the prompt.'
        );
        $this->assertStringContainsString('Midjourney', implode(' ', $this->targetErrors($response)));
        $this->assertDatabaseCount('generations', 0);
        $this->assertDatabaseCount('creative_projects', 0);
    }

    public function test_generation_refuses_a_target_that_does_not_serve_the_format(): void
    {
        $user = $this->actingUser();
        $product = $user->products()->create(['name' => 'عطر']);

        $videoTargetOnImage = $this->postJson('/api/generations', [
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'target_ai' => 'veo',
        ])->assertUnprocessable();

        $this->assertStringContainsString('فقط ویدیو', implode(' ', $this->targetErrors($videoTargetOnImage)));

        $imageTargetOnVideo = $this->postJson('/api/generations', [
            'product_id' => $product->id,
            'goal' => 'branding',
            'style' => 'luxury',
            'format' => 'instagram_reel',
            'video_duration_seconds' => 5,
            'target_ai' => 'imagen',
        ])->assertUnprocessable();

        $this->assertStringContainsString('فقط عکس', implode(' ', $this->targetErrors($imageTargetOnVideo)));
        $this->assertDatabaseCount('generations', 0);
    }

    public function test_generation_compiles_for_an_in_house_target(): void
    {
        $product = $this->actingUser()->products()->create(['name' => 'عطر']);

        $id = $this->postJson('/api/generations', [
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'surface' => 'marble',
            'target_ai' => 'imagen',
        ])->assertAccepted()->json('data.id');

        $generation = Generation::query()->findOrFail($id);
        $project = $generation->creativeProject;

        $this->assertSame('imagen', $generation->metadata['target_ai'] ?? null, 'Metadata records what the prompt was written for.');
        $this->assertSame('imagen', $this->briefOf($project->brief)['target_ai'] ?? null);
        $this->assertStringContainsString('85mm lens', $project->prompt, 'The Imagen dialect reached the provider payload.');
        $this->assertStringNotContainsString('High-end commercial production', $project->prompt);
        $this->assertArrayNotHasKey('target_ai', $project->getAttributes(), 'creative_projects has no such column.');
    }

    public function test_bulk_carries_the_target_into_every_project(): void
    {
        $user = $this->actingUser();
        $ids = [
            $user->products()->create(['name' => 'عطر یک'])->id,
            $user->products()->create(['name' => 'عطر دو'])->id,
        ];

        $response = $this->postJson('/api/generations/bulk', [
            'product_ids' => $ids,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'target_ai' => 'imagen',
        ])->assertAccepted();

        $this->assertSame(2, $response->json('data.created'));

        foreach (Generation::query()->get() as $generation) {
            $this->assertSame('imagen', $generation->metadata['target_ai'] ?? null);
            $this->assertStringContainsString('85mm lens', $generation->creativeProject->prompt);
            $this->assertArrayNotHasKey('target_ai', $generation->creativeProject->getAttributes());
        }

        $this->postJson('/api/generations/bulk', [
            'product_ids' => $ids,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'target_ai' => 'flux',
        ])->assertUnprocessable();

        $this->assertSame(2, Generation::query()->count(), 'A refused target must not queue anything.');
    }

    public function test_a_template_may_pin_a_target_model(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $payload = [
            'name' => 'کمپین لوکس',
            'settings' => [
                'goal' => 'branding',
                'style' => 'luxury',
                'format' => 'instagram_post',
                'target_ai' => 'claude',
            ],
        ];

        $id = $this->postJson('/api/templates', $payload)
            ->assertCreated()
            ->assertJsonPath('data.settings.target_ai', 'claude')
            ->json('data.id');

        $this->getJson("/api/templates/{$id}")
            ->assertOk()
            ->assertJsonPath('data.settings.target_ai', 'claude');

        $payload['settings']['target_ai'] = 'hal-9000';
        $this->postJson('/api/templates', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('settings.target_ai');
    }
}
