<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\Product;
use App\Models\ProductVariant;
use Laravel\Sanctum\Sanctum;

test('creates a guest cart identified by a public id', function () {
    $response = $this->postJson(route('store.carts.store'), ['email' => 'ana@example.com']);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'ana@example.com')
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.items', []);

    expect($response->json('data.id'))->toHaveLength(26);
    $this->assertDatabaseHas('carts', ['public_id' => $response->json('data.id'), 'customer_id' => null]);
});

test('creates a cart owned by the authenticated customer', function () {
    $customer = Customer::factory()->create();
    Sanctum::actingAs($customer, guard: 'customer');

    $response = $this->postJson(route('store.carts.store'));

    $response->assertCreated()->assertJsonPath('data.email', $customer->email);
    $this->assertDatabaseHas('carts', ['public_id' => $response->json('data.id'), 'customer_id' => $customer->id]);
});

test('returns 404 for an unknown cart id', function () {
    $this->getJson('/api/store/carts/01ARZ3NDEKTSV4RRFFQ69G5FAV')->assertNotFound();
});

test('adds, updates and removes items through the API', function () {
    $cart = Cart::factory()->create();
    $variant = ProductVariant::factory()->create(['price' => 1500]);

    $this->postJson(route('store.carts.items.store', $cart), ['product_variant_id' => $variant->id, 'quantity' => 2])
        ->assertOk()
        ->assertJsonPath('data.items.0.quantity', 2)
        ->assertJsonPath('data.items.0.sku', $variant->sku)
        ->assertJsonPath('data.subtotal', 3000)
        ->assertJsonPath('data.total', 3450);

    $item = $cart->items()->firstOrFail();

    $this->patchJson(route('store.carts.items.update', [$cart, $item]), ['quantity' => 1])
        ->assertOk()
        ->assertJsonPath('data.subtotal', 1500);

    $this->deleteJson(route('store.carts.items.destroy', [$cart, $item]))
        ->assertOk()
        ->assertJsonPath('data.items', []);
});

test('validates cart item payloads', function () {
    $cart = Cart::factory()->create();

    $this->postJson(route('store.carts.items.store', $cart), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['product_variant_id', 'quantity']);

    $this->postJson(route('store.carts.items.store', $cart), ['product_variant_id' => 999, 'quantity' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['product_variant_id', 'quantity']);
});

test('rejects adding a draft product with a business error', function () {
    $cart = Cart::factory()->create();
    $variant = ProductVariant::factory()->for(Product::factory()->draft())->create();

    $this->postJson(route('store.carts.items.store', $cart), ['product_variant_id' => $variant->id, 'quantity' => 1])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'El producto no está disponible.');
});

test('does not let one cart touch another cart item', function () {
    $cart = Cart::factory()->create();
    $otherItem = CartItem::factory()->create(['quantity' => 1]);

    $this->patchJson(route('store.carts.items.update', [$cart, $otherItem]), ['quantity' => 5])->assertNotFound();
    expect($otherItem->fresh()->quantity)->toBe(1);
});

test('updates contact and shipping address', function () {
    $cart = Cart::factory()->create();

    $this->patchJson(route('store.carts.update', $cart), [
        'email' => 'ana@example.com',
        'shipping_address' => [
            'first_name' => 'Ana', 'last_name' => 'Pérez', 'phone' => '0999999999', 'address_1' => 'Calle 1',
            'city' => 'QUITO', 'province' => 'PICHINCHA', 'metadata' => ['dni' => '0999999999', 'dni_type' => 1, 'city_id' => 42],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('data.email', 'ana@example.com')
        ->assertJsonPath('data.shipping_address.city', 'QUITO')
        ->assertJsonPath('data.shipping_address.metadata.city_id', 42);
});

test('requires the address fields when a shipping address is sent', function () {
    $cart = Cart::factory()->create();

    $this->patchJson(route('store.carts.update', $cart), ['shipping_address' => ['city' => 'QUITO']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['shipping_address.first_name', 'shipping_address.metadata.dni']);
});

test('applies and removes a discount code', function () {
    $cart = Cart::factory()->create();
    $this->postJson(route('store.carts.items.store', $cart), ['product_variant_id' => ProductVariant::factory()->create(['price' => 10000])->id, 'quantity' => 1]);
    Discount::factory()->create(['code' => 'DIEZ', 'value' => 10]);

    $this->postJson(route('store.carts.discounts.store', $cart), ['code' => 'DIEZ'])
        ->assertOk()
        ->assertJsonPath('data.discount_total', 1000)
        ->assertJsonPath('data.discounts.0.code', 'DIEZ');

    $this->postJson(route('store.carts.discounts.store', $cart), ['code' => 'NADA'])
        ->assertUnprocessable();

    $this->deleteJson(route('store.carts.discounts.destroy', [$cart, 'DIEZ']))
        ->assertOk()
        ->assertJsonPath('data.discount_total', 0);
});

test('rejects changes on a completed cart', function () {
    $cart = Cart::factory()->completed()->create();

    $this->postJson(route('store.carts.items.store', $cart), ['product_variant_id' => ProductVariant::factory()->create()->id, 'quantity' => 1])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'El carrito ya fue completado o abandonado.');
});
