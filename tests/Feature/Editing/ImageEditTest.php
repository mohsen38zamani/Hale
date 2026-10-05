<?php

namespace Tests\Feature\Editing;

use App\Domains\AI\Gateway\AiGateway;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Editing\Jobs\ProcessImageEdit;
use App\Domains\Generations\Models\Generation;
use App\Domains\Notifications\Notifications\ImageEditStatusNotification;
use App\Domains\Products\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use RuntimeException;
use Tests\TestCase;
use Throwable;

class ImageEditTest extends TestCase
{
    use RefreshDatabase;

    private const SOURCE_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function test_remove_bg_edit_runs_end_to_end_and_charges_five_credits(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $product = $this->sourceProduct($user);
        $initial = (int) config('credits.initial_balance');

        $this->postJson('/api/edits', [
            'source_type' => 'product',
            'source_id' => $product->id,
            'operation' => 'remove_bg',
            'background' => 'white',
        ])
            ->assertAccepted()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.operation', 'remove_bg')
            ->assertJsonPath('data.credits_spent', 5)
            ->assertJsonPath('data.provider', 'local');

        $edit = $user->imageEdits()->firstOrFail();
        $this->assertSame(['background' => 'white'], $edit->options);
        $this->assertSame('completed', $edit->status);
        $this->assertNotNull($edit->output_media_id);
        Storage::disk('s3')->assertExists("edits/{$user->id}/{$edit->id}.png");

        $this->assertSame($initial - 5, app(CreditService::class)->account($user)->fresh()->balance);
        $this->assertTrue($user->creditAccount->transactions()
            ->where('idempotency_key', "edit:{$edit->id}")
            ->where('type', 'edit')
            ->where('amount', -5)
            ->exists());
        $this->assertDatabaseHas('notifications', [
            'type' => ImageEditStatusNotification::class,
            'notifiable_id' => $user->id,
        ]);
    }

    public function test_edit_operates_on_a_completed_generation_output(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $generation = $this->completedGeneration($user);

        $this->postJson('/api/edits', [
            'source_type' => 'generation',
            'source_id' => $generation->id,
            'operation' => 'remove_bg',
        ])
            ->assertAccepted()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.source_type', 'generation');

        $edit = $user->imageEdits()->firstOrFail();
        $this->assertSame('generation', $edit->source_type);
        $this->assertSame($generation->id, (int) $edit->source_id);
        $this->assertSame($initial = (int) config('credits.initial_balance') - 5, app(CreditService::class)->account($user)->fresh()->balance);
    }

    public function test_insufficient_credits_rejects_without_creating_an_edit(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $product = $this->sourceProduct($user);
        $credits = app(CreditService::class);
        // Drain the wallet down to 4 (below the 5-credit remove_bg tier).
        $credits->spendForTask($user, null, 26, 'drain:test', [], 'text_task');

        $this->postJson('/api/edits', [
            'source_type' => 'product',
            'source_id' => $product->id,
            'operation' => 'remove_bg',
        ])->assertStatus(402)->assertJsonPath('error.code', 'INSUFFICIENT_CREDITS');

        $this->assertSame(0, $user->imageEdits()->count());
        $this->assertSame(4, $credits->account($user)->fresh()->balance);
        $this->assertSame(0, $user->creditAccount->transactions()->where('type', 'edit')->count());
    }

    public function test_upscale_beyond_hd_requires_a_premium_plan(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $product = $this->sourceProduct($user);
        $initial = (int) config('credits.initial_balance');

        $this->postJson('/api/edits', [
            'source_type' => 'product',
            'source_id' => $product->id,
            'operation' => 'upscale',
            'target' => '2k',
        ])->assertStatus(403)->assertJsonPath('error.code', 'PREMIUM_QUALITY_REQUIRED');

        $this->assertSame(0, $user->imageEdits()->count());
        $this->assertSame($initial, app(CreditService::class)->account($user)->fresh()->balance);
    }

