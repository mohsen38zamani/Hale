<?php

namespace App\Providers;

use App\Domains\AI\Providers\Local\FakeGenerationProvider;
use App\Domains\AI\Router\ModelRouter;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
