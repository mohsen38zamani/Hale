<?php

namespace App\Providers;

use App\Domains\AI\Providers\Local\FakeGenerationProvider;
use App\Domains\AI\Router\ModelRouter;
use App\Domains\Billing\Contracts\PaymentGateway;
use App\Domains\Billing\Providers\FakePaymentGateway;
use App\Domains\Auth\Contracts\SmsProvider;
use App\Domains\Auth\Providers\FakeSmsProvider;
use App\Domains\Auth\Providers\SmsIrProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ModelRouter::class, fn () => new ModelRouter([
            $this->app->make(FakeGenerationProvider::class),
        ]));
        $this->app->singleton(SmsProvider::class, function ($app): SmsProvider {
            return match (config('services.sms.driver')) {
                'sms_ir' => $app->make(SmsIrProvider::class),
                default => $app->make(FakeSmsProvider::class),
            };
        });
        $this->app->singleton(PaymentGateway::class, function ($app): PaymentGateway {
            return match (config('payment.driver')) {
                default => $app->make(FakePaymentGateway::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
