<?php

use App\Http\Controllers\Admin\BusinessSettingsController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('home')
        : redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/home', HomeController::class)->name('home');

    /*
    |--------------------------------------------------------------------------
    | Branch side - phone first, five screens, four in the bottom bar
    |--------------------------------------------------------------------------
    */
    Route::middleware('branch')->prefix('b')->name('branch.')->group(function () {
        Route::get('/', fn () => Inertia::render('Branch/Home'))->name('home');
        Route::get('/ask', fn () => Inertia::render('Branch/AskForStock'))->name('ask');
        Route::get('/requests', fn () => Inertia::render('Branch/MyRequests'))->name('requests');
        Route::get('/receive', fn () => Inertia::render('Branch/Receive'))->name('receive');
        Route::get('/more', fn () => Inertia::render('Branch/More'))->name('more');
    });

    /*
    |--------------------------------------------------------------------------
    | Admin side - works on a laptop and on a phone
    |--------------------------------------------------------------------------
    */
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', fn () => Inertia::render('Admin/Dashboard'))->name('dashboard');
        Route::get('/requests', fn () => Inertia::render('Admin/Requests/Inbox'))->name('requests.index');
        Route::get('/dispatch', fn () => Inertia::render('Admin/Dispatch/Index'))->name('dispatch.index');
        Route::get('/stock', fn () => Inertia::render('Admin/Stock/Index'))->name('stock.index');

        Route::get('/settings', fn () => Inertia::render('Admin/Settings/Index'))->name('settings.index');
        Route::get('/settings/business', [BusinessSettingsController::class, 'edit'])->name('settings.business');
        Route::put('/settings/business', [BusinessSettingsController::class, 'update'])->name('settings.business.update');
    });

    // Living reference for the design system. Handy when adding a screen.
    Route::get('/design', fn () => Inertia::render('DesignSystem'))->name('design');
});

require __DIR__.'/auth.php';
