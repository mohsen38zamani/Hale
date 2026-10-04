<?php

namespace App\Domains\Notifications\Controllers;

use App\Domains\Notifications\Models\PushSubscription;
use App\Domains\Notifications\Services\WebPushSender;
use App\Http\Controllers\Controller;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushController extends Controller
{
    use ApiResponse;

    public function publicKey(WebPushSender $sender): JsonResponse
    {
        return $this->success([
            'enabled' => $sender->enabled(),
            'public_key' => $sender->enabled() ? (string) config('webpush.vapid_public_key') : null,
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:200'],
            'keys.auth' => ['required', 'string', 'max:200'],
        ], [
            'endpoint.required' => 'آدرس اشتراک اعلان الزامی است.',
            'endpoint.url' => 'آدرس اشتراک اعلان نامعتبر است.',
            'endpoint.max' => 'آدرس اشتراک اعلان خیلی طولانی است.',
            'keys.p256dh.required' => 'کلید رمزنگاری اعلان الزامی است.',
            'keys.p256dh.max' => 'کلید رمزنگاری اعلان نامعتبر است.',
            'keys.auth.required' => 'کلید احراز اعلان الزامی است.',
            'keys.auth.max' => 'کلید احراز اعلان نامعتبر است.',
        ]);

        // Remove any stale subscription on the same endpoint belonging to
        // another user (e.g. shared device or re-login) to prevent notification leaks.
        PushSubscription::query()
            ->where('endpoint', $data['endpoint'])
            ->where('user_id', '!=', $request->user()->getKey())
            ->delete();

        $subscription = PushSubscription::query()->updateOrCreate(
            ['user_id' => $request->user()->getKey(), 'endpoint' => $data['endpoint']],
            ['p256dh' => $data['keys']['p256dh'], 'auth' => $data['keys']['auth']],
        );

        return $this->success(['subscribed' => true, 'id' => $subscription->getKey()], 201);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:2000'],
        ], [
            'endpoint.required' => 'آدرس اشتراک اعلان الزامی است.',
            'endpoint.url' => 'آدرس اشتراک اعلان نامعتبر است.',
        ]);

        PushSubscription::query()
            ->where('user_id', $request->user()->getKey())
            ->where('endpoint', $data['endpoint'])
            ->delete();

        return $this->success(['subscribed' => false]);
    }
}
