<?php

use Illuminate\Support\Facades\Route;
use App\Domains\Billing\Services\BillingService;
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('landing');
});

Route::get('/dashboard', function () {
    return view('dashboard');
});

Route::get('/create', function () {
    return view('create');
});

Route::get('/generations/{generationId}', function (int $generationId) {
    return view('generation', compact('generationId'));
});

Route::get('/pricing', function () {
    return view('pricing');
});

Route::get('/fake-checkout/{authority}', function (string $authority) {
    abort_unless(config('payment.driver') === 'fake', 404);

    return view('fake-checkout', compact('authority'));
});

Route::post('/fake-checkout/{authority}', function (Request $request, BillingService $billing, string $authority) {
    abort_unless(config('payment.driver') === 'fake', 404);
    $status = $request->string('status')->value();
    abort_unless(in_array($status, ['paid', 'failed'], true), 422);
    $billing->settle($authority, $status);

    return redirect('/pricing?payment='.$status);
});

Route::get('/reset-password/{token}', function (string $token) {
    return view('app', ['resetToken' => $token, 'email' => request('email')]);
})->name('password.reset');