    public function test_premium_user_can_upscale_to_4k_for_fifteen_credits(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $user->forceFill(['plan_key' => 'starter'])->save();
        $product = $this->sourceProduct($user);
        $initial = (int) config('credits.initial_balance');

        $this->postJson('/api/edits', [
            'source_type' => 'product',
            'source_id' => $product->id,
            'operation' => 'upscale',
            'target' => '4k',
        ])
            ->assertAccepted()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.credits_spent', 15);

        $edit = $user->imageEdits()->firstOrFail();
        $this->assertSame(['target' => '4k'], $edit->options);
        $this->assertSame($initial - 15, app(CreditService::class)->account($user)->fresh()->balance);
    }

    public function test_estimate_endpoint_reports_edit_costs_and_plan_gate(): void
    {
        Sanctum::actingAs($user = User::factory()->create());

        $this->postJson('/api/credits/estimate', ['type' => 'edit', 'operation' => 'remove_bg'])
            ->assertOk()
            ->assertJsonPath('data.type', 'edit')
            ->assertJsonPath('data.operation', 'remove_bg')
            ->assertJsonPath('data.cost', 5)
            ->assertJsonPath('data.plan_blocked', false)
            ->assertJsonPath('data.sufficient', true);

        // Free plan sees the real 2k price, flagged as plan-gated.
        $this->postJson('/api/credits/estimate', ['type' => 'edit', 'operation' => 'upscale', 'target' => '2k'])
            ->assertOk()
            ->assertJsonPath('data.cost', 10)
            ->assertJsonPath('data.plan_blocked', true);

        $user->forceFill(['plan_key' => 'starter'])->save();
        $this->postJson('/api/credits/estimate', ['type' => 'edit', 'operation' => 'upscale', 'target' => '2k'])
            ->assertOk()
            ->assertJsonPath('data.cost', 10)
            ->assertJsonPath('data.plan_blocked', false);
    }

    public function test_estimate_endpoint_requires_operation_for_edit_type(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/credits/estimate', ['type' => 'edit'])->assertUnprocessable();
        $this->postJson('/api/credits/estimate', ['type' => 'edit', 'operation' => 'magic'])->assertUnprocessable();
    }

    public function test_expand_requires_aspect_ratio_and_rejects_unknown_operations(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $product = $this->sourceProduct($user);

        $this->postJson('/api/edits', [
            'source_type' => 'product',
            'source_id' => $product->id,
            'operation' => 'expand',
        ])->assertUnprocessable()->assertJsonValidationErrors(['aspect_ratio']);

        $this->postJson('/api/edits', [
            'source_type' => 'product',
            'source_id' => $product->id,
            'operation' => 'magic',
        ])->assertUnprocessable()->assertJsonValidationErrors(['operation']);

        $this->assertSame(0, $user->imageEdits()->count());
        $this->assertSame((int) config('credits.initial_balance'), app(CreditService::class)->account($user)->fresh()->balance);
    }

    public function test_foreign_source_returns_404_and_does_not_charge(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $foreign = User::factory()->create();
        $foreignProduct = $foreign->products()->create(['name' => 'محصول دیگران']);
        $initial = (int) config('credits.initial_balance');

        $this->postJson('/api/edits', [
            'source_type' => 'product',
            'source_id' => $foreignProduct->id,
            'operation' => 'remove_bg',
        ])->assertStatus(404)->assertJsonPath('error.code', 'SOURCE_NOT_FOUND');

        $this->assertSame(0, $user->imageEdits()->count());
        $this->assertSame($initial, app(CreditService::class)->account($user)->fresh()->balance);
    }

