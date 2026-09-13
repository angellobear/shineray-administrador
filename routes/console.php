<?php

use App\Domain\Cart\Jobs\DetectAbandonedCartsJob;
use App\Domain\ErpSync\Jobs\SyncClientsB2bJob;
use App\Domain\ErpSync\Jobs\SyncImagesJob;
use App\Domain\ErpSync\Jobs\SyncPoliciesB2bJob;
use App\Domain\ErpSync\Jobs\SyncProductsJob;
use App\Domain\ErpSync\Jobs\SyncTransportistasB2bJob;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Sincronización con el ERP
|--------------------------------------------------------------------------
|
| `shineray.erp_sync.enabled` reemplaza el MEDUSA_JOBS=true/false. Los crons
| son los mismos que hoy. `withoutOverlapping` + `onOneServer` evitan que
| dos corridas se solapen aunque haya varios nodos.
|
*/

$erpSyncEnabled = fn (): bool => (bool) config('shineray.erp_sync.enabled');

Schedule::job(new SyncProductsJob)
    ->cron((string) config('shineray.erp_sync.products_cron'))
    ->when($erpSyncEnabled)
    ->withoutOverlapping(120)
    ->onOneServer()
    ->name('erp:sync-products');

Schedule::job(new SyncImagesJob)
    ->cron((string) config('shineray.erp_sync.images_cron'))
    ->when($erpSyncEnabled)
    ->withoutOverlapping(240)
    ->onOneServer()
    ->name('erp:sync-images');

foreach ([SyncClientsB2bJob::class, SyncPoliciesB2bJob::class, SyncTransportistasB2bJob::class] as $b2bJob) {
    Schedule::job(new $b2bJob)
        ->cron((string) config('shineray.erp_sync.b2b_cron'))
        ->when($erpSyncEnabled)
        ->withoutOverlapping(30)
        ->onOneServer()
        ->name('erp:'.Str::kebab(class_basename($b2bJob)));
}

Schedule::job(new DetectAbandonedCartsJob)
    ->everyFiveMinutes()
    ->when(fn (): bool => (bool) config('shineray.abandoned_cart.enabled'))
    ->withoutOverlapping(30)
    ->onOneServer()
    ->name('cart:detect-abandoned');

Schedule::command('horizon:snapshot')->everyFiveMinutes();
