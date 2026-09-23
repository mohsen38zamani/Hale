<?php

use App\Domains\Admin\Controllers\AdminController;
use App\Domains\Auth\Controllers\AuthController;
use App\Domains\Billing\Controllers\PlanController;
use App\Domains\Billing\Services\SubscriptionService;
use App\Domains\Creative\Controllers\CreativeController;
use App\Domains\Credits\Controllers\CreditController;
use App\Domains\Credits\Services\CreditService;
use App\Domains\Generations\Controllers\GenerationController;
use App\Domains\Media\Controllers\ProductAssetController;
use App\Domains\Notifications\Controllers\NotificationController;
use App\Domains\Products\Controllers\ProductController;
use App\Http\Controllers\HealthController;
use App\Http\Middleware\AdminMiddleware;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth-attempt');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-attempt');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth-attempt');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth-attempt');
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware('throttle:email-verify')->name('email.verification.verify');
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])->middleware(['auth:sanctum', 'throttle:email-verify']);
    Route::post('/phone/send-code', [AuthController::class, 'sendPhoneVerification'])->middleware(['auth:sanctum', 'throttle:sms-send']);
    Route::post('/verify-phone', [AuthController::class, 'verifyPhone'])->middleware(['auth:sanctum', 'throttle:sms-verify']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::get('/user/profile', function (CreditService $credits, SubscriptionService $subscriptions) {
    $user = request()->user();
    $subscriptions->syncExpired($user);
    $user->refresh();
    $activeSub = $subscriptions->active($user);

    return response()->json([
        'success' => true,
        'data' => [
            ...$user->only(['id', 'name', 'email', 'phone', 'plan_key']),
            'credits_balance' => $credits->account($user)->balance,
            'subscription' => $activeSub ? [
                'plan_key' => $activeSub->plan_key,
                'starts_at' => $activeSub->starts_at?->toIso8601String(),
                'ends_at' => $activeSub->ends_at?->toIso8601String(),
            ] : null,
        ],
        'error' => null,
    ]);
})->middleware('auth:sanctum');
Route::get('/plans', [PlanController::class, 'index']);
Route::post('/webhooks/payment', [PlanController::class, 'webhook'])->middleware('throttle:payment-webhook');
Route::get('/payments/zarinpal/callback', [PlanController::class, 'zarinpalCallback'])->middleware('throttle:payment-webhook');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('products', ProductController::class);
    Route::post('/products/{product}/assets', [ProductAssetController::class, 'store']);
    Route::get('/products/{product}/assets/{asset}/download', [ProductAssetController::class, 'download']);
    Route::delete('/products/{product}/assets/{asset}', [ProductAssetController::class, 'destroy']);
    Route::get('/creative/options', [CreativeController::class, 'options']);
    Route::post('/creative/preview', [CreativeController::class, 'preview']);
    Route::get('/generations', [GenerationController::class, 'index']);
    Route::post('/generations', [GenerationController::class, 'store'])->middleware(['verified', 'throttle:generation']);
    Route::get('/generations/{generation}', [GenerationController::class, 'show']);
    Route::post('/generations/{generation}/retry', [GenerationController::class, 'retry'])->middleware(['verified', 'throttle:generation']);
    Route::post('/generations/{generation}/regenerate', [GenerationController::class, 'regenerate'])->middleware(['verified', 'throttle:generation']);
    Route::post('/generations/{generation}/feedback', [GenerationController::class, 'feedback']);
    Route::get('/generations/{generation}/download', [GenerationController::class, 'download']);
    Route::get('/credits/balance', [CreditController::class, 'balance']);
    Route::get('/credits/transactions', [CreditController::class, 'transactions']);
    Route::post('/credits/estimate', [CreditController::class, 'estimate']);
    Route::get('/payments', [PlanController::class, 'payments'])->middleware('throttle:payment-history');
    Route::get('/payments/{payment}/receipt', [PlanController::class, 'receipt']);
    Route::get('/payments/{payment}/invoice', [PlanController::class, 'invoice']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::post('/subscriptions/checkout', [PlanController::class, 'checkout'])->middleware(['verified', 'throttle:checkout']);
});

Route::get('/health', [HealthController::class, 'check']);

Route::prefix('admin')->middleware(['auth:sanctum', AdminMiddleware::class, 'throttle:admin'])->group(function (): void {
    Route::get('/metrics', [AdminController::class, 'metrics']);
    Route::get('/users', [AdminController::class, 'users']);
    Route::get('/users/{user}', [AdminController::class, 'user']);
    Route::get('/generations', [AdminController::class, 'generations']);
    Route::post('/users/{user}/refund', [AdminController::class, 'refund']);
    Route::get('/settings', [AdminController::class, 'settings']);
    Route::post('/settings', [AdminController::class, 'updateSettings']);
});
