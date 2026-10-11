<?php

namespace Tests\Feature\Editing;

use App\Domains\AI\Gateway\AiGateway;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Editing\Jobs\ProcessImageEdit;
use App\Domains\Editing\Models\ImageEdit;
use App\Domains\Generations\Models\Generation;
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

/**
 * Conversational editing turns one image into a thread: every reply is fed
 * by the previous step's result, so the provider never sees the original
 * after step one. Variants are the other shape - one source, several takes
 * - and both share the resolver, the charge lifecycle and the refund path
 * that the fixed tools already had.
 */
class ConversationalEditingTest extends TestCase
{
    use RefreshDatabase;

    private const SOURCE_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    public function test_a_custom_edit_is_fed_by_the_previous_step_and_not_by_the_original(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $generation = $this->completedGeneration($user);

        $this->postJson('/api/edits', [
            'source_type' => 'generation',
            'source_id' => $generation->id,
            'operation' => 'remove_bg',
        ])->assertAccepted();

        $first = $user->imageEdits()->firstOrFail();

        $this->postJson('/api/edits', [
            'source_type' => 'edit',
            'source_id' => $first->id,
            'operation' => 'custom',
            'message' => 'بطری را رو به چپ بچرخان',
        ])
            ->assertAccepted()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.operation', 'custom')
            ->assertJsonPath('data.message', 'بطری را رو به چپ بچرخان');

        $reply = $user->imageEdits()->where('operation', 'custom')->firstOrFail();
        $this->assertSame('edit', $reply->source_type);
        $this->assertSame($first->id, (int) $reply->source_id);
        $this->assertNotNull($reply->output_media_id);

        // The reply could only have completed if the resolver found the
        // first edit's own output on disk - that is what makes it a reply.
        Storage::disk('s3')->assertExists("edits/{$user->id}/{$first->id}.png");
        $this->assertNotSame($first->output_media_id, $reply->output_media_id);
    }

    public function test_the_thread_endpoint_returns_the_conversation_root_first(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $generation = $this->completedGeneration($user);
        $root = $this->edit($user, 'generation', $generation->id, 'remove_bg');
        $second = $this->edit($user, 'edit', $root->id, 'custom', 'پس‌زمینه را گرم‌تر کن');
        $third = $this->edit($user, 'edit', $second->id, 'custom', 'سایه را ملایم‌تر کن');

        $this->getJson("/api/edits/{$third->id}/thread")
            ->assertOk()
            ->assertJsonCount(3, 'data.steps')
            ->assertJsonPath('data.steps.0.id', $root->id)
            ->assertJsonPath('data.steps.0.operation', 'remove_bg')
            ->assertJsonPath('data.steps.1.message', 'پس‌زمینه را گرم‌تر کن')
            ->assertJsonPath('data.steps.2.id', $third->id)
            ->assertJsonPath('data.depth', 3)
            ->assertJsonPath('data.max_depth', (int) config('ai.max_edit_chain'));

        // Any step opens the same conversation, not just the newest.
        $this->getJson("/api/edits/{$root->id}/thread")->assertJsonCount(3, 'data.steps');
    }

    public function test_siblings_made_from_the_same_step_all_belong_to_the_conversation(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $generation = $this->completedGeneration($user);
        $root = $this->edit($user, 'generation', $generation->id, 'remove_bg');
        $reply = $this->edit($user, 'edit', $root->id, 'custom', 'قدم دوم');
        // Two takes of the same instruction: siblings, not a chain.
        $sibling = $this->edit($user, 'edit', $root->id, 'variant', null);

        $thread = $this->getJson("/api/edits/{$reply->id}/thread")->assertOk()->json('data.steps');

        $this->assertSame([$root->id, $reply->id, $sibling->id], array_column($thread, 'id'));
        $this->assertSame('variant', $thread[2]['operation']);
    }

    public function test_the_thread_is_not_readable_by_someone_else(): void
    {
        Sanctum::actingAs($owner = User::factory()->create());
        $generation = $this->completedGeneration($owner);
        $edit = $this->edit($owner, 'generation', $generation->id, 'remove_bg');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/edits/{$edit->id}/thread")->assertNotFound();
    }

    public function test_a_conversation_instruction_is_required_and_bounded(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $product = $this->sourceProduct($user);

        $this->postJson('/api/edits', [
            'source_type' => 'product',
            'source_id' => $product->id,
            'operation' => 'custom',
        ])->assertUnprocessable();

        $this->postJson('/api/edits', [
            'source_type' => 'product',
            'source_id' => $product->id,
            'operation' => 'custom',
            'message' => 'نه',
        ])->assertUnprocessable();

        $this->assertSame(0, $user->imageEdits()->count());
    }

