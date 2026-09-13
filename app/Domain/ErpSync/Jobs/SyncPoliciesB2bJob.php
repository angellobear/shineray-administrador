<?php

namespace App\Domain\ErpSync\Jobs;

use App\Domain\ErpSync\Contracts\ErpClientContract;
use App\Domain\ErpSync\DTOs\ErpPolicyB2b;
use App\Enums\ErpSyncType;
use App\Enums\Integration;
use App\Models\B2bPolicy;
use App\Models\ErpSyncRun;
use App\Models\IntegrationLog;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sincroniza políticas de crédito por tipo de cliente (reemplaza
 * `sync-policies-b2b.ts`). Para cada tipo que viene en el feed se dejan
 * exactamente las cuotas del feed (upsert + borrado de las sobrantes), todo en
 * una transacción. Tipos que no vienen en el feed se conservan (como hoy).
 */
class SyncPoliciesB2bJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $uniqueFor = 300;

    public int $tries = 1;

    public function handle(ErpClientContract $erp): void
    {
        $run = ErpSyncRun::start(ErpSyncType::PoliciesB2b);

        try {
            $policies = $erp->fetchPoliciesB2b();
            $now = now();

            [$created, $updated, $deleted] = DB::transaction(function () use ($policies, $now): array {
                $created = 0;
                $updated = 0;
                $deleted = 0;

                foreach ($policies->groupBy(fn (ErpPolicyB2b $policy): string => $policy->clientType) as $clientType => $typePolicies) {
                    $installments = [];

                    foreach ($typePolicies as $policy) {
                        $installments[] = $policy->installments;

                        $model = B2bPolicy::query()->firstOrNew(['client_type' => $clientType, 'installments' => $policy->installments]);
                        $isNew = ! $model->exists;
                        $model->fill(['is_active' => $policy->isActive, 'credit_factor' => $policy->creditFactor, 'erp_synced_at' => $now])->save();
                        $isNew ? $created++ : $updated++;
                    }

                    $deleted += B2bPolicy::query()->where('client_type', $clientType)->whereNotIn('installments', $installments)->delete();
                }

                return [$created, $updated, $deleted];
            });

            $run->finish(['fetched' => $policies->count(), 'created' => $created, 'updated' => $updated], metadata: ['deleted' => $deleted]);
        } catch (Throwable $exception) {
            $run->fail($exception);
            IntegrationLog::record(Integration::ShinerayErp, 'sync_policies_b2b_fail', ['message' => $exception->getMessage()], $run);

            throw $exception;
        }
    }
}
