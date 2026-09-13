<?php

use App\Domain\ErpSync\Contracts\ErpClientContract;
use App\Domain\ErpSync\DTOs\ErpProduct;
use App\Domain\ErpSync\Jobs\SyncProductsJob;
use App\Enums\ErpSyncRunStatus;
use App\Enums\ErpSyncType;
use App\Enums\ProductStatus;
use App\Models\ErpSyncRun;
use App\Models\IntegrationLog;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\QueryException;
use Tests\Support\FakeErpClient;

beforeEach(function () {
    config()->set('shineray.erp_sync.min_products_for_unpublish', 2);
    $this->erp = new FakeErpClient;
    $this->app->instance(ErpClientContract::class, $this->erp);
});

function erpProduct(string $sku, array $overrides = []): ErpProduct
{
    return new ErpProduct(...array_merge([
        'sku' => $sku,
        'title' => "Producto {$sku}",
        'handle' => strtolower("producto-{$sku}"),
        'priceCents' => 1000,
        'stockQuantity' => 5,
        'thumbnail' => "https://cdn.test/{$sku}.jpg",
        'metadata' => ['COD_PRODUCTO' => $sku, 'NOMBRE_CATEGORIA' => 'SISTEMA DE FRENO', 'NIVEL_1' => 'REPUESTOS'],
    ], $overrides));
}

test('creates published products with a backorderable variant from the feed', function () {
    $this->erp->products = collect([erpProduct('A-1', ['weightKg' => 2.5]), erpProduct('B-2')]);

    SyncProductsJob::dispatchSync();

    $product = Product::query()->where('handle', 'producto-a-1')->firstOrFail();
    expect($product)
        ->status->toBe(ProductStatus::Published)
        ->thumbnail->toBe('https://cdn.test/A-1.jpg')
        ->erp_synced_at->not->toBeNull();
    expect($product->metadata)->toHaveKey('NOMBRE_CATEGORIA', 'SISTEMA DE FRENO');
    expect($product->variant)
        ->sku->toBe('A-1')
        ->price->toBe(1000)
        ->inventory_quantity->toBe(5)
        ->allow_backorder->toBeTrue()
        ->weight_kg->toBe(2.5);

    $run = ErpSyncRun::query()->where('type', ErpSyncType::Products)->firstOrFail();
    expect($run)
        ->status->toBe(ErpSyncRunStatus::Succeeded)
        ->fetched_count->toBe(2)
        ->created_count->toBe(2)
        ->updated_count->toBe(0)
        ->unpublished_count->toBe(0);
});

test('updates an existing product by SKU and merges metadata without losing admin flags', function () {
    $product = Product::factory()->create(['handle' => 'old-handle', 'metadata' => ['COD_PRODUCTO' => 'A-1', 'is_b2b' => true, 'OLD_PRICE' => 1500]]);
    ProductVariant::factory()->for($product)->create(['sku' => 'A-1', 'price' => 500, 'inventory_quantity' => 0]);
    $this->erp->products = collect([erpProduct('A-1', ['title' => 'Nuevo título', 'priceCents' => 1200, 'stockQuantity' => 7]), erpProduct('B-2')]);

    SyncProductsJob::dispatchSync();

    $product->refresh();
    expect($product)
        ->title->toBe('Nuevo título')
        ->handle->toBe('old-handle');
    expect($product->metadata)
        ->toHaveKey('is_b2b', true)
        ->toHaveKey('OLD_PRICE', 1500)
        ->toHaveKey('NOMBRE_CATEGORIA', 'SISTEMA DE FRENO');
    expect($product->variant)
        ->price->toBe(1200)
        ->inventory_quantity->toBe(7);
    expect(ErpSyncRun::query()->first())->created_count->toBe(1)->updated_count->toBe(1);
});

test('unpublishes products missing from the feed when the feed is large enough', function () {
    $missing = Product::factory()->withVariant()->create(['title' => 'Descontinuado']);
    $this->erp->products = collect([erpProduct('A-1'), erpProduct('B-2')]);

    SyncProductsJob::dispatchSync();

    $missing->refresh();
    expect($missing->status)->toBe(ProductStatus::Draft);
    expect($missing->metadata)->toHaveKey(SyncProductsJob::UNPUBLISHED_BY_SYNC_KEY, true);
    expect(ErpSyncRun::query()->first())->unpublished_count->toBe(1);
});

test('does not unpublish anything when the feed is suspiciously small', function () {
    $existing = Product::factory()->withVariant()->create();
    $this->erp->products = collect([erpProduct('A-1')]);

    SyncProductsJob::dispatchSync();

    expect($existing->fresh()->status)->toBe(ProductStatus::Published);
    expect(ErpSyncRun::query()->first())
        ->status->toBe(ErpSyncRunStatus::Skipped)
        ->unpublished_count->toBe(0);
});

test('republishes a product the sync had unpublished but leaves manual drafts alone', function () {
    $bySync = Product::factory()->draft()->create(['metadata' => ['COD_PRODUCTO' => 'A-1', SyncProductsJob::UNPUBLISHED_BY_SYNC_KEY => true]]);
    ProductVariant::factory()->for($bySync)->create(['sku' => 'A-1']);
    $manual = Product::factory()->draft()->create(['metadata' => ['COD_PRODUCTO' => 'B-2']]);
    ProductVariant::factory()->for($manual)->create(['sku' => 'B-2']);
    $this->erp->products = collect([erpProduct('A-1'), erpProduct('B-2')]);

    SyncProductsJob::dispatchSync();

    expect($bySync->fresh())->status->toBe(ProductStatus::Published);
    expect($bySync->fresh()->metadata)->not->toHaveKey(SyncProductsJob::UNPUBLISHED_BY_SYNC_KEY);
    expect($manual->fresh()->status)->toBe(ProductStatus::Draft);
});

test('rolls back the whole sync and records the failure when a write fails midway', function () {
    $this->erp->products = collect([erpProduct('A-1'), erpProduct('B-2', ['handle' => 'producto-a-1'])]);

    expect(fn () => SyncProductsJob::dispatchSync())->toThrow(QueryException::class);

    expect(Product::query()->count())->toBe(0);
    expect(ErpSyncRun::query()->first())->status->toBe(ErpSyncRunStatus::Failed)->error->not->toBeNull();
    expect(IntegrationLog::query()->where('event', 'sync_products_fail')->exists())->toBeTrue();
});

test('records a failed run when the ERP is unreachable', function () {
    $this->erp->failWith = new RuntimeException('ERP down');

    expect(fn () => SyncProductsJob::dispatchSync())->toThrow(RuntimeException::class, 'ERP down');

    expect(ErpSyncRun::query()->first())->status->toBe(ErpSyncRunStatus::Failed)->error->toBe('ERP down');
});
