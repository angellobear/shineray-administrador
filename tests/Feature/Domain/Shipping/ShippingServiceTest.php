<?php

use App\Domain\Cart\Services\CartService;
use App\Domain\Shipping\Contracts\ShippingProviderContract;
use App\Domain\Shipping\Exceptions\ShippingProviderException;
use App\Domain\Shipping\Services\ShippingService;
use App\Enums\ShipmentStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Models\Shipment;
use Tests\Support\FakeShippingProvider;

beforeEach(function () {
    $this->provider = new FakeShippingProvider;
    $this->app->instance(ShippingProviderContract::class, $this->provider);
    $this->service = app(ShippingService::class);
});

function shippableCart(): Cart
{
    $carts = app(CartService::class);
    $cart = $carts->create(email: 'ana@example.com');
    $carts->addItem($cart, ProductVariant::factory()->create(['price' => 10000, 'weight_kg' => 1.5]), 2);
    $carts->addItem($cart, ProductVariant::factory()->create(['price' => 500, 'weight_kg' => null]), 1);
    $carts->setShippingAddress($cart, ['first_name' => 'Ana', 'last_name' => 'Pérez', 'phone' => '0999', 'address_1' => 'Calle 1', 'city' => 'QUITO', 'province' => 'PICHINCHA', 'metadata' => ['dni' => '0999999999', 'city_id' => 42]]);

    return $cart->fresh();
}

test('quotes the cart with its weight and declared value and applies the cost to the totals', function () {
    $cart = shippableCart();

    $quote = $this->service->quoteForCart($cart);

    expect($quote->costCents)->toBe(430);
    expect($this->provider->quoteRequests[0])
        ->totalWeightKg->toBe(4.0)
        ->destinationCity->toBe('QUITO')
        ->destinationCityId->toBe('42')
        ->declaredValueCents->toBe(23575);
    expect($cart->fresh())->shipping_total->toBe(430)->total->toBe(24070);
});

test('creates the guide for a paid order, stores the shipment and the guide id on the address', function () {
    $order = Order::factory()->paid()->create(['total' => 11995, 'metadata' => ['dni' => '0999999999', 'city_id' => 42]]);
    $address = Address::factory()->create(['addressable_type' => $order->getMorphClass(), 'addressable_id' => $order->id, 'metadata' => ['dni' => '0999999999', 'city_id' => 42]]);
    $order->update(['shipping_address_id' => $address->id]);
    OrderItem::factory()->for($order)->for(ProductVariant::factory()->create(['weight_kg' => 3]), 'variant')->create(['quantity' => 2]);

    $shipment = $this->service->createGuideForOrder($order->fresh());

    expect($shipment)
        ->guide_number->toBe('0123456789')
        ->status->toBe(ShipmentStatus::Created)
        ->weight_kg->toBe(6.0);
    expect($this->provider->guideRequests[0])->destinationCityId->toBe('42')->declaredValueCents->toBe(11995)->recipientDni->toBe('0999999999');
    expect($address->fresh()->metadata)->toHaveKey('servientrega_guide_id', '0123456789');
});

test('does not create a second guide for an order that already has one', function () {
    $order = Order::factory()->paid()->create();
    Shipment::factory()->for($order)->created()->create(['guide_number' => 'EXISTING']);

    $shipment = $this->service->createGuideForOrder($order);

    expect($shipment->guide_number)->toBe('EXISTING');
    expect($this->provider->guideRequests)->toBe([]);
});

test('marks the shipment failed and rethrows when the provider fails', function () {
    $this->provider->guideFails = true;
    $order = Order::factory()->paid()->create();
    $address = Address::factory()->create(['addressable_type' => $order->getMorphClass(), 'addressable_id' => $order->id]);
    $order->update(['shipping_address_id' => $address->id]);

    expect(fn () => $this->service->createGuideForOrder($order->fresh()))->toThrow(ShippingProviderException::class);

    expect(Shipment::query()->where('order_id', $order->id)->first())->status->toBe(ShipmentStatus::Failed)->guide_number->toBeNull();
});
