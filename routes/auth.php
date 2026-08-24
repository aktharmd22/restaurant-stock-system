<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordOtpController;
use Illuminate\Support\Facades\Route;

// There is no sign-up. Accounts are created by the admin.
Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordOtpController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordOtpController::class, 'send'])
        ->middleware('throttle:5,1')
        ->name('password.send');

    Route::get('enter-code', [PasswordOtpController::class, 'verify'])->name('password.code');
    Route::post('enter-code', [PasswordOtpController::class, 'reset'])
        ->middleware('throttle:10,1')
        ->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::put('password', [PasswordController::class, 'update'])->name('password.update');
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
