<?php

use App\Domain\ErpSync\Contracts\ErpClientContract;
use App\Domain\ErpSync\DTOs\ErpClientB2b;
use App\Domain\ErpSync\DTOs\ErpPolicyB2b;
use App\Domain\ErpSync\DTOs\ErpTransportistaB2b;
use App\Domain\ErpSync\Jobs\SyncClientsB2bJob;
use App\Domain\ErpSync\Jobs\SyncPoliciesB2bJob;
use App\Domain\ErpSync\Jobs\SyncTransportistasB2bJob;
use App\Enums\ErpSyncRunStatus;
use App\Models\B2bClient;
use App\Models\B2bPolicy;
use App\Models\B2bTransportista;
use App\Models\Customer;
use App\Models\ErpSyncRun;
use Tests\Support\FakeErpClient;

beforeEach(function () {
    $this->erp = new FakeErpClient;
    $this->app->instance(ErpClientContract::class, $this->erp);
});

test('upserts B2B clients by id and links the customer with the same email', function () {
    $customer = Customer::factory()->b2b()->create(['email' => 'ana@example.com']);
    $existing = B2bClient::factory()->create(['id_client' => 'RUC1', 'first_name' => 'Viejo']);
    $this->erp->clientsB2b = collect([
        new ErpClientB2b('RUC1', 'DM', 'Ana', 'Pérez', 'ANA@example.com', '099', ['city' => 'Quito'], true),
        new ErpClientB2b('RUC2', 'DP', 'Luis', 'Gómez', 'luis@example.com', null, [], false),
    ]);

    SyncClientsB2bJob::dispatchSync();

    expect($existing->fresh())->first_name->toBe('Ana')->type_client->toBe('DM')->customer_id->toBe($customer->id);
    expect(B2bClient::query()->where('id_client', 'RUC2')->first())->active->toBeFalse()->customer_id->toBeNull();
    expect(ErpSyncRun::query()->latest('id')->first())->created_count->toBe(1)->updated_count->toBe(1);
});

test('replaces the policies of each client type in the feed and keeps the other types', function () {
    B2bPolicy::factory()->create(['client_type' => 'DM', 'installments' => 3, 'credit_factor' => 1.0]);
    B2bPolicy::factory()->create(['client_type' => 'DM', 'installments' => 12]);
    $untouched = B2bPolicy::factory()->create(['client_type' => 'MY', 'installments' => 6]);
    $this->erp->policiesB2b = collect([
        new ErpPolicyB2b('DM', true, 1.05, 3),
        new ErpPolicyB2b('DM', false, 1.1, 6),
    ]);

    SyncPoliciesB2bJob::dispatchSync();

    expect(B2bPolicy::query()->where('client_type', 'DM')->pluck('installments')->sort()->values()->all())->toBe([3, 6]);
    expect(B2bPolicy::query()->where('client_type', 'DM')->where('installments', 3)->first()->credit_factor)->toBe(1.05);
    $this->assertModelExists($untouched);
    expect(ErpSyncRun::query()->latest('id')->first()->metadata)->toBe(['deleted' => 1]);
});

test('upserts transportistas by RUC and removes the ones missing from the feed atomically', function () {
    B2bTransportista::factory()->create(['ruc' => '0001', 'business_name' => 'Vieja']);
    $gone = B2bTransportista::factory()->create(['ruc' => '0002']);
    $this->erp->transportistas = collect([
        new ErpTransportistaB2b('0001', 'Trans Uno'),
        new ErpTransportistaB2b('0003', 'Trans Tres'),
    ]);

    SyncTransportistasB2bJob::dispatchSync();

    expect(B2bTransportista::query()->pluck('business_name', 'ruc')->all())->toBe(['0001' => 'Trans Uno', '0003' => 'Trans Tres']);
    $this->assertModelMissing($gone);
});

test('an empty transportistas feed never empties the table', function () {
    B2bTransportista::factory()->count(2)->create();

    SyncTransportistasB2bJob::dispatchSync();

    expect(B2bTransportista::query()->count())->toBe(2);
    expect(ErpSyncRun::query()->latest('id')->first()->status)->toBe(ErpSyncRunStatus::Skipped);
});

test('records a failed run and keeps the data when the ERP fails', function () {
    B2bTransportista::factory()->count(2)->create();
    $this->erp->failWith = new RuntimeException('ERP down');

    expect(fn () => SyncTransportistasB2bJob::dispatchSync())->toThrow(RuntimeException::class);

    expect(B2bTransportista::query()->count())->toBe(2);
    expect(ErpSyncRun::query()->latest('id')->first()->status)->toBe(ErpSyncRunStatus::Failed);
});
