<?php

namespace Tests\Feature\Generations;

use App\Domains\Credits\Services\CreditService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class StaleGenerationRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_lease_generation_is_recovered_and_refunded(): void
    {
        $user = User::factory()->create();
        $creditService = app(CreditService::class);
        $creditService->grantBonus($user, 50, 'initial_bonus');

        $product = $user->products()->create(['name' => 'تست محصول']);
        $project = $user->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'minimal',
            'format' => 'instagram_post',
            'prompt' => 'prompt test',
            'brief' => ['test' => true],
        ]);

        $generation = $project->generations()->create([
            'user_id' => $user->id,
            'type' => 'image',
            'status' => 'processing',
            'prompt_hash' => hash('sha256', 'test'),
            'credits_reserved' => 10,
            'processing_lease_expires_at' => now()->subMinutes(10), // expired lease!
        ]);

        // Account reserved credits
        $account = $creditService->account($user);
        $account->update(['reserved' => 10, 'balance' => 40]);

        $this->assertSame('processing', $generation->status);

        Artisan::call('generations:recover-stale');

        $generation->refresh();
        $this->assertSame('failed', $generation->status);
        $this->assertNull($generation->processing_lease_expires_at);

        $account->refresh();
        $this->assertSame(50, $account->balance);
        $this->assertSame(0, $account->reserved);
    }
}