    public function test_generation_without_a_completed_image_returns_422(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $generation = $this->makeGeneration($user);
        $generation->update(['output_media_id' => null]);
        $initial = (int) config('credits.initial_balance');

        $this->postJson('/api/edits', [
            'source_type' => 'generation',
            'source_id' => $generation->id,
            'operation' => 'remove_bg',
        ])->assertStatus(422)->assertJsonPath('error.code', 'SOURCE_NOT_PROCESSABLE');

        $this->assertSame(0, $user->imageEdits()->count());
        $this->assertSame($initial, app(CreditService::class)->account($user)->fresh()->balance);
    }

    public function test_terminal_failure_refunds_the_upfront_charge(): void
    {
        Queue::fake();
        Sanctum::actingAs($user = User::factory()->create());
        $product = $this->sourceProduct($user);
        $credits = app(CreditService::class);
        $initial = (int) config('credits.initial_balance');

        $this->postJson('/api/edits', [
            'source_type' => 'product',
            'source_id' => $product->id,
            'operation' => 'remove_bg',
        ])->assertAccepted();
        Queue::assertPushed(ProcessImageEdit::class, fn (ProcessImageEdit $job): bool => $job->imageEditId === $user->imageEdits()->first()->id);

        $edit = $user->imageEdits()->firstOrFail();
        $this->assertSame($initial - 5, $credits->account($user)->fresh()->balance);

        $failure = new RuntimeException('permanent provider failure');
        $job = new ProcessImageEdit($edit->id);
        $job->tries = 1;
        $failingGateway = Mockery::mock(AiGateway::class);
        $failingGateway->shouldReceive('generate')->once()->andThrow($failure);

        try {
            $job->handle($failingGateway, $credits);
            $this->fail('The job should rethrow the terminal failure.');
        } catch (Throwable $exception) {
            $this->assertSame($failure, $exception);
        }
        $job->failed($failure);

        $edit->refresh();
        $this->assertSame('failed', $edit->status);
        $this->assertSame('permanent provider failure', $edit->error_message);
        $this->assertNull($edit->output_media_id);
        $this->assertSame($initial, $credits->account($user)->fresh()->balance);
        $this->assertTrue($user->creditAccount->transactions()
            ->where('idempotency_key', "edit:{$edit->id}:refund")
            ->where('type', 'refund')
            ->where('amount', 5)
            ->exists());
    }

    public function test_failed_handler_is_idempotent_and_notifies_once(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $credits = app(CreditService::class);
        $initial = (int) config('credits.initial_balance');
        $edit = $user->imageEdits()->create([
            'source_type' => 'product',
            'source_id' => 1,
            'operation' => 'remove_bg',
            'status' => 'processing',
            'credits_spent' => 5,
        ]);
        $credits->spendForTask($user, null, 5, "edit:{$edit->id}", ['edit_id' => $edit->id], 'edit');

        $job = new ProcessImageEdit($edit->id);
        $job->failed(new RuntimeException('boom'));
        $job->failed(new RuntimeException('boom replayed'));

        $edit->refresh();
        $this->assertSame('failed', $edit->status);
        $this->assertSame($initial, $credits->account($user)->fresh()->balance);
        $this->assertSame(1, $user->creditAccount->transactions()->where('type', 'refund')->count());
        $this->assertSame(1, $user->notifications()->where('type', ImageEditStatusNotification::class)->count());
    }

