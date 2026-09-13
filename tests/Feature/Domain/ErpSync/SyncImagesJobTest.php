<?php

use App\Domain\ErpSync\Contracts\ErpClientContract;
use App\Domain\ErpSync\DTOs\ErpProduct;
use App\Domain\ErpSync\Jobs\SyncImagesJob;
use App\Enums\ErpSyncRunStatus;
use App\Models\ErpSyncRun;
use App\Models\IntegrationLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeErpClient;

beforeEach(function () {
    config()->set('services.shineray_erp.base_url', 'http://erp.test');
    config()->set('shineray.catalog.images_disk', 's3');
    Storage::fake('s3');
    Http::preventStrayRequests();
    $this->erp = new FakeErpClient;
    $this->app->instance(ErpClientContract::class, $this->erp);
});

test('uploads each product image to the public bucket and logs the ones that fail', function () {
    $this->erp->products = collect([
        new ErpProduct('A-1', 'A', 'a', 100, 0),
        new ErpProduct('B-2', 'B', 'b', 100, 0),
    ]);
    Http::fake([
        'erp.test/imageApi/img?code=A-1' => Http::response('jpegbytes', 200, ['Content-Type' => 'image/jpeg', 'Content-Length' => '9']),
        'erp.test/imageApi/img?code=B-2' => Http::response('', 404),
    ]);

    SyncImagesJob::dispatchSync();

    Storage::disk('s3')->assertExists('repuestos/A-1.jpg');
    Storage::disk('s3')->assertMissing('repuestos/B-2.jpg');
    expect(IntegrationLog::query()->where('event', 'image_sync_fail')->first()->payload)->toHaveKey('sku', 'B-2');
    expect(ErpSyncRun::query()->first())
        ->status->toBe(ErpSyncRunStatus::Succeeded)
        ->fetched_count->toBe(2)
        ->updated_count->toBe(1)
        ->metadata->toBe(['failed' => 1]);
});

test('rejects a truncated download', function () {
    $this->erp->products = collect([new ErpProduct('A-1', 'A', 'a', 100, 0)]);
    Http::fake([
        'erp.test/imageApi/img?code=A-1' => Http::response('short', 200, ['Content-Length' => '9999']),
    ]);

    SyncImagesJob::dispatchSync();

    Storage::disk('s3')->assertMissing('repuestos/A-1.jpg');
    expect(IntegrationLog::query()->where('event', 'image_sync_fail')->exists())->toBeTrue();
});
