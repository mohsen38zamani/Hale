<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('app');
});

Route::get('/dashboard', function () {
    return view('dashboard');
});

Route::get('/reset-password/{token}', function (string $token) {
    return view('app', ['resetToken' => $token, 'email' => request('email')]);
})->name('password.reset');
