<?php

namespace Tests\Feature\Notifications;

use App\Domains\Notifications\Notifications\CreditsLowNotification;
use App\Domains\Notifications\Notifications\GenerationStatusNotification;
use App\Domains\Notifications\Notifications\PaymentSucceededNotification;
use App\Domains\Notifications\Notifications\SubscriptionExpiredNotification;
use App\Domains\Notifications\Notifications\WelcomeNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_and_read_notifications(): void
    {
        $user = User::factory()->create();
        $user->notify(new WelcomeNotification);
        Sanctum::actingAs($user);

        $notification = $this->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.unread_count', 1)
            ->json('data.items.0');

        $this->postJson("/api/notifications/{$notification['id']}/read")
            ->assertOk()
            ->assertJsonPath('data.id', $notification['id']);

        $this->getJson('/api/notifications')->assertJsonPath('data.unread_count', 0);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $user = User::factory()->create();
        $user->notify(new WelcomeNotification);
        $user->notify(new WelcomeNotification);
        Sanctum::actingAs($user);

        $this->postJson('/api/notifications/read-all')->assertOk();
        $this->getJson('/api/notifications')->assertJsonPath('data.unread_count', 0);
    }

    public function test_email_notifications_are_enabled_only_for_users_with_email(): void
    {
        $withEmail = User::factory()->create(['email' => 'hale@example.com']);
        $withoutEmail = User::factory()->create(['email' => null]);
        $notification = new WelcomeNotification;

        $this->assertSame(['database', 'mail'], $notification->via($withEmail));
        $this->assertSame(['database'], $notification->via($withoutEmail));
    }

    public function test_payment_succeeded_notification_is_stored_and_rendered(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $payment = $user->payments()->create([
            'plan_key' => 'starter',
            'amount' => 4_990_000,
            'gateway' => 'zarinpal',
            'status' => 'paid',
            'reference' => 'REF123456',
            'idempotency_key' => 'pay-notif-1',
        ]);

        $notification = new PaymentSucceededNotification($payment);
        $user->notify($notification);

        $dbNotification = $user->notifications()->first();
        $this->assertNotNull($dbNotification);
        $this->assertSame('payment_succeeded', $dbNotification->data['kind']);
        $this->assertSame('starter', $dbNotification->data['plan_key']);
        $this->assertSame(4_990_000, $dbNotification->data['amount']);
        $this->assertSame('REF123456', $dbNotification->data['reference']);

        $mail = $notification->toMail($user);
        $this->assertStringContainsString('پرداخت', $mail->subject);
        $this->assertStringContainsString('4990000', $mail->introLines[1]);
    }

    public function test_generation_status_notification_for_completed_and_failed(): void
    {
        $user = User::factory()->create(['email' => 'creator@example.com']);
        $product = $user->products()->create(['name' => 'تست']);
        $project = $user->creativeProjects()->create([
            'product_id' => $product->id,
            'goal' => 'sales',
            'style' => 'luxury',
            'format' => 'instagram_post',
            'brief' => [],
            'prompt' => 'test prompt',
        ]);
        $generation = $user->generations()->create([
            'creative_project_id' => $project->id,
            'type' => 'image',
            'status' => 'completed',
            'prompt_hash' => hash('sha256', 'test prompt'),
        ]);

        $completedNotif = new GenerationStatusNotification($generation, 'completed');
        $user->notify($completedNotif);

        $this->assertSame('generation_completed', $user->notifications()->first()->data['kind']);
        $mail = $completedNotif->toMail($user);
        $this->assertStringContainsString('آماده', $mail->subject);

        $failedNotif = new GenerationStatusNotification($generation, 'failed');
        $failMail = $failedNotif->toMail($user);
        $this->assertStringContainsString('ناموفق', $failMail->subject);
    }

    public function test_notifications_never_route_to_sms_channel(): void
    {
        $userWithEmailAndPhone = User::factory()->create(['email' => 'user@example.com', 'phone' => '+989121112233']);
        $userWithPhoneOnly = User::factory()->create(['email' => null, 'phone' => '+989124445566']);

        $notifications = [
            new WelcomeNotification,
            new CreditsLowNotification(5),
            new SubscriptionExpiredNotification('starter'),
        ];

        foreach ($notifications as $notification) {
            $channelsWithEmail = $notification->via($userWithEmailAndPhone);
            $channelsPhoneOnly = $notification->via($userWithPhoneOnly);

            $this->assertNotContains('sms', $channelsWithEmail);
            $this->assertNotContains('sms', $channelsPhoneOnly);
            $this->assertSame(['database', 'mail'], $channelsWithEmail);
            $this->assertSame(['database'], $channelsPhoneOnly);
        }
    }
}
