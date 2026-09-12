<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Http\Middleware\RequestId;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(RequestId::class);
    })
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('media:cleanup-expired')->daily();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));
        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['success' => false, 'data' => null, 'message' => 'داده‌های ورودی معتبر نیستند.', 'errors' => $exception->errors(), 'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'داده‌های ورودی معتبر نیستند.', 'details' => $exception->errors()]], 422);
        });
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json(['success' => false, 'data' => null, 'error' => ['code' => 'UNAUTHENTICATED', 'message' => 'ابتدا وارد حساب کاربری شوید.']], 401);
        });
    })->create();
