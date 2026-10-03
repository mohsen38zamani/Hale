<?php

namespace Tests\Feature\Notifications;

use App\Domains\Notifications\Models\PushSubscription;
use App\Domains\Notifications\Notifications\WelcomeNotification;
use App\Domains\Notifications\Services\WebPushSender;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Minishlink\WebPush\VAPID;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array<string, mixed>> */
    private array $history = [];

    private function fakePushClient(array $statuses): void
    {
        $mock = new MockHandler(array_map(fn (int $status): Response => new Response($status), $statuses));
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        $this->app->instance(WebPushSender::class, new WebPushSender(new Client(['handler' => $stack])));
    }

    private function configureVapid(): void
    {
        $keys = VAPID::createVapidKeys();

        config([
            'webpush.vapid_public_key' => $keys['publicKey'],
            'webpush.vapid_private_key' => $keys['privateKey'],
            'webpush.subject' => 'mailto:test@hale.test',
        ]);
    }

    private function base64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * A valid uncompressed P-256 point plus a 16-byte auth secret, in the
     * base64url form push services hand to the browser.
     *
     * @return array{p256dh: string, auth: string}
     */
    private function validSubscriptionKeys(): array
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $details = openssl_pkey_get_details($key);

        return [
            'p256dh' => $this->base64url("\x04".$details['ec']['x'].$details['ec']['y']),
            'auth' => $this->base64url(random_bytes(16)),
        ];
    }

    private function subscribe(User $user, string $endpoint = 'https://push.example.test/subscription'): PushSubscription
    {
        return PushSubscription::query()->create([
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            ...$this->validSubscriptionKeys(),
        ]);
    }

    public function test_push_endpoints_require_authentication(): void
    {
        $this->getJson('/api/notifications/push/public-key')->assertUnauthorized();
        $this->postJson('/api/notifications/push/subscriptions', [])->assertUnauthorized();
        $this->deleteJson('/api/notifications/push/subscriptions', [])->assertUnauthorized();
    }

    public function test_user_can_subscribe_update_and_unsubscribe(): void
    {
        Sanctum::actingAs($user = User::factory()->create());
        $endpoint = 'https://fcm.googleapis.com/wp/fcm/example';

        $this->postJson('/api/notifications/push/subscriptions', [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-key'],
        ])->assertCreated()->assertJsonPath('data.subscribed', true);
        $this->assertDatabaseCount('push_subscriptions', 1);

        // Re-subscribing the same endpoint updates keys, never duplicates.
        $this->postJson('/api/notifications/push/subscriptions', [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'new-p256dh', 'auth' => 'new-auth'],
        ])->assertCreated();
        $this->assertDatabaseCount('push_subscriptions', 1);
        $this->assertDatabaseHas('push_subscriptions', ['p256dh' => 'new-p256dh']);

        $this->deleteJson('/api/notifications/push/subscriptions', ['endpoint' => $endpoint])
            ->assertOk()
            ->assertJsonPath('data.subscribed', false);
        $this->assertDatabaseCount('push_subscriptions', 0);

        // Idempotent unsubscribe.
        $this->deleteJson('/api/notifications/push/subscriptions', ['endpoint' => $endpoint])->assertOk();
    }

    public function test_subscribe_validates_the_payload(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/notifications/push/subscriptions', ['endpoint' => 'not-a-url'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
    }

    public function test_public_key_endpoint_reports_enabled_state(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/notifications/push/public-key')
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.public_key', null);

        $this->configureVapid();

        $response = $this->getJson('/api/notifications/push/public-key')->assertOk();
        $this->assertTrue($response->json('data.enabled'));
        $this->assertNotEmpty($response->json('data.public_key'));
    }

    public function test_sender_is_blocked_without_vapid_keys(): void
    {
        $this->fakePushClient([201]);
        Sanctum::actingAs($user = User::factory()->create());
        $this->subscribe($user);

        $sender = app(WebPushSender::class);
        $this->assertFalse($sender->enabled());
        $this->assertSame(0, $sender->sendToUser($user, 'سلام', '/dashboard'));
        $this->assertCount(0, $this->history);
    }

    public function test_sender_delivers_payload_and_prunes_expired_subscriptions(): void
    {
        $this->configureVapid();
        Sanctum::actingAs($user = User::factory()->create());
        $alive = $this->subscribe($user, 'https://push.example.test/alive');
        $expired = $this->subscribe($user, 'https://push.example.test/expired');
        $this->fakePushClient([201, 410]);

        $sent = app(WebPushSender::class)->sendToUser($user, 'تولید شما با موفقیت تکمیل شد.', '/dashboard');

        $this->assertSame(1, $sent);
        $this->assertCount(2, $this->history);
        $this->assertSame('https://push.example.test/alive', (string) $this->history[0]['request']->getUri());
        $this->assertDatabaseHas('push_subscriptions', ['id' => $alive->id]);
        $this->assertDatabaseMissing('push_subscriptions', ['id' => $expired->id]);
    }

    public function test_marked_notifications_send_push_but_plain_ones_do_not(): void
    {
        $this->configureVapid();
        Sanctum::actingAs($user = User::factory()->create());
        $this->subscribe($user);
        $this->fakePushClient([201]);

        $user->notify(new PushTestNotification);
        $this->assertCount(1, $this->history);

        // Welcome notifications are not marked ShouldWebPush.
        $user->notify(new WelcomeNotification);
        $this->assertCount(1, $this->history);
    }

    public function test_dashboard_exposes_the_push_toggle(): void
    {
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('data-push-subscribe', false);
    }
}
