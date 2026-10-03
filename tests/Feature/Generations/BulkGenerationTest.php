<?php

namespace Tests\Feature\Generations;

use App\Domains\Creative\Enums\CreativeFormat;
use App\Domains\Creative\Enums\CreativeGoal;
use App\Domains\Creative\Enums\CreativeStyle;
use App\Domains\Generations\Jobs\ProcessGeneration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BulkGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_endpoint_requires_authentication(): void
    {
        $this->postJson('/api/generations/bulk', ['product_ids' => [1]])->assertUnauthorized();
    }

    public function test_bulk_creates_a_generation_per_owned_product(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $ids = [
            $user->products()->create(['name' => 'عطر شبانه'])->id,
            $user->products()->create(['name' => 'کیف چرم'])->id,
            $user->products()->create(['name' => 'شمع وانیل'])->id,
        ];
        $balanceBefore = $user->creditAccount()->firstOrCreate([], ['balance' => 30])->balance;

        $this->postJson('/api/generations/bulk', [
            'product_ids' => $ids,
            'goal' => 'branding',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'environment' => 'luxury',
            'custom_prompt' => 'با نور طلایی ملایم',
        ])->assertAccepted()
            ->assertJsonPath('data.created', 3)
            ->assertJsonPath('data.skipped', 0)
            ->assertJsonPath('data.reason', null);

        $this->assertDatabaseCount('generations', 3);
        $this->assertDatabaseCount('creative_projects', 3);
        Queue::assertPushed(ProcessGeneration::class, 3);

        // 3 image generations at 10 credits each.
        $this->assertSame($balanceBefore - 30, $user->creditAccount()->first()->balance);

        $project = $user->creativeProjects()->first();
        $this->assertEquals(CreativeGoal::Branding, $project->goal);
        $this->assertEquals(CreativeStyle::Luxury, $project->style);
        $this->assertStringContainsString('Custom scene details: با نور طلایی ملایم.', $project->prompt);
        $this->assertTrue($project->generations()->first()->metadata['bulk']);
    }

    public function test_bulk_rejects_foreign_and_unknown_products(): void
    {
        Queue::fake();
        Sanctum::actingAs($user = User::factory()->create());
        $foreign = User::factory()->create()->products()->create(['name' => 'محصول دیگران']);
        $own = $user->products()->create(['name' => 'محصول من']);

        $this->postJson('/api/generations/bulk', ['product_ids' => [$own->id, $foreign->id]])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_PRODUCTS');
        $this->postJson('/api/generations/bulk', ['product_ids' => [999999]])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_PRODUCTS');

        $this->assertDatabaseCount('generations', 0);
    }

    public function test_bulk_validates_product_array_bounds(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $id = $user->products()->create(['name' => 'محصول'])->id;

        $this->postJson('/api/generations/bulk', ['product_ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_ids']);
        $this->postJson('/api/generations/bulk', ['product_ids' => array_fill(0, 21, $id)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_ids']);
        $this->postJson('/api/generations/bulk', ['product_ids' => [$id, $id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_ids.0', 'product_ids.1']);

        $this->assertDatabaseCount('generations', 0);
    }

    public function test_bulk_validates_shared_settings_and_moderation(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $id = $user->products()->create(['name' => 'محصول'])->id;

        $this->postJson('/api/generations/bulk', ['product_ids' => [$id], 'goal' => 'unknown'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['goal']);
        $this->postJson('/api/generations/bulk', ['product_ids' => [$id], 'format' => 'instagram_reel'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['video_duration_seconds']);
        $this->postJson('/api/generations/bulk', ['product_ids' => [$id], 'custom_prompt' => str_repeat('x', 1001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['custom_prompt']);
        $this->postJson('/api/generations/bulk', ['product_ids' => [$id], 'custom_prompt' => 'تصویر با پس‌زمینه پورنوگرافی و مستهجن'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['custom_prompt']);

        $this->assertDatabaseCount('generations', 0);
    }

    public function test_bulk_fails_upfront_when_balance_cannot_cover_the_batch(): void
    {
        Queue::fake();
        Sanctum::actingAs($user = User::factory()->create());
        $ids = [
            $user->products()->create(['name' => 'یک'])->id,
            $user->products()->create(['name' => 'دو'])->id,
        ];
        $user->creditAccount()->firstOrCreate([], ['balance' => 30])->update(['balance' => 5]);

        $this->postJson('/api/generations/bulk', $payload = ['product_ids' => $ids])
            ->assertStatus(402)
            ->assertJsonPath('error.code', 'INSUFFICIENT_CREDITS');

        $this->assertDatabaseCount('generations', 0);
        $this->assertSame(5, $user->creditAccount()->first()->balance);
        $this->assertArrayHasKey('product_ids', $payload);
    }

    public function test_bulk_without_settings_falls_back_to_auto_best(): void
    {
        Queue::fake();
        Sanctum::actingAs($user = User::factory()->create());
        $id = $user->products()->create(['name' => 'کفش ورزشی'])->id;

        $this->postJson('/api/generations/bulk', ['product_ids' => [$id]])
            ->assertAccepted()
            ->assertJsonPath('data.created', 1);

        $project = $user->creativeProjects()->first();
        $this->assertEquals(CreativeGoal::Introduction, $project->goal);
        // autoBest maps a plain product to the professional studio look.
        $this->assertEquals(CreativeStyle::Professional, $project->style);
        $this->assertSame('studio', $project->environment);
        $this->assertEquals(CreativeFormat::InstagramPost, $project->format);
    }

    public function test_dashboard_exposes_the_bulk_controls(): void
    {
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('data-bulk-toggle', false)
            ->assertSee('data-bulk-run', false);
    }
}