    public function test_show_and_download_are_owner_scoped(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $product = $this->sourceProduct($user);
        $this->postJson('/api/edits', [
            'source_type' => 'product',
            'source_id' => $product->id,
            'operation' => 'remove_bg',
        ])->assertAccepted();
        $edit = $user->imageEdits()->firstOrFail();

        $this->getJson("/api/edits/{$edit->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $edit->id)
            ->assertJsonPath('data.output_media.mime', 'image/png');

        $download = $this->get("/api/edits/{$edit->id}/download");
        $download->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertDownload("edit-{$edit->id}.png");

        Sanctum::actingAs($foreign = User::factory()->create());
        $this->getJson("/api/edits/{$edit->id}")->assertNotFound();
        $this->getJson("/api/edits/{$edit->id}/download")->assertNotFound();
        $this->getJson('/api/edits')->assertOk()->assertJsonPath('data.total', 0);
        $this->assertSame(0, $foreign->imageEdits()->count());
    }

    public function test_costs_endpoint_exposes_prices_and_plan_targets(): void
    {
        Sanctum::actingAs($user = User::factory()->create());

        $this->getJson('/api/edits/costs')
            ->assertOk()
            ->assertJsonPath('data.costs.remove_bg', 5)
            ->assertJsonPath('data.costs.shadow', 5)
            ->assertJsonPath('data.costs.expand', 10)
            ->assertJsonPath('data.costs.upscale.hd', 5)
            ->assertJsonPath('data.costs.upscale.2k', 10)
            ->assertJsonPath('data.costs.upscale.4k', 15)
            ->assertJsonPath('data.plan.quality', 'standard')
            ->assertJsonPath('data.plan.max_upscale_target', 'hd')
            ->assertJsonPath('data.balance', 30);

        $user->forceFill(['plan_key' => 'creator'])->save();
        $this->getJson('/api/edits/costs')
            ->assertOk()
            ->assertJsonPath('data.plan.max_upscale_target', '4k');
    }

    public function test_index_lists_only_own_edits_and_filters_by_source(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $product = $this->sourceProduct($user);
        $this->postJson('/api/edits', [
            'source_type' => 'product',
            'source_id' => $product->id,
            'operation' => 'remove_bg',
        ])->assertAccepted();

        $foreign = User::factory()->create();
        $foreign->imageEdits()->create([
            'source_type' => 'product',
            'source_id' => 999,
            'operation' => 'expand',
            'status' => 'queued',
            'credits_spent' => 10,
        ]);

        $this->getJson('/api/edits')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson("/api/edits?source_type=product&source_id={$product->id}")
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.operation', 'remove_bg');
        $this->getJson('/api/edits?source_id=999')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/edits?status=queued')->assertOk()->assertJsonPath('data.total', 0);
    }

    /**
     * A product with a real source photo on the (faked) s3 disk.
     */
    private function sourceProduct(User $user): Product
    {
        Storage::fake('s3');
        $product = $user->products()->create(['name' => 'عطر شب']);
        $path = "products/{$user->id}/source-{$product->id}.png";
        Storage::disk('s3')->put($path, base64_decode(self::SOURCE_PNG));
        $asset = $user->mediaAssets()->create(['disk' => 's3', 'path' => $path, 'mime' => 'image/png', 'size' => strlen(base64_decode(self::SOURCE_PNG))]);
        $product->assets()->attach($asset->id, ['is_primary' => true]);

        return $product;
    }

    /**
     * A completed image generation whose output already exists on disk.
     */
    private function completedGeneration(User $user): Generation
    {
        Storage::fake('s3');
        $generation = $this->makeGeneration($user);
        $path = "generations/{$user->id}/{$generation->id}.png";
        Storage::disk('s3')->put($path, base64_decode(self::SOURCE_PNG));
        $media = $user->mediaAssets()->create(['disk' => 's3', 'path' => $path, 'mime' => 'image/png', 'size' => strlen(base64_decode(self::SOURCE_PNG))]);
        $generation->update(['output_media_id' => $media->id]);

        return $generation->fresh();
    }

    private function makeGeneration(User $user): Generation
    {
        $product = $user->products()->create(['name' => 'عطر شب', 'description' => 'توضیح آزمایشی']);
        $project = $user->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'brief' => [],
            'prompt' => 'prompt',
        ]);

        return $project->generations()->create([
            'user_id' => $user->id,
            'type' => 'image',
            'status' => 'completed',
            'prompt_hash' => hash('sha256', 'prompt'),
            'metadata' => ['aspect_ratio' => '1:1'],
        ]);
    }
}
