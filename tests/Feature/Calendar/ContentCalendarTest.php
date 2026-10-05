<?php

namespace Tests\Feature\Calendar;

use App\Domains\Calendar\Models\Campaign;
use App\Domains\Calendar\Models\ScheduledPost;
use App\Domains\Generations\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_posts_are_scoped_to_the_owner_and_range(): void
    {
        Sanctum::actingAs($owner = User::factory()->create());
        $inRange = $this->makePost($owner, $this->makeGeneration($owner), '2030-01-15');
        $this->makePost($owner, $this->makeGeneration($owner), '2030-03-01');
        $foreign = User::factory()->create();
        $this->makePost($foreign, $this->makeGeneration($foreign), '2030-01-20');

        $posts = $this->getJson('/api/calendar/posts?from=2030-01-01&to=2030-01-31')
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $posts);
        $this->assertSame($inRange->id, $posts[0]['id']);
        $this->assertSame('2030-01-15', $posts[0]['scheduled_at']);
        $this->assertSame('scheduled', $posts[0]['status']);
        $this->assertNull($posts[0]['campaign']);

        // Without a range: everything of mine, never the foreign row.
        $this->assertCount(2, $this->getJson('/api/calendar/posts')->assertOk()->json('data'));
    }

    public function test_store_post_validates_generation_ownership_and_date(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $foreign = $this->makeGeneration(User::factory()->create());

        $this->postJson('/api/calendar/posts', [
            'generation_id' => $foreign->id,
            'scheduled_at' => '2030-01-10',
        ])->assertNotFound();

        $this->postJson('/api/calendar/posts', [
            'generation_id' => $this->makeGeneration($user)->id,
            'scheduled_at' => '2020-01-01',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertDatabaseCount('scheduled_posts', 0);
    }

    public function test_store_post_creates_a_scheduled_entry(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $generation = $this->makeGeneration($user);

        $data = $this->postJson('/api/calendar/posts', [
            'generation_id' => $generation->id,
            'scheduled_at' => '2030-01-10',
            'caption' => 'کپشن اول',
            'status' => 'draft',
        ])->assertCreated()->json('data');

        $this->assertSame('2030-01-10', $data['scheduled_at']);
        $this->assertSame('draft', $data['status']);
        $this->assertSame($generation->id, $data['generation']['id']);
        $this->assertDatabaseHas('scheduled_posts', [
            'user_id' => $user->id,
            'status' => 'draft',
            'caption' => 'کپشن اول',
        ]);
    }

    public function test_update_post_changes_status_date_and_caption(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $post = $this->makePost($user, $this->makeGeneration($user), '2030-01-10');

        $this->patchJson("/api/calendar/posts/{$post->id}", ['status' => 'published'])
            ->assertOk()
            ->assertJsonPath('data.status', 'published');
        $this->patchJson("/api/calendar/posts/{$post->id}", ['scheduled_at' => '2030-01-12', 'caption' => 'تغییر'])
            ->assertOk()
            ->assertJsonPath('data.scheduled_at', '2030-01-12');

        $this->assertDatabaseHas('scheduled_posts', [
            'id' => $post->id,
            'status' => 'published',
            'caption' => 'تغییر',
        ]);

        // Nullable caption and notes can be cleared back to null
        $this->patchJson("/api/calendar/posts/{$post->id}", ['caption' => null, 'notes' => null])
            ->assertOk()
            ->assertJsonPath('data.caption', null)
            ->assertJsonPath('data.notes', null);

        $this->assertDatabaseHas('scheduled_posts', [
            'id' => $post->id,
            'caption' => null,
            'notes' => null,
        ]);

        $this->assertTrue($user->scheduledPosts()->whereKey($post->id)->exists());

        // Another user can neither edit nor delete someone else's post.
        Sanctum::actingAs(User::factory()->create());
        $this->patchJson("/api/calendar/posts/{$post->id}", ['status' => 'draft'])->assertNotFound();
        $this->deleteJson("/api/calendar/posts/{$post->id}")->assertNotFound();
        $this->assertDatabaseHas('scheduled_posts', ['id' => $post->id, 'status' => 'published']);
    }

    public function test_delete_post_removes_it(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $post = $this->makePost($user, $this->makeGeneration($user), '2030-01-10');

        $this->deleteJson("/api/calendar/posts/{$post->id}")->assertOk();

        $this->assertDatabaseMissing('scheduled_posts', ['id' => $post->id]);
    }

    public function test_campaign_creation_schedules_stepwise_posts(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $ids = [
            $this->makeGeneration($user)->id,
            $this->makeGeneration($user)->id,
            $this->makeGeneration($user)->id,
        ];

        $data = $this->postJson('/api/campaigns', [
            'name' => 'هفتهٔ فروش',
            'generation_ids' => $ids,
            'start_date' => '2030-01-10',
            'interval_days' => 2,
        ])->assertCreated()->json('data');

        $this->assertSame('هفتهٔ فروش', $data['name']);
        $this->assertSame(3, $data['posts_count']);
        $this->assertSame(
            ['2030-01-10', '2030-01-12', '2030-01-14'],
            array_column($data['posts'], 'scheduled_at'),
        );

        $campaign = Campaign::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame(2, $campaign->interval_days);
        $this->assertSame('2030-01-10', $campaign->starts_at->toDateString());
        $this->assertSame(
            [$ids[0], $ids[1], $ids[2]],
            $campaign->posts()->orderBy('scheduled_at')->pluck('generation_id')->all(),
        );
        $this->assertSame(
            ['scheduled', 'scheduled', 'scheduled'],
            $campaign->posts()->pluck('status')->all(),
        );
    }

    public function test_campaign_rejects_foreign_generations_atomically(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $own = $this->makeGeneration($user);
        $foreign = $this->makeGeneration(User::factory()->create());

        $this->postJson('/api/campaigns', [
            'name' => 'کمپین',
            'generation_ids' => [$own->id, $foreign->id],
            'start_date' => '2030-01-10',
            'interval_days' => 1,
        ])->assertNotFound();

        $this->assertDatabaseCount('campaigns', 0);
        $this->assertDatabaseCount('scheduled_posts', 0);
    }

    public function test_campaign_validates_bounds(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $generation = $this->makeGeneration($user);

        $this->postJson('/api/campaigns', [
            'name' => '',
            'generation_ids' => [$generation->id],
            'start_date' => '2030-01-10',
            'interval_days' => 1,
        ])->assertUnprocessable();

        $this->postJson('/api/campaigns', [
            'name' => 'کمپین',
            'generation_ids' => [$generation->id],
            'start_date' => '2030-01-10',
            'interval_days' => 0,
        ])->assertUnprocessable();

        $this->postJson('/api/campaigns', [
            'name' => 'کمپین',
            'generation_ids' => [$generation->id],
            'start_date' => '2020-01-01',
            'interval_days' => 1,
        ])->assertUnprocessable();

        $this->postJson('/api/campaigns', [
            'name' => 'کمپین',
            'generation_ids' => array_fill(0, 31, $generation->id),
            'start_date' => '2030-01-10',
            'interval_days' => 1,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('campaigns', 0);
        $this->assertDatabaseCount('scheduled_posts', 0);
    }

    public function test_campaigns_endpoint_lists_campaigns_with_counts(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $ids = [$this->makeGeneration($user)->id, $this->makeGeneration($user)->id];
        $this->postJson('/api/campaigns', [
            'name' => 'کمپین من',
            'generation_ids' => $ids,
            'start_date' => '2030-01-10',
            'interval_days' => 3,
        ])->assertCreated();

        $items = $this->getJson('/api/campaigns')->assertOk()->json('data');

        $this->assertCount(1, $items);
        $this->assertSame('کمپین من', $items[0]['name']);
        $this->assertSame(2, $items[0]['posts_count']);
        $this->assertSame('2030-01-10', $items[0]['scheduled_at_range']['first']);
        $this->assertSame('2030-01-13', $items[0]['scheduled_at_range']['last']);

        // Another user sees an empty calendar.
        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/campaigns')->assertOk()->assertJsonPath('data', []);
    }

    public function test_dashboard_exposes_the_calendar_controls(): void
    {
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('data-cal-grid', false)
            ->assertSee('data-campaign-form', false)
            ->assertSee('تقویم محتوا', false);
    }

    private function makeGeneration(User $user): Generation
    {
        $product = $user->products()->create(['name' => 'محصول تقویم', 'description' => 'توضیح']);
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
            'prompt_hash' => hash('sha256', 'prompt'.uniqid('', true)),
            'metadata' => [],
        ]);
    }

    private function makePost(User $user, Generation $generation, string $date): ScheduledPost
    {
        return ScheduledPost::query()->create([
            'user_id' => $user->id,
            'generation_id' => $generation->id,
            'scheduled_at' => $date,
            'status' => 'scheduled',
        ]);
    }
}
