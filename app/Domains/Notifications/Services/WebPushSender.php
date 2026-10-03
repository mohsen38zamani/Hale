<?php

namespace App\Domains\Notifications\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Psr\Http\Client\ClientInterface;
use Throwable;

/**
 * Best-effort Web Push sender. Without VAPID keys it stays silent; expired
 * subscriptions (404/410 from the push service) are pruned automatically and
 * transport failures are logged instead of breaking the caller.
 */
class WebPushSender
{
    public function __construct(private readonly ?ClientInterface $client = null) {}

    public function enabled(): bool
    {
        return filled(config('webpush.vapid_public_key')) && filled(config('webpush.vapid_private_key'));
    }

    /**
     * Send a push message to every subscription of the user.
     *
     * @return int number of accepted deliveries
     */
    public function sendToUser(User $user, string $message, string $url): int
    {
        if (! $this->enabled()) {
            Log::info('webpush.disabled', ['user_id' => $user->getKey()]);

            return 0;
        }

        $subscriptions = $user->pushSubscriptions()->get();
        if ($subscriptions->isEmpty()) {
            return 0;
        }

        try {
            $webPush = $this->makeWebPush();
        } catch (Throwable $exception) {
            Log::warning('webpush.init_failed', ['error' => $exception->getMessage()]);

            return 0;
        }

        $payload = json_encode(['title' => 'Hale', 'body' => $message, 'url' => $url], JSON_UNESCAPED_UNICODE);
        $sent = 0;

        foreach ($subscriptions as $subscription) {
            try {
                $report = $webPush->sendOneNotification(
                    new Subscription($subscription->endpoint, $subscription->p256dh, $subscription->auth),
                    $payload,
                );

                if ($report->isSubscriptionExpired()) {
                    $subscription->delete();

                    continue;
                }

                if ($report->isSuccess()) {
                    $sent++;

                    continue;
                }

                Log::warning('webpush.send_failed', [
                    'subscription_id' => $subscription->getKey(),
                    'status' => $report->getResponse()?->getStatusCode(),
                    'reason' => $report->getReason(),
                ]);
            } catch (Throwable $exception) {
                Log::warning('webpush.send_failed', [
                    'subscription_id' => $subscription->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    private function makeWebPush(): WebPush
    {
        return new WebPush(
            [
                'VAPID' => [
                    'subject' => (string) config('webpush.subject'),
                    'publicKey' => (string) config('webpush.vapid_public_key'),
                    'privateKey' => (string) config('webpush.vapid_private_key'),
                ],
            ],
            ['TTL' => (int) config('webpush.ttl', 3600)],
            $this->client,
        );
    }
}
