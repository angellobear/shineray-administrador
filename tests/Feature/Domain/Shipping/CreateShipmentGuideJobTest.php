<?php

use App\Domain\Shipping\Contracts\ShippingProviderContract;
use App\Domain\Shipping\Jobs\CreateShipmentGuideJob;
use App\Enums\ShipmentStatus;
use App\Models\Address;
use App\Models\IntegrationLog;
use App\Models\Order;
use Tests\Support\FakeShippingProvider;

beforeEach(function () {
    $this->provider = new FakeShippingProvider;
    $this->app->instance(ShippingProviderContract::class, $this->provider);
});

test('B2B orders get an explicit pending shipment without calling Servientrega', function () {
    $order = Order::factory()->paid()->b2b()->create();

    CreateShipmentGuideJob::dispatchSync($order);

    expect($order->shipments()->first())->status->toBe(ShipmentStatus::Pending)->guide_number->toBeNull();
    expect($this->provider->guideRequests)->toBe([]);
});

test('creates the guide for B2C orders', function () {
    $order = Order::factory()->paid()->create();
    $address = Address::factory()->create(['addressable_type' => $order->getMorphClass(), 'addressable_id' => $order->id]);
    $order->update(['shipping_address_id' => $address->id]);

    CreateShipmentGuideJob::dispatchSync($order->fresh());

    expect($order->shipments()->first()->guide_number)->toBe('0123456789');
});

test('logs the failure and releases the job for a retry without breaking the pipeline', function () {
    $this->provider->guideFails = true;
    $order = Order::factory()->paid()->create();
    $address = Address::factory()->create(['addressable_type' => $order->getMorphClass(), 'addressable_id' => $order->id]);
    $order->update(['shipping_address_id' => $address->id]);

    CreateShipmentGuideJob::dispatchSync($order->fresh());

    expect(IntegrationLog::query()->where('event', 'servientrega_fail')->first()->loggable_id)->toBe($order->id);
    expect($order->shipments()->first()->status)->toBe(ShipmentStatus::Failed);
});
