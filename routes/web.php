<?php

use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Admin\BusinessSettingsController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DispatchController;
use App\Http\Controllers\Admin\ItemController;
use App\Http\Controllers\Admin\RequestInboxController;
use App\Http\Controllers\Admin\PurchaseOrderController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\StockController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Branch\HomeController as BranchHomeController;
use App\Http\Controllers\Branch\ReceiveController;
use App\Http\Controllers\Branch\StockRequestController as BranchRequestController;
use App\Http\Controllers\Branch\StockController as BranchStockController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocalPurchaseController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WastageController;
use App\Http\Controllers\SoundSettingsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
 * The app manifest is generated rather than a static file, so an admin who
 * renames the restaurant renames the icon on everyone's home screen too.
 */
Route::get('/manifest.webmanifest', function () {
    return response()->json([
        'name' => setting('business_name'),
        'short_name' => \Illuminate\Support\Str::limit(setting('business_name'), 12, ''),
        'description' => setting('business_tagline'),
        'start_url' => '/home',
        'scope' => '/',
        'display' => 'standalone',
        'orientation' => 'portrait',
        'background_color' => '#F6F7F9',
        'theme_color' => '#1F5EFF',
        'icons' => [
            ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
        ],
    ])->header('Content-Type', 'application/manifest+json');
})->name('manifest');

// Shown by the service worker when the phone has no signal.
Route::get('/offline', fn () => response()->view('offline'))->name('offline');

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('home')
        : redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/home', HomeController::class)->name('home');

    Route::get('/settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Sound is a per-person setting, and both sides of the app share this screen.
    Route::get('/settings/sound', fn () => Inertia::render('Settings/Sound'))->name('settings.sound.edit');
    Route::put('/settings/sound', [SoundSettingsController::class, 'update'])->name('settings.sound');
    Route::post('/notifications/read', [SoundSettingsController::class, 'markRead'])->name('notifications.read');

    // Waste and emergency buying are the same screens for both sides. The
    // branch scope decides what each person can see.
    Route::get('/waste', [WastageController::class, 'index'])->name('waste.index');
    Route::post('/waste', [WastageController::class, 'store'])->name('waste.store');

    Route::get('/local-purchases', [LocalPurchaseController::class, 'index'])->name('local.index');
    Route::post('/local-purchases', [LocalPurchaseController::class, 'store'])->name('local.store');
    Route::post('/local-purchases/{localPurchase}/approve', [LocalPurchaseController::class, 'approve'])->name('local.approve');
    Route::post('/local-purchases/{localPurchase}/reject', [LocalPurchaseController::class, 'reject'])->name('local.reject');

    /*
    |--------------------------------------------------------------------------
    | Branch side - phone first, five screens, four in the bottom bar
    |--------------------------------------------------------------------------
    */
    Route::middleware('branch')->prefix('b')->name('branch.')->group(function () {
        Route::get('/', BranchHomeController::class)->name('home');

        Route::get('/ask', [BranchRequestController::class, 'create'])->name('ask');
        Route::post('/ask', [BranchRequestController::class, 'store'])->name('requests.store');

        Route::get('/requests', [BranchRequestController::class, 'index'])->name('requests.index');
        Route::get('/requests/{stockRequest}', [BranchRequestController::class, 'show'])->name('requests.show');
        Route::post('/requests/{stockRequest}/cancel', [BranchRequestController::class, 'cancel'])->name('requests.cancel');

        Route::get('/receive', [ReceiveController::class, 'index'])->name('receive');
        Route::get('/receive/{stockRequest}', [ReceiveController::class, 'show'])->name('receive.show');
        Route::post('/receive/{stockRequest}', [ReceiveController::class, 'store'])->name('receive.store');

        Route::get('/stock', [BranchStockController::class, 'index'])->name('stock');
        Route::get('/more', fn () => Inertia::render('Branch/More'))->name('more');
    });

    /*
    |--------------------------------------------------------------------------
    | Admin side - laptop first, but works on a phone
    |--------------------------------------------------------------------------
    */
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');

        Route::get('/requests', [RequestInboxController::class, 'index'])->name('requests.index');
        Route::post('/requests/{stockRequest}/approve', [RequestInboxController::class, 'approve'])->name('requests.approve');
        Route::post('/requests/{stockRequest}/approve-all', [RequestInboxController::class, 'approveAll'])->name('requests.approveAll');
        Route::post('/requests/{stockRequest}/cancel', [RequestInboxController::class, 'cancel'])->name('requests.cancel');

        Route::get('/dispatch', [DispatchController::class, 'index'])->name('dispatch.index');
        Route::get('/dispatch/{stockRequest}', [DispatchController::class, 'show'])->name('dispatch.show');
        Route::post('/dispatch/{stockRequest}', [DispatchController::class, 'store'])->name('dispatch.store');

        Route::get('/stock', [StockController::class, 'index'])->name('stock.index');
        Route::post('/stock/count', [StockController::class, 'startCount'])
            ->middleware('permission:stock.count')->name('stock.count.start');
        Route::get('/stock/count/{stockCount}', [StockController::class, 'count'])
            ->middleware('permission:stock.count')->name('stock.count');
        Route::post('/stock/count/{stockCount}/apply', [StockController::class, 'applyCount'])
            ->middleware('permission:stock.adjust')->name('stock.count.apply');

        Route::middleware('permission:purchase.manage')->group(function () {
            Route::get('/purchase', [PurchaseOrderController::class, 'index'])->name('purchase.index');
            Route::get('/purchase/new', [PurchaseOrderController::class, 'create'])->name('purchase.create');
            // Before the {purchaseOrder} route, or "suggestions" is read as an id.
            Route::get('/purchase/suggestions', [PurchaseOrderController::class, 'suggestions'])->name('purchase.suggestions');
            Route::post('/purchase', [PurchaseOrderController::class, 'store'])->name('purchase.store');
            Route::get('/purchase/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase.show');
            Route::post('/purchase/{purchaseOrder}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase.receive');

            Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
            Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
            Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
            Route::post('/suppliers/{supplier}/toggle', [SupplierController::class, 'toggle'])->name('suppliers.toggle');
        });

        Route::middleware('permission:reports.view')->group(function () {
            Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/{key}', [ReportController::class, 'show'])->name('reports.show');
            Route::get('/reports/{key}/export/{format}', [ReportController::class, 'export'])
                ->whereIn('format', ['xlsx', 'pdf'])
                ->name('reports.export');
        });

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
