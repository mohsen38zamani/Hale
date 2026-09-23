<?php

namespace Tests\Feature\Credits;

use App\Domains\Credits\Services\CreditService;
use App\Domains\Generations\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Double-spend protection for credit settlement:
 * - settle/refund lock the credit account before re-reading state,
 * - an atomic claim on the generation row ensures only one caller can consume
 *   or return a reservation, even when it holds a stale model instance,
 * - ledger rows exist exactly once per movement.
 */
class CreditDoubleSpendRaceTest extends TestCase
{
    use RefreshDatabase;

    private function createGeneration(User $user): Generation
    {
        $product = $user->products()->create(['name' => 'عطر']);
        $project = $user->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'minimal',
            'format' => 'instagram_post',
            'brief' => ['summary' => 'brief'],
            'prompt' => 'prompt',
        ]);

        return Generation::query()->create([
            'user_id' => $user->id,
            'creative_project_id' => $project->id,
            'type' => 'image',
            'status' => 'processing',
            'prompt_hash' => hash('sha256', 'prompt-'.uniqid()),
        ]);
    }

    private function ledger(User $user, string $type, ?int $generationId = null): int
    {
        return app(CreditService::class)->account($user)->transactions()
            ->where('type', $type)
            ->when($generationId !== null, fn ($query) => $query->where('generation_id', $generationId))
            ->count();
    }

    public function test_double_settle_charges_only_once(): void
    {
        $user = User::factory()->create();
        $credits = app(CreditService::class);
        $credits->initialize($user, 100);
        $generation = $this->createGeneration($user);

        $credits->reserve($user, $generation, 10);
        $credits->settle($generation);
        $credits->settle($generation); // concurrent duplicate must be a no-op

        $generation->refresh();
        $account = $credits->account($user)->fresh();

        $this->assertSame(10, $generation->credits_charged);
        $this->assertSame(0, $generation->credits_reserved);
        $this->assertSame(90, $account->balance);
        $this->assertSame(0, $account->reserved);
        $this->assertSame(10, $account->lifetime_used);
        $this->assertSame(1, $this->ledger($user, 'charge', $generation->id));
        $this->assertSame(1, $this->ledger($user, 'reserve', $generation->id));
    }

    public function test_double_refund_returns_credits_only_once(): void
    {
        $user = User::factory()->create();
        $credits = app(CreditService::class);
        $credits->initialize($user, 100);
        $generation = $this->createGeneration($user);

        $credits->reserve($user, $generation, 10);
        $credits->refund($generation);
        $credits->refund($generation); // concurrent duplicate must be a no-op

        $generation->refresh();
        $account = $credits->account($user)->fresh();

        $this->assertSame(0, $generation->credits_reserved);
        $this->assertSame(100, $account->balance);
        $this->assertSame(0, $account->reserved);
        $this->assertSame(1, $this->ledger($user, 'refund', $generation->id));
    }

    public function test_stale_generation_instance_cannot_double_refund(): void
    {
        $user = User::factory()->create();
        $credits = app(CreditService::class);
        $credits->initialize($user, 100);
        $generation = $this->createGeneration($user);
        $credits->reserve($user, $generation, 10);

        // A second request loads the generation while the reservation is still open,
        // then the first request refunds before the second one acts.
        $staleCopy = Generation::query()->findOrFail($generation->id);
        $credits->refund($generation);
        $credits->refund($staleCopy);

        $account = $credits->account($user)->fresh();
        $this->assertSame(100, $account->balance);
        $this->assertSame(0, $account->reserved);
        $this->assertSame(1, $this->ledger($user, 'refund', $generation->id));
    }

    public function test_stale_generation_instance_cannot_double_settle(): void
    {
        $user = User::factory()->create();
        $credits = app(CreditService::class);
        $credits->initialize($user, 100);
        $generation = $this->createGeneration($user);
        $credits->reserve($user, $generation, 10);

        $staleCopy = Generation::query()->findOrFail($generation->id);
        $credits->settle($generation);
        $credits->settle($staleCopy);

        $account = $credits->account($user)->fresh();
        $this->assertSame(90, $account->balance);
        $this->assertSame(0, $account->reserved);
        $this->assertSame(10, $account->lifetime_used);
        $this->assertSame(1, $this->ledger($user, 'charge', $generation->id));
    }

    public function test_refund_after_settle_does_not_return_consumed_credits(): void
    {
        $user = User::factory()->create();
        $credits = app(CreditService::class);
        $credits->initialize($user, 100);
        $generation = $this->createGeneration($user);

        $credits->reserve($user, $generation, 10);
        $credits->settle($generation);
        $credits->refund($generation); // reservation already consumed by settle

        $account = $credits->account($user)->fresh();
        $this->assertSame(90, $account->balance);
        $this->assertSame(10, $account->lifetime_used);
        $this->assertSame(1, $this->ledger($user, 'charge', $generation->id));
        $this->assertSame(0, $this->ledger($user, 'refund', $generation->id));
    }

    public function test_reserve_refund_reserve_cycle_keeps_ledger_and_balance_consistent(): void
    {
        $user = User::factory()->create();
        $credits = app(CreditService::class);
        $credits->initialize($user, 100);
        $generation = $this->createGeneration($user);

        $credits->reserve($user, $generation, 10);
        $credits->refund($generation);
        $credits->reserve($user, $generation, 10);

        $generation->refresh();
        $account = $credits->account($user)->fresh();

        $this->assertSame(10, $generation->credits_reserved);
        $this->assertSame(90, $account->balance);
        $this->assertSame(10, $account->reserved);
        $this->assertSame(2, $this->ledger($user, 'reserve', $generation->id));
        $this->assertSame(1, $this->ledger($user, 'refund', $generation->id));
    }
}
