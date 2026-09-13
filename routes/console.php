<?php

use App\Domain\ErpSync\Jobs\SyncImagesJob;
use App\Domain\ErpSync\Jobs\SyncProductsJob;
use Illuminate\Support\Facades\Schedule;

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

Schedule::command('horizon:snapshot')->everyFiveMinutes();
