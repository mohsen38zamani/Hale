<?php

namespace Tests\Feature\Credits;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Credits\Exceptions\PlanLimitReached;
use App\Domains\Credits\Services\PlanLimitService;
use App\Domains\Generations\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanLimitWindowTest extends TestCase
{
    use RefreshDatabase;

    public function test_usage_counts_from_active_subscription_starts_at(): void
    {
        $user = User::factory()->create(['plan_key' => 'starter']);
        $product = $user->products()->create(['name' => 'Dress', 'status' => 'active']);
        $project = $user->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'introduction',
            'style' => 'minimal',
            'format' => 'instagram_reel',
            'brief' => ['summary' => 'reel brief'],
            'prompt' => 'video prompt',
        ]);

        // Subscription started 5 days ago (starter limit for video is 2)
        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->addDays(25),
        ]);

        // A generation created 10 days ago (BEFORE subscription started)
        Generation::query()->create([
            'user_id' => $user->id,
            'creative_project_id' => $project->id,
            'type' => 'video',
            'status' => 'completed',
            'prompt_hash' => hash('sha256', 'old-prompt'),
            'created_at' => now()->subDays(10),
        ]);

        $service = app(PlanLimitService::class);

        // Should still be able to generate (0 used in active window, limit is 2)
        $service->ensureCanGenerate($user, 'video');

        // Create 2 generations inside active window
        Generation::query()->create([
            'user_id' => $user->id,
            'creative_project_id' => $project->id,
            'type' => 'video',
            'status' => 'completed',
            'prompt_hash' => hash('sha256', 'prompt-1'),
            'created_at' => now()->subDays(2),
        ]);

        Generation::query()->create([
            'user_id' => $user->id,
            'creative_project_id' => $project->id,
            'type' => 'video',
            'status' => 'completed',
            'prompt_hash' => hash('sha256', 'prompt-2'),
            'created_at' => now()->subDay(),
        ]);

        // Now limit is reached
        $this->expectException(PlanLimitReached::class);
        $this->expectExceptionMessage('محدودیت تولید ویدئوی پلن شما به پایان رسیده است.');

        $service->ensureCanGenerate($user, 'video');
    }
}
