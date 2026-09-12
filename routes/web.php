<?php

use Illuminate\Support\Facades\Route;

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

Route::get('/reset-password/{token}', function (string $token) {
    return view('app', ['resetToken' => $token, 'email' => request('email')]);
})->name('password.reset');
