<?php

namespace Tests\Feature\Credits;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Credits\Exceptions\PlanLimitReached;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Credits\Services\PlanLimitService;
use App\Domains\Generations\Jobs\ProcessGeneration;
use App\Domains\Generations\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Race / double-spend coverage for plan limits:
 * - the locked in-transaction re-check closes the window between the early
 *   (unlocked) check and generation creation,
 * - a rejected request leaves no orphan rows behind,
 * - a failed credit reservation rolls the whole unit of work back.
 */
class PlanLimitRaceTest extends TestCase
{
    use RefreshDatabase;

    /** User on the starter plan (video limit = 2) with `used` videos already consumed. */
    private function starterUserWithVideosUsed(int $used): User
    {
        $user = User::factory()->create(['plan_key' => 'starter']);
        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(25),
        ]);
        app(CreditService::class)->initialize($user, 200);

        for ($i = 0; $i < $used; $i++) {
            $this->seedVideo($user, 'seed-'.$i);
        }

        return $user;
    }

    private function seedVideo(User $user, string $seed): Generation
    {
        $product = $user->products()->create(['name' => 'کیف '.$seed]);
        $project = $user->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_reel',
            'brief' => ['summary' => $seed],
            'prompt' => 'video '.$seed,
        ]);

        return Generation::query()->create([
            'user_id' => $user->id,
            'creative_project_id' => $project->id,
            'type' => 'video',
            'status' => 'completed',
            'prompt_hash' => hash('sha256', $seed),
        ]);
    }

    public function test_locked_recheck_inside_transaction_rejects_interleaved_request(): void
    {
        $user = $this->starterUserWithVideosUsed(1);
        $limits = app(PlanLimitService::class);

        // Request B passes the early, unlocked check while still under the limit.
        $limits->ensureCanGenerate($user, 'video');

        // Request A commits its generation in between (limit now reached).
        $this->seedVideo($user, 'winner');

        // Request B now enters its transaction: the locked re-check must reject it,
        // even though its early check succeeded.
        try {
            DB::transaction(fn () => $limits->ensureCanGenerateLocked($user, 'video'));
            $this->fail('Locked re-check must reject a request that passed the early check.');
        } catch (PlanLimitReached $exception) {
            $this->assertSame('محدودیت تولید ویدئوی پلن شما به پایان رسیده است.', $exception->getMessage());
        }

        $this->assertSame(2, $user->generations()->where('type', 'video')->count());
    }

    public function test_back_to_back_request_at_limit_is_rejected_without_orphan_rows(): void
    {
        Queue::fake();
        $user = $this->starterUserWithVideosUsed(1);
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'عطر']);
        $payload = [
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_reel',
            'video_duration_seconds' => 5,
        ];

        // First request fits (used 1 -> 2 of 2).
        $this->postJson('/api/generations', $payload)->assertAccepted();
        $projectsAfterFirst = $user->creativeProjects()->count();

        // Second request at the boundary must be rejected by the plan limit.
        $this->postJson('/api/generations', $payload)
            ->assertStatus(402)
            ->assertJsonPath('error.code', 'PLAN_LIMIT_REACHED');

        $this->assertSame(2, $user->generations()->where('type', 'video')->count());
        $this->assertSame($projectsAfterFirst, $user->creativeProjects()->count(),
            'Rejected request must not leave an orphan creative project.');
        Queue::assertPushed(ProcessGeneration::class, 1);
    }

    public function test_failed_reservation_rolls_back_project_generation_and_ledger(): void
    {
        Queue::fake();
        $user = User::factory()->create(['plan_key' => 'free']);
        app(CreditService::class)->initialize($user, 0);
        Sanctum::actingAs($user);
        $product = $user->products()->create(['name' => 'عطر']);

        $this->postJson('/api/generations', [
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
        ])
            ->assertStatus(402)
            ->assertJsonPath('error.code', 'INSUFFICIENT_CREDITS');

        // Plan check passed (free image limit is not reached), but the reservation
        // failed inside the shared transaction: nothing may persist.
        $this->assertDatabaseCount('generations', 0);
        $this->assertDatabaseCount('creative_projects', 0);
        $this->assertSame(0, app(CreditService::class)->account($user)->fresh()->balance);
        $this->assertSame(0, app(CreditService::class)->account($user)->transactions()->count());
        Queue::assertNothingPushed();
    }
}
