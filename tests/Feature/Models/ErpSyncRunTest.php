<?php

use App\Enums\ErpSyncRunStatus;
use App\Enums\ErpSyncType;
use App\Models\ErpSyncRun;

test('tracks a successful sync run with its counters', function () {
    $this->freezeSecond();
    $run = ErpSyncRun::start(ErpSyncType::Products);

    $run->finish(['fetched' => 120, 'created' => 3, 'updated' => 117, 'unpublished' => 2]);

    expect($run->fresh())
        ->status->toBe(ErpSyncRunStatus::Succeeded)
        ->finished_at->toEqual(now())
        ->fetched_count->toBe(120)
        ->created_count->toBe(3)
        ->updated_count->toBe(117)
        ->unpublished_count->toBe(2);
});

test('stores the error message when a sync run fails', function () {
    $run = ErpSyncRun::start(ErpSyncType::ClientsB2b);

    $run->fail(new RuntimeException('ERP timeout'));

    expect($run->fresh())
        ->status->toBe(ErpSyncRunStatus::Failed)
        ->error->toBe('ERP timeout')
        ->finished_at->not->toBeNull();
});
