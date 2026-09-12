<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/reset-password/{token}', function (string $token) {
    return view('welcome', ['resetToken' => $token, 'email' => request('email')]);
})->name('password.reset');
