<?php

namespace App\Providers;

use App\Domains\AI\Providers\Google\GoogleImagenProvider;
use App\Domains\AI\Providers\Google\GoogleVeoProvider;
use App\Domains\AI\Providers\Local\FakeGenerationProvider;
use App\Domains\AI\Router\ModelRouter;
use App\Domains\Auth\Contracts\SmsProvider;
use App\Domains\Auth\Providers\FakeSmsProvider;
use App\Domains\Auth\Providers\SmsIrProvider;
use App\Domains\Billing\Contracts\PaymentGateway;
use App\Domains\Billing\Providers\FakePaymentGateway;
use App\Domains\Billing\Providers\ZarinpalPaymentGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ModelRouter::class, function ($app): ModelRouter {
            $providers = [];
            if (config('ai.driver') === 'google') {
                $providers[] = $app->make(GoogleImagenProvider::class);
                $providers[] = $app->make(GoogleVeoProvider::class);
            }
            $providers[] = $app->make(FakeGenerationProvider::class);

            return new ModelRouter($providers);
        });
        $this->app->singleton(SmsProvider::class, function ($app): SmsProvider {
            return match (config('services.sms.driver')) {
                'sms_ir' => $app->make(SmsIrProvider::class),
                default => $app->make(FakeSmsProvider::class),
            };
        });
        $this->app->singleton(PaymentGateway::class, function ($app): PaymentGateway {
            return match (config('payment.driver')) {
                'zarinpal' => $app->make(ZarinpalPaymentGateway::class),
                default => $app->make(FakePaymentGateway::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('generation', function (Request $request) {
            $user = $request->user();
            $limit = match ($user?->plan_key) {
                'creator' => 30,
                'starter' => 15,
                default => 5,
            };

            return Limit::perMinute($limit)->by((string) ($user?->id ?? $request->ip()));
        });

        RateLimiter::for('checkout', fn (Request $request) => Limit::perMinute(5)->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('payment-history', fn (Request $request) => Limit::perMinute(30)->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('payment-webhook', fn (Request $request) => Limit::perMinute(60)->by((string) $request->ip()));

        RateLimiter::for('auth-attempt', fn (Request $request) => Limit::perMinute(10)->by((string) ($request->input('email') ?? $request->input('phone') ?? $request->ip())));
        RateLimiter::for('sms-send', fn (Request $request) => Limit::perMinutes(10, 3)->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('sms-verify', fn (Request $request) => Limit::perMinutes(10, 10)->by((string) ($request->user()?->id ?? $request->ip())));
    }
}
