<?php

namespace Tests\Feature\Notifications;

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
        $user->notify(new WelcomeNotification());
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
        $user->notify(new WelcomeNotification());
        $user->notify(new WelcomeNotification());
        Sanctum::actingAs($user);

        $this->postJson('/api/notifications/read-all')->assertOk();
        $this->getJson('/api/notifications')->assertJsonPath('data.unread_count', 0);
    }

    public function test_email_notifications_are_enabled_only_for_users_with_email(): void
    {
        $withEmail = User::factory()->create(['email' => 'hale@example.com']);
        $withoutEmail = User::factory()->create(['email' => null]);
        $notification = new WelcomeNotification();

        $this->assertSame(['database', 'mail'], $notification->via($withEmail));
        $this->assertSame(['database'], $notification->via($withoutEmail));
    }
}