    public function test_an_instruction_the_moderator_rejects_is_refused_before_anything_is_charged(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $product = $this->sourceProduct($user);
        $initial = (int) config('credits.initial_balance');

        $this->postJson('/api/edits', [
            'source_type' => 'product',
            'source_id' => $product->id,
            'operation' => 'custom',
            'message' => 'make it pornographic',
        ])->assertUnprocessable();

        $this->assertSame(0, $user->imageEdits()->count());
        $this->assertSame($initial, app(CreditService::class)->account($user)->fresh()->balance);
    }

    public function test_replying_to_a_foreign_edit_is_a_404_that_costs_nothing(): void
    {
        Sanctum::actingAs($owner = User::factory()->create());
        $generation = $this->completedGeneration($owner);
        $theirEdit = $this->edit($owner, 'generation', $generation->id, 'remove_bg');

        Sanctum::actingAs($stranger = User::factory()->create());
        $initial = (int) config('credits.initial_balance');

        $this->postJson('/api/edits', [
            'source_type' => 'edit',
            'source_id' => $theirEdit->id,
            'operation' => 'custom',
            'message' => 'یک درخواستِ متجاوز',
        ])->assertNotFound();

        $this->assertSame(0, $stranger->imageEdits()->count());
        $this->assertSame($initial, app(CreditService::class)->account($stranger)->fresh()->balance);
    }

    public function test_a_reply_to_an_edit_that_has_not_finished_is_422(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $product = $this->sourceProduct($user);
        $pending = $user->imageEdits()->create([
            'source_type' => 'product',
            'source_id' => $product->id,
            'operation' => 'remove_bg',
            'status' => 'queued',
        ]);

        $this->postJson('/api/edits', [
            'source_type' => 'edit',
            'source_id' => $pending->id,
            'operation' => 'custom',
            'message' => 'ادامهٔ کار روی چیزی که هنوز آماده نیست',
        ])->assertUnprocessable();
    }

