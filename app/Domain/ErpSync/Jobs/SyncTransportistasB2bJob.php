<?php

namespace App\Domain\ErpSync\Jobs;

use App\Domain\ErpSync\Contracts\ErpClientContract;
use App\Domain\ErpSync\DTOs\ErpTransportistaB2b;
use App\Enums\ErpSyncRunStatus;
use App\Enums\ErpSyncType;
use App\Enums\Integration;
use App\Models\B2bTransportista;
use App\Models\ErpSyncRun;
use App\Models\IntegrationLog;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sincroniza transportistas B2B (reemplaza `sync-transportistas-b2b.ts`, que
 * hacía TRUNCATE + reinserción sin transacción: si fallaba a mitad, la tabla
 * quedaba vacía). Aquí: upsert por RUC + borrado de los que ya no vienen, en
 * una transacción, y nunca se vacía la tabla si el feed viene vacío.
 */
class SyncTransportistasB2bJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $uniqueFor = 300;

    public int $tries = 1;

    public function handle(ErpClientContract $erp): void
    {
        $run = ErpSyncRun::start(ErpSyncType::TransportistasB2b);

        try {
            $transportistas = $erp->fetchTransportistas();

            if ($transportistas->isEmpty()) {
                $run->finish(['fetched' => 0], ErpSyncRunStatus::Skipped, ['reason' => 'feed vacío: no se borra nada']);

                return;
            }

            $now = now();

            [$created, $updated, $deleted] = DB::transaction(function () use ($transportistas, $now): array {
                $created = 0;
                $updated = 0;

                foreach ($transportistas as $transportista) {
                    /** @var ErpTransportistaB2b $transportista */
                    $model = B2bTransportista::query()->firstOrNew(['ruc' => $transportista->ruc]);
                    $isNew = ! $model->exists;
                    $model->fill(['business_name' => $transportista->businessName, 'erp_synced_at' => $now])->save();
                    $isNew ? $created++ : $updated++;
                }

                $deleted = B2bTransportista::query()->whereNotIn('ruc', $transportistas->map(fn (ErpTransportistaB2b $t): string => $t->ruc))->delete();

                return [$created, $updated, $deleted];
            });

            $run->finish(['fetched' => $transportistas->count(), 'created' => $created, 'updated' => $updated], metadata: ['deleted' => $deleted]);
        } catch (Throwable $exception) {
            $run->fail($exception);
            IntegrationLog::record(Integration::ShinerayErp, 'sync_transportistas_b2b_fail', ['message' => $exception->getMessage()], $run);

            throw $exception;
        }
    }
}
