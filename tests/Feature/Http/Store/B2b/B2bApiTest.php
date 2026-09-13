<?php

use App\Domain\ErpSync\Contracts\ErpClientContract;
use App\Models\B2bClient;
use App\Models\B2bPolicy;
use App\Models\B2bTransportista;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FakeErpClient;

function b2bCustomer(): Customer
{
    $customer = Customer::factory()->b2b()->create();
    B2bClient::factory()->forCustomer($customer)->create(['id_client' => 'RUC1', 'type_client' => 'DI', 'address' => ['city' => 'Quito']]);

    return $customer;
}

test('verifies a B2B client through the API', function () {
    Notification::fake();
    B2bClient::factory()->create(['id_client' => 'RUC9']);

    $this->postJson(route('store.b2b.verify'), ['email' => 'ana@example.com', 'name' => 'Ana', 'last_name' => 'Pérez', 'identification' => 'RUC9'])
        ->assertOk()
        ->assertJsonPath('customer.is_b2b', true)
        ->assertJsonPath('customer.has_account', false)
        ->assertJsonPath('customer.b2b.id_client', 'RUC9');

    $this->postJson(route('store.b2b.verify'), ['email' => 'x@example.com', 'name' => 'X', 'last_name' => 'Y', 'identification' => 'NOPE'])
        ->assertNotFound();
});

test('B2B endpoints require an authenticated B2B customer', function () {
    $this->getJson(route('store.b2b.installments'))->assertUnauthorized();

    Sanctum::actingAs(Customer::factory()->create(), guard: 'customer');
    $this->getJson(route('store.b2b.installments'))->assertForbidden();
});

test('returns the address, the credit policies of the client type with the DI alias, and the carriers', function () {
    B2bPolicy::factory()->create(['client_type' => 'DM', 'installments' => 3, 'credit_factor' => 1.05]);
    B2bPolicy::factory()->create(['client_type' => 'DM', 'installments' => 6, 'is_active' => false]);
    B2bTransportista::factory()->create(['ruc' => '0001', 'business_name' => 'Trans Uno']);
    Sanctum::actingAs(b2bCustomer(), guard: 'customer');

    $this->getJson(route('store.b2b.address'))->assertOk()->assertJsonPath('address.city', 'Quito');
    $this->getJson(route('store.b2b.installments'))
        ->assertOk()
        ->assertJsonPath('policy_type', 'DM')
        ->assertJsonCount(1, 'policies')
        ->assertJsonPath('policies.0.installments', 3);
    $this->getJson(route('store.b2b.transportistas'))->assertOk()->assertJsonPath('transportistas.0.business_name', 'Trans Uno');
});

test('fetches the credit debt and recommended products from the ERP for the client RUC', function () {
    $erp = new FakeErpClient;
    $erp->debts['RUC1'] = ['total' => 120.5];
    $erp->recommended['RUC1'] = ['MOT-1'];
    $this->app->instance(ErpClientContract::class, $erp);
    ProductVariant::factory()->for(Product::factory(['title' => 'Recomendado']))->create(['sku' => 'MOT-1']);
    ProductVariant::factory()->create(['sku' => 'OTRO']);
    Sanctum::actingAs(b2bCustomer(), guard: 'customer');

    $this->getJson(route('store.b2b.debt'))->assertOk()->assertJsonPath('debt.total', 120.5);
    $this->getJson(route('store.b2b.recommended-products'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Recomendado');
});

test('answers 503 when the ERP debt lookup fails', function () {
    $erp = new FakeErpClient;
    $erp->failWith = new RuntimeException('down');
    $this->app->instance(ErpClientContract::class, $erp);
    Sanctum::actingAs(b2bCustomer(), guard: 'customer');

    $this->getJson(route('store.b2b.debt'))->assertServiceUnavailable();
});
