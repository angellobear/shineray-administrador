<?php

use App\Models\Address;
use App\Models\B2bClient;
use App\Models\B2bPolicy;
use App\Models\B2bTransportista;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\ErpSyncRun;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shipment;
use Illuminate\Database\QueryException;

test('every model can be persisted through its factory against the schema', function (string $model) {
    $record = $model::factory()->create();

    $this->assertModelExists($record);
})->with([
    Product::class,
    ProductVariant::class,
    Customer::class,
    Address::class,
    Cart::class,
    CartItem::class,
    Order::class,
    OrderItem::class,
    Discount::class,
    Payment::class,
    Shipment::class,
    IntegrationLog::class,
    B2bClient::class,
    B2bPolicy::class,
    B2bTransportista::class,
    ErpSyncRun::class,
]);

test('a B2B client links to its customer and resolves the credit policies of its type', function () {
    $client = B2bClient::factory()->forCustomer()->create(['type_client' => 'DI']);
    B2bPolicy::factory()->create(['client_type' => 'DM', 'installments' => 3]);
    B2bPolicy::factory()->create(['client_type' => 'DM', 'installments' => 6, 'is_active' => false]);
    B2bPolicy::factory()->create(['client_type' => 'MY', 'installments' => 3]);

    expect($client->fresh())
        ->customer->toBeInstanceOf(Customer::class)
        ->policyType()->toBe('DM')
        ->activePolicies()->toHaveCount(1);
    expect($client->customer->b2bClient->is($client))->toBeTrue();
});

test('rejects two policies with the same installments for one client type', function () {
    B2bPolicy::factory()->create(['client_type' => 'DM', 'installments' => 3]);

    expect(fn () => B2bPolicy::factory()->create(['client_type' => 'DM', 'installments' => 3]))
        ->toThrow(QueryException::class);
});

test('rejects two payments with the same gateway reference', function () {
    $payment = Payment::factory()->create(['gateway_reference' => 'chk_123']);

    expect(fn () => Payment::factory()->create([
        'gateway' => $payment->gateway,
        'gateway_reference' => 'chk_123',
    ]))->toThrow(QueryException::class);
});
