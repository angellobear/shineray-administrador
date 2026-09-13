<?php

namespace App\Domain\ErpSync\Jobs;

use App\Domain\ErpSync\Contracts\ErpClientContract;
use App\Domain\ErpSync\DTOs\ErpClientB2b;
use App\Enums\ErpSyncType;
use App\Enums\Integration;
use App\Models\B2bClient;
use App\Models\Customer;
use App\Models\ErpSyncRun;
use App\Models\IntegrationLog;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sincroniza clientes B2B desde el ERP (reemplaza `sync-clients-b2b.ts`, que
 * hacía delete + save sin await ni transacción). Aquí es un upsert por
 * `id_client` dentro de una transacción; además enlaza el `Customer` con el
 * mismo email cuando existe.
 */
class SyncClientsB2bJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $uniqueFor = 600;

    public int $tries = 1;

    public function handle(ErpClientContract $erp): void
    {
        $run = ErpSyncRun::start(ErpSyncType::ClientsB2b);

        try {
            $clients = $erp->fetchClientsB2b();
            $now = now();

            [$created, $updated] = DB::transaction(function () use ($clients, $now): array {
                $created = 0;
                $updated = 0;

                $clients->chunk(500)->each(function ($chunk) use (&$created, &$updated, $now): void {
                    $existing = B2bClient::query()->whereIn('id_client', $chunk->map(fn (ErpClientB2b $c): string => $c->idClient))->get()->keyBy('id_client');
                    $customersByEmail = Customer::query()
                        ->whereIn('email', $chunk->map(fn (ErpClientB2b $c): ?string => $c->email)->filter()->map(fn (string $e): string => mb_strtolower($e)))
                        ->get()
                        ->keyBy(fn (Customer $customer): string => mb_strtolower($customer->email));

                    foreach ($chunk as $erpClient) {
                        $client = $existing->get($erpClient->idClient) ?? new B2bClient(['id_client' => $erpClient->idClient]);
                        $isNew = ! $client->exists;

                        $client->fill([
                            'type_client' => $erpClient->typeClient,
                            'first_name' => $erpClient->firstName,
                            'last_name' => $erpClient->lastName,
                            'email' => $erpClient->email,
                            'phone_number' => $erpClient->phoneNumber,
                            'address' => $erpClient->address,
                            'active' => $erpClient->active,
                            'erp_synced_at' => $now,
                        ]);

                        if ($client->customer_id === null && $erpClient->email !== null) {
                            $customer = $customersByEmail->get(mb_strtolower($erpClient->email));

                            if ($customer instanceof Customer && $customer->b2bClient()->doesntExist()) {
                                $client->customer_id = $customer->id;
                            }
                        }

                        $client->save();
                        $isNew ? $created++ : $updated++;
                    }
                });

                return [$created, $updated];
            });

            $run->finish(['fetched' => $clients->count(), 'created' => $created, 'updated' => $updated]);
        } catch (Throwable $exception) {
            $run->fail($exception);
            IntegrationLog::record(Integration::ShinerayErp, 'sync_clients_b2b_fail', ['message' => $exception->getMessage()], $run);

            throw $exception;
        }
    }
}
