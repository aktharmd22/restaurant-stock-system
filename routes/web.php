<?php

use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BusinessSettingsController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\ItemController;
use App\Http\Controllers\Admin\UserController;
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
    | Admin side - laptop first, but works on a phone
    |--------------------------------------------------------------------------
    */
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', fn () => Inertia::render('Admin/Dashboard'))->name('dashboard');
        Route::get('/requests', fn () => Inertia::render('Admin/Requests/Inbox'))->name('requests.index');
        Route::get('/dispatch', fn () => Inertia::render('Admin/Dispatch/Index'))->name('dispatch.index');
        Route::get('/stock', fn () => Inertia::render('Admin/Stock/Index'))->name('stock.index');

        Route::get('/settings', fn () => Inertia::render('Admin/Settings/Index', [
            // Hide what this person cannot use. The server still enforces it.
            'can' => [
                'settings' => auth()->user()->can('settings.manage'),
                'branches' => auth()->user()->can('branches.manage'),
                'users' => auth()->user()->can('users.manage'),
            ],
        ]))->name('settings.index');

        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('/settings/business', [BusinessSettingsController::class, 'edit'])->name('settings.business');
            Route::put('/settings/business', [BusinessSettingsController::class, 'update'])->name('settings.business.update');

            Route::get('/settings/categories', [CategoryController::class, 'index'])->name('categories.index');
            Route::post('/settings/categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::put('/settings/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::post('/settings/categories/{category}/toggle', [CategoryController::class, 'toggle'])->name('categories.toggle');

            Route::get('/settings/items', [ItemController::class, 'index'])->name('items.index');
            Route::get('/settings/items/new', [ItemController::class, 'create'])->name('items.create');
            Route::post('/settings/items', [ItemController::class, 'store'])->name('items.store');
            Route::get('/settings/items/{item}/edit', [ItemController::class, 'edit'])->name('items.edit');
            Route::post('/settings/items/{item}', [ItemController::class, 'update'])->name('items.update');
            Route::post('/settings/items/{item}/toggle', [ItemController::class, 'toggle'])->name('items.toggle');
        });

        Route::middleware('permission:branches.manage')->group(function () {
            Route::get('/settings/branches', [BranchController::class, 'index'])->name('branches.index');
            Route::get('/settings/branches/new', [BranchController::class, 'create'])->name('branches.create');
            Route::post('/settings/branches', [BranchController::class, 'store'])->name('branches.store');
            Route::get('/settings/branches/{branch}/edit', [BranchController::class, 'edit'])->name('branches.edit');
            Route::put('/settings/branches/{branch}', [BranchController::class, 'update'])->name('branches.update');
            Route::post('/settings/branches/{branch}/toggle', [BranchController::class, 'toggle'])->name('branches.toggle');
        });

        Route::middleware('permission:users.manage')->group(function () {
            Route::get('/settings/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/settings/users/new', [UserController::class, 'create'])->name('users.create');
            Route::post('/settings/users', [UserController::class, 'store'])->name('users.store');
            Route::get('/settings/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/settings/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::post('/settings/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
            Route::post('/settings/users/{user}/new-password', [UserController::class, 'resetPassword'])->name('users.password');
        });
    });

    // Living reference for the design system. Handy when adding a screen.
    Route::get('/design', fn () => Inertia::render('DesignSystem'))->name('design');
});

require __DIR__.'/auth.php';
