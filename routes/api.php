<?php

use App\Http\Controllers\Store\ProductController;
use App\Http\Controllers\Store\SearchController;
use App\Http\Controllers\Store\StockController;
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
});
