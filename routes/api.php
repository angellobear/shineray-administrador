<?php

use App\Http\Controllers\Store\Auth\AuthController;
use App\Http\Controllers\Store\Auth\PasswordResetController;
use App\Http\Controllers\Store\B2b\B2bAccountController;
use App\Http\Controllers\Store\B2b\ClientVerificationController;
use App\Http\Controllers\Store\CartController;
use App\Http\Controllers\Store\CartDiscountController;
use App\Http\Controllers\Store\CartItemController;
use App\Http\Controllers\Store\CheckoutController;
use App\Http\Controllers\Store\OrderController;
use App\Http\Controllers\Store\ProductController;
use App\Http\Controllers\Store\SearchController;
use App\Http\Controllers\Store\ShippingQuoteController;
use App\Http\Controllers\Store\StockController;
use App\Http\Controllers\Webhooks\DeunaWebhookController;
use App\Http\Middleware\EnsureCustomerIsB2b;
use App\Http\Middleware\VerifyDeunaWebhook;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API del storefront (Next.js)
|--------------------------------------------------------------------------
|
| Prefijo `/api/store`. Catálogo y búsqueda son públicos; todo lo que toque
| identidad del cliente va detrás de `auth:sanctum` (fases siguientes).
|
*/

Route::prefix('store')->name('store.')->group(function (): void {
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/{product:handle}', [ProductController::class, 'show'])->name('products.show');
    Route::get('search', SearchController::class)->name('search');
    Route::post('stock', StockController::class)->middleware('throttle:60,1')->name('stock');

    Route::prefix('carts')->name('carts.')->group(function (): void {
        Route::post('/', [CartController::class, 'store'])->name('store');
        Route::get('{cart}', [CartController::class, 'show'])->name('show');
        Route::patch('{cart}', [CartController::class, 'update'])->name('update');
        Route::post('{cart}/items', [CartItemController::class, 'store'])->name('items.store');
        Route::patch('{cart}/items/{item}', [CartItemController::class, 'update'])->scopeBindings()->name('items.update');
        Route::delete('{cart}/items/{item}', [CartItemController::class, 'destroy'])->scopeBindings()->name('items.destroy');
        Route::post('{cart}/discounts', [CartDiscountController::class, 'store'])->name('discounts.store');
        Route::delete('{cart}/discounts/{discount:code}', [CartDiscountController::class, 'destroy'])->name('discounts.destroy');
        Route::post('{cart}/shipping-quote', ShippingQuoteController::class)->middleware('throttle:30,1')->name('shipping-quote');
        Route::post('{cart}/checkout', [CheckoutController::class, 'start'])->middleware('throttle:20,1')->name('checkout.start');
    });

    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:customer-auth')->name('register');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:customer-auth')->name('login');
        Route::post('forgot-password', [PasswordResetController::class, 'sendResetLink'])->middleware('throttle:customer-auth')->name('forgot-password');
        Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:customer-auth')->name('reset-password');
        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        });
    });

    Route::prefix('b2b')->name('b2b.')->group(function (): void {
        Route::post('verify', ClientVerificationController::class)->middleware('throttle:customer-auth')->name('verify');
        Route::middleware(['auth:sanctum', EnsureCustomerIsB2b::class])->group(function (): void {
            Route::get('address', [B2bAccountController::class, 'address'])->name('address');
            Route::get('installments', [B2bAccountController::class, 'installments'])->name('installments');
            Route::get('transportistas', [B2bAccountController::class, 'transportistas'])->name('transportistas');
            Route::get('debt', [B2bAccountController::class, 'debt'])->name('debt');
            Route::get('recommended-products', [B2bAccountController::class, 'recommendedProducts'])->name('recommended-products');
        });
    });

    Route::prefix('orders')->name('orders.')->group(function (): void {
        Route::get('{order}', [OrderController::class, 'show'])->middleware('throttle:store-lookup')->name('show');
        Route::post('{order}/complete', [CheckoutController::class, 'complete'])->middleware('throttle:20,1')->name('complete');
    });
});

Route::post('webhooks/deuna', DeunaWebhookController::class)
    ->middleware([VerifyDeunaWebhook::class, 'throttle:webhooks'])
    ->name('webhooks.deuna');