    public function test_a_conversation_cannot_run_past_the_depth_cap(): void
    {
        config(['ai.max_edit_chain' => 3]);
        Sanctum::actingAs($user = User::factory()->create());
        $generation = $this->completedGeneration($user);

        $edit = $this->edit($user, 'generation', $generation->id, 'remove_bg');
        $edit = $this->edit($user, 'edit', $edit->id, 'custom', 'قدم دوم');
        $edit = $this->edit($user, 'edit', $edit->id, 'custom', 'قدم سوم');

        $this->postJson('/api/edits', [
            'source_type' => 'edit',
            'source_id' => $edit->id,
            'operation' => 'custom',
            'message' => 'قدم چهارم که نباید ساخته شود',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'EDIT_CHAIN_TOO_LONG');

        $this->assertSame(3, $user->imageEdits()->count());
    }

    public function test_variants_create_one_take_per_request_on_separate_axes(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $product = $this->sourceProduct($user);
        $initial = (int) config('credits.initial_balance');

        $this->postJson('/api/edits/variants', [
            'source_type' => 'product',
            'source_id' => $product->id,
            'count' => 3,
        ])
            ->assertAccepted()
            ->assertJsonCount(3, 'data');

        $variants = $user->imageEdits()->where('operation', 'variant')->orderBy('id')->get();
        $this->assertCount(3, $variants);
        $this->assertSame([0, 1, 2], $variants->map(fn (ImageEdit $variant): int => (int) $variant->options['take_index'])->all());
        $this->assertSame([5, 5, 5], $variants->map(fn (ImageEdit $variant): int => (int) $variant->credits_spent)->all());
        $variants->each(function (ImageEdit $variant): void {
            $this->assertSame('completed', $variant->status);
            $this->assertNotNull($variant->output_media_id);
        });

        $this->assertSame($initial - 15, app(CreditService::class)->account($user)->fresh()->balance);
    }

    public function test_a_variant_batch_may_start_from_a_previous_edit(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $generation = $this->completedGeneration($user);
        $edited = $this->edit($user, 'generation', $generation->id, 'custom', 'پس‌زمینه را سفید کن');

        $this->postJson('/api/edits/variants', [
            'source_type' => 'edit',
            'source_id' => $edited->id,
            'count' => 2,
        ])->assertAccepted();

        $this->assertSame(2, $user->imageEdits()->where('source_type', 'edit')->count());
    }

    public function test_a_variant_count_outside_the_supported_range_is_rejected(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $product = $this->sourceProduct($user);

        foreach ([1, 5] as $count) {
            $this->postJson('/api/edits/variants', [
                'source_type' => 'product',
                'source_id' => $product->id,
                'count' => $count,
            ])->assertUnprocessable();
        }

        $this->assertSame(0, $user->imageEdits()->count());
    }

    public function test_a_variant_batch_the_user_cannot_pay_for_is_refused_whole(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $product = $this->sourceProduct($user);
        app(CreditService::class)->account($user)->update(['balance' => 10]);

        $this->postJson('/api/edits/variants', [
            'source_type' => 'product',
            'source_id' => $product->id,
            'count' => 3,
        ])->assertStatus(402);

        $this->assertSame(0, $user->imageEdits()->count());
    }

    public function test_a_failed_take_refunds_only_itself_and_leaves_its_siblings_paid(): void
    {
        Queue::fake();
        Sanctum::actingAs($user = User::factory()->create());
        $product = $this->sourceProduct($user);
        $credits = app(CreditService::class);
        $initial = (int) config('credits.initial_balance');

        $this->postJson('/api/edits/variants', [
            'source_type' => 'product',
            'source_id' => $product->id,
            'count' => 3,
        ])->assertAccepted();

        $variants = $user->imageEdits()->where('operation', 'variant')->orderBy('id')->get();
        $this->assertCount(3, Queue::pushed(ProcessImageEdit::class));
        $this->assertSame($initial - 15, $credits->account($user)->fresh()->balance);

        $failure = new RuntimeException('one take could not be made');
        $job = new ProcessImageEdit($variants->first()->id);
        $job->tries = 1;
        $gateway = Mockery::mock(AiGateway::class);
        $gateway->shouldReceive('generate')->once()->andThrow($failure);

        try {
            $job->handle($gateway, $credits);
            $this->fail('The job should rethrow the terminal failure.');
        } catch (Throwable $exception) {
            $this->assertSame($failure, $exception);
        }
        $job->failed($failure);

        $this->assertSame('failed', $variants->first()->refresh()->status);
        // Only the take that failed is refunded; the other two stay charged.
        $this->assertSame($initial - 10, $credits->account($user)->fresh()->balance);
        $this->assertTrue($user->creditAccount->transactions()
            ->where('idempotency_key', "edit:{$variants->first()->id}:refund")
            ->where('amount', 5)
            ->exists());
        $this->assertSame(2, $user->imageEdits()->where('operation', 'variant')->where('status', 'queued')->count());
    }

    public function test_the_costs_endpoint_advertises_the_new_work_and_its_axes(): void
    {
        Sanctum::actingAs($user = User::factory()->create());

        $this->getJson('/api/edits/costs')
            ->assertOk()
            ->assertJsonPath('data.costs.custom', 5)
            ->assertJsonPath('data.costs.variant', 5)
            ->assertJsonPath('data.variants.count.min', 2)
            ->assertJsonPath('data.variants.count.max', 4)
            ->assertJsonPath('data.variants.takes.0.key', 'angle')
            ->assertJsonPath('data.variants.takes.1.key', 'surface')
            ->assertJsonPath('data.variants.takes.2.key', 'light')
            ->assertJsonPath('data.max_depth', (int) config('ai.max_edit_chain'));
    }

    public function test_the_tools_panels_expose_conversation_and_variants(): void
    {
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('data-tool-op="custom"', false)
            ->assertSee('data-tool-op="variants"', false)
            ->assertSee('data-tool-message', false)
            ->assertSee('data-tool-count', false)
            ->assertSee('data-tool-thread', false)
            ->assertSee('data-tool-reply-run', false);

        // The generation page hosts the same shared controller and markup.
        $this->get('/generations/7')
            ->assertOk()
            ->assertSee('data-tool-op="variants"', false)
            ->assertSee('data-tool-thread-steps', false);
    }

    private function edit(User $user, string $sourceType, int $sourceId, string $operation, ?string $message = null): ImageEdit
    {
        $path = "edits/{$user->id}/step-{$sourceId}-{$operation}.png";
        Storage::disk('s3')->put($path, base64_decode(self::SOURCE_PNG));

        return $user->imageEdits()->create([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'operation' => $operation,
            'message' => $message,
            'status' => 'completed',
            'output_media_id' => $user->mediaAssets()->create([
                'disk' => 's3',
                'path' => $path,
                'mime' => 'image/png',
                'size' => strlen(base64_decode(self::SOURCE_PNG)),
            ])->id,
        ]);
    }

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

    private function completedGeneration(User $user): Generation
    {
        Storage::fake('s3');
        $product = $user->products()->create(['name' => 'عطر شب']);
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
            'metadata' => ['aspect_ratio' => '1:1'],
        ]);
        $path = "generations/{$user->id}/{$generation->id}.png";
        Storage::disk('s3')->put($path, base64_decode(self::SOURCE_PNG));
        $media = $user->mediaAssets()->create(['disk' => 's3', 'path' => $path, 'mime' => 'image/png', 'size' => strlen(base64_decode(self::SOURCE_PNG))]);
        $generation->update(['output_media_id' => $media->id]);

        return $generation->fresh();
    }
}
