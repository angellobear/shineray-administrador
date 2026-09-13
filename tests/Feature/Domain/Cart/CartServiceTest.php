<?php

use App\Domain\Cart\Exceptions\CartException;
use App\Domain\Cart\Services\CartService;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;

beforeEach(function () {
    config()->set('shineray.iva_rate', 0.15);
    $this->service = app(CartService::class);
});

test('adds an item with a price snapshot and recalculates totals with IVA', function () {
    $cart = $this->service->create(email: 'ana@example.com');
    $variant = ProductVariant::factory()->create(['price' => 1000]);

    $this->service->addItem($cart, $variant, 2);
    $variant->update(['price' => 9999]);
    $this->service->addItem($cart, $variant, 1);

    expect($cart->fresh())
        ->subtotal->toBe(3000)
        ->discount_total->toBe(0)
        ->shipping_total->toBe(0)
        ->tax_total->toBe(450)
        ->total->toBe(3450)
        ->itemCount()->toBe(3);
    expect($cart->items()->first())->unit_price->toBe(1000)->quantity->toBe(3);
});

test('adds items without checking local stock', function () {
    $cart = $this->service->create();
    $variant = ProductVariant::factory()->outOfStock()->create(['price' => 500]);

    $this->service->addItem($cart, $variant, 1);

    expect($cart->fresh()->items)->toHaveCount(1);
});

test('rejects products that are not published', function () {
    $cart = $this->service->create();
    $draft = Product::factory()->draft()->create();
    $variant = ProductVariant::factory()->for($draft)->create();

    expect(fn () => $this->service->addItem($cart, $variant, 1))
        ->toThrow(CartException::class, 'El producto no está disponible.');
    expect($cart->fresh()->items)->toHaveCount(0);
});

test('rejects changes to a completed cart', function () {
    $cart = Cart::factory()->completed()->create();
    $variant = ProductVariant::factory()->create();

    expect(fn () => $this->service->addItem($cart, $variant, 1))->toThrow(CartException::class);
});

test('updates and removes items', function () {
    $cart = $this->service->create();
    $variant = ProductVariant::factory()->create(['price' => 250]);
    $this->service->addItem($cart, $variant, 1);
    $item = $cart->items()->firstOrFail();

    $this->service->updateItemQuantity($cart, $item, 4);
    expect($cart->fresh()->subtotal)->toBe(1000);

    $this->service->removeItem($cart, $item);
    expect($cart->fresh())->subtotal->toBe(0)->total->toBe(0);
    $this->assertModelMissing($item);
});

test('applies percentage, fixed and free shipping discounts to the totals', function () {
    $cart = $this->service->create();
    $this->service->addItem($cart, ProductVariant::factory()->create(['price' => 10000]), 1);
    $this->service->setShippingQuote($cart, 430);
    Discount::factory()->create(['code' => 'DIEZ', 'value' => 10]);
    Discount::factory()->fixed(500)->create(['code' => 'FIJO5']);
    Discount::factory()->freeShipping()->create(['code' => 'ENVIO']);

    $this->service->applyDiscount($cart, 'diez');
    expect($cart->fresh())->discount_total->toBe(1000)->shipping_total->toBe(430)->tax_total->toBe(1415)->total->toBe(10845);

    $this->service->applyDiscount($cart, 'FIJO5');
    expect($cart->fresh())->discount_total->toBe(1500);

    $this->service->applyDiscount($cart, 'ENVIO');
    expect($cart->fresh())->shipping_total->toBe(0)->discount_total->toBe(1500)->total->toBe(9775);
    expect($cart->fresh()->metadata['shipping_quote_cents'])->toBe(430);

    $this->service->removeDiscount($cart, Discount::query()->where('code', 'ENVIO')->firstOrFail());
    expect($cart->fresh())->shipping_total->toBe(430);
});

test('rejects unusable discount codes', function () {
    $cart = $this->service->create();
    Discount::factory()->expired()->create(['code' => 'VIEJO']);

    expect(fn () => $this->service->applyDiscount($cart, 'VIEJO'))->toThrow(CartException::class);
    expect(fn () => $this->service->applyDiscount($cart, 'NOEXISTE'))->toThrow(CartException::class);
    expect($cart->fresh()->discounts)->toHaveCount(0);
});

test('stores the shipping address and drops the previous shipping quote', function () {
    $cart = $this->service->create();
    $this->service->setShippingQuote($cart, 700);

    $this->service->setShippingAddress($cart, [
        'first_name' => 'Ana', 'last_name' => 'Pérez', 'phone' => '0999', 'address_1' => 'Calle 1',
        'city' => 'QUITO', 'province' => 'PICHINCHA', 'metadata' => ['dni' => '0999999999', 'dni_type' => 1, 'city_id' => 42],
    ]);

    $cart->refresh();
    expect($cart->shippingAddress)
        ->city->toBe('QUITO')
        ->country_code->toBe('EC')
        ->postal_code->toBe('000000');
    expect($cart->shippingAddress->metadata)->toHaveKey('city_id', 42);
    expect($cart->metadata)->not->toHaveKey('shipping_quote_cents');
    expect($cart->shipping_total)->toBe(0);

    $this->service->setShippingAddress($cart, ['first_name' => 'Ana', 'last_name' => 'Pérez', 'phone' => '0999', 'address_1' => 'Calle 2', 'city' => 'GUAYAQUIL', 'province' => 'GUAYAS']);
    expect($cart->fresh()->addresses)->toHaveCount(1);
});

test('links an authenticated customer to the cart', function () {
    $customer = Customer::factory()->create();
    $cart = $this->service->create();

    $this->service->updateContact($cart, ['customer' => $customer]);

    expect($cart->fresh())->customer_id->toBe($customer->id)->email->toBeNull();
});
