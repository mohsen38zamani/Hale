<?php

use App\Http\Middleware\AuthenticateFromCookie;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\PreventBannedUser;
use App\Http\Middleware\RequestId;
use App\Support\AuthTokenCookie;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(RequestId::class);
        $middleware->append(AuthenticateFromCookie::class);
        $middleware->append(PreventBannedUser::class);
        $middleware->alias(['verified' => EnsureEmailIsVerified::class]);
        // Auth token cookie is intentionally raw: it is only consumed by the
        // AuthenticateFromCookie middleware on API routes and must never be
        // serialized into a readable JS-accessible value.
        $middleware->encryptCookies([AuthTokenCookie::NAME]);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('media:cleanup-expired')->daily();
        $schedule->command('subscriptions:expire')->daily();
        $schedule->command('generations:recover-stale')->everyFiveMinutes();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $firstError = collect($exception->errors())->flatten()->first() ?: 'داده‌های ورودی معتبر نیستند.';

            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $firstError,
                'errors' => $exception->errors(),
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => $firstError,
                    'details' => $exception->errors(),
                ],
            ], 422);
        });
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['success' => false, 'data' => null, 'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'ابتدا وارد حساب کاربری شوید.']], 401);
        });
    })->create();
