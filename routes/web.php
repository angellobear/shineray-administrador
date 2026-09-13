<?php

use App\Http\Controllers\Admin\AbandonedCartController;
use App\Http\Controllers\Admin\B2bController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DiscountController;
use App\Http\Controllers\Admin\ErpSyncController;
use App\Http\Controllers\Admin\IntegrationLogController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OrderExportController;
use App\Http\Controllers\Admin\ProductController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');

        Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/export', OrderExportController::class)->name('orders.export');
        Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/transition', [OrderController::class, 'transition'])->name('orders.transition');
        Route::post('orders/{order}/resend-confirmation', [OrderController::class, 'resendConfirmation'])->name('orders.resend-confirmation');

        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');

        Route::get('b2b/clients', [B2bController::class, 'clients'])->name('b2b.clients');
        Route::get('b2b/policies', [B2bController::class, 'policies'])->name('b2b.policies');
        Route::get('b2b/transportistas', [B2bController::class, 'transportistas'])->name('b2b.transportistas');

        Route::get('abandoned-carts', [AbandonedCartController::class, 'index'])->name('abandoned-carts.index');
        Route::post('abandoned-carts/{cart}/send', [AbandonedCartController::class, 'send'])->name('abandoned-carts.send');

        Route::get('sync', [ErpSyncController::class, 'index'])->name('sync.index');
        Route::post('sync/run', [ErpSyncController::class, 'run'])->name('sync.run');

        Route::get('logs', [IntegrationLogController::class, 'index'])->name('logs.index');

        Route::resource('discounts', DiscountController::class)->except(['show']);
    });
});

require __DIR__.'/settings.php';
