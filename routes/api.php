<?php

use App\Domains\Auth\Controllers\AuthController;
use App\Domains\Billing\Controllers\PlanController;
use App\Domains\Creative\Controllers\CreativeController;
use App\Domains\Credits\Controllers\CreditController;
use App\Domains\Generations\Controllers\GenerationController;
use App\Domains\Media\Controllers\ProductAssetController;
use App\Domains\Products\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::get('/user/profile', fn () => response()->json(['success' => true, 'data' => request()->user()->only(['id', 'name', 'email', 'phone', 'credits_balance', 'plan_key']), 'error' => null]))->middleware('auth:sanctum');
Route::get('/plans', [PlanController::class, 'index']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::apiResource('products', ProductController::class);
    Route::post('/products/{product}/assets', [ProductAssetController::class, 'store']);
    Route::get('/creative/options', [CreativeController::class, 'options']);
    Route::post('/creative/preview', [CreativeController::class, 'preview']);
    Route::apiResource('generations', GenerationController::class)->only(['index', 'store', 'show']);
    Route::post('/generations/{generation}/retry', [GenerationController::class, 'retry']);
    Route::post('/generations/{generation}/feedback', [GenerationController::class, 'feedback']);
    Route::get('/generations/{generation}/download', [GenerationController::class, 'download']);
    Route::get('/credits/balance', [CreditController::class, 'balance']);
    Route::get('/credits/transactions', [CreditController::class, 'transactions']);
});
