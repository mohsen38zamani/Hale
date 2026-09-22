<?php

namespace Tests\Feature\Billing;

use App\Domains\Billing\Models\Subscription;
use App\Domains\Notifications\Notifications\SubscriptionExpiredNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_subscription_is_expired_by_artisan_command(): void
    {
        Notification::fake();

        $user = User::factory()->create(['plan_key' => 'starter']);
        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->addHour(),
        ]);

        $this->travel(2)->hours();

        $this->artisan('subscriptions:expire')
            ->expectsOutput('Expired 1 subscriptions.')
            ->assertSuccessful();

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'status' => 'expired',
        ]);

        $this->assertSame('free', $user->fresh()->plan_key);
        Notification::assertSentTo($user, SubscriptionExpiredNotification::class);
    }

    public function test_subscription_model_hook_prevents_active_status_if_ended_in_past(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'starts_at' => now()->subDays(40),
            'ends_at' => now()->subDays(10),
        ]);

        $this->assertSame('expired', $subscription->fresh()->status);
    }

    public function test_user_profile_endpoint_syncs_expired_subscription(): void
    {
        $user = User::factory()->create(['plan_key' => 'starter']);
        Subscription::query()->create([
            'user_id' => $user->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->addHour(),
        ]);

        $this->travel(2)->hours();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user/profile')
            ->assertOk()
            ->assertJsonPath('data.plan_key', 'free')
            ->assertJsonPath('data.subscription', null);

        $this->assertSame('free', $user->fresh()->plan_key);
    }

    public function test_renewing_same_plan_extends_ends_at_smoothly(): void
    {
        config(['payment.driver' => 'fake']);
        config(['payment.webhook_secret' => 'test-secret']);

        $user = User::factory()->create(['plan_key' => 'starter']);
        $existingEndsAt = Carbon::now()->addDays(5);
        $initialSub = Subscription::create([
            'user_id' => $user->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'starts_at' => now()->subDays(25),
            'ends_at' => $existingEndsAt,
        ]);

        Sanctum::actingAs($user);
        $checkout = $this->postJson('/api/subscriptions/checkout', ['plan_key' => 'starter'])->json('data');
        $signature = hash_hmac('sha256', $checkout['authority'].'|paid', 'test-secret');

        $this->postJson('/api/webhooks/payment', [
            'authority' => $checkout['authority'],
            'status' => 'paid',
        ], ['X-Payment-Signature' => $signature])->assertOk();

        $initialSub->refresh();
        $this->assertSame('active', $initialSub->status);
        // Expect ends_at to have been extended by 1 month from existingEndsAt
        $expectedEndsAt = $existingEndsAt->copy()->addMonths((int) config('payment.subscription_months'));
        $this->assertSame($expectedEndsAt->toDateTimeString(), $initialSub->ends_at->toDateTimeString());
    }

    public function test_upgrading_plan_expires_previous_subscription_and_creates_new_one(): void
    {
        config(['payment.driver' => 'fake']);
        config(['payment.webhook_secret' => 'test-secret']);

        $user = User::factory()->create(['plan_key' => 'starter']);
        $initialSub = Subscription::create([
            'user_id' => $user->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->addDays(20),
        ]);

        Sanctum::actingAs($user);
        $checkout = $this->postJson('/api/subscriptions/checkout', ['plan_key' => 'creator'])->json('data');
        $signature = hash_hmac('sha256', $checkout['authority'].'|paid', 'test-secret');

        $this->postJson('/api/webhooks/payment', [
            'authority' => $checkout['authority'],
            'status' => 'paid',
        ], ['X-Payment-Signature' => $signature])->assertOk();

        $initialSub->refresh();
        $this->assertSame('expired', $initialSub->status);

        $newSub = Subscription::query()->where('user_id', $user->id)->where('plan_key', 'creator')->first();
        $this->assertNotNull($newSub);
        $this->assertSame('active', $newSub->status);
        $this->assertSame('creator', $user->fresh()->plan_key);
    }
}
