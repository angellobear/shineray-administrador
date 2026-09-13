<?php

use App\Domain\Cart\Services\CartService;
use App\Domain\Shipping\Contracts\ShippingProviderContract;
use App\Models\Cart;
use App\Models\ProductVariant;
use Tests\Support\FakeShippingProvider;

test('quotes the shipping for a cart and returns the updated totals', function () {
    $provider = new FakeShippingProvider;
    $provider->quoteCents = 650;
    $this->app->instance(ShippingProviderContract::class, $provider);
    $carts = app(CartService::class);
    $cart = $carts->create(email: 'ana@example.com');
    $carts->addItem($cart, ProductVariant::factory()->create(['price' => 10000]), 1);
    $carts->setShippingAddress($cart, ['first_name' => 'Ana', 'last_name' => 'Pérez', 'phone' => '0999', 'address_1' => 'Calle 1', 'city' => 'QUITO', 'province' => 'PICHINCHA', 'metadata' => ['dni' => '0999999999']]);

    $this->postJson(route('store.carts.shipping-quote', $cart->fresh()))
        ->assertOk()
        ->assertJsonPath('quote.cost', 650)
        ->assertJsonPath('quote.is_fallback', false)
        ->assertJsonPath('cart.shipping_total', 650)
        ->assertJsonPath('cart.total', 12248);
});

test('refuses to quote a cart without shipping address', function () {
    $this->app->instance(ShippingProviderContract::class, new FakeShippingProvider);
    $cart = Cart::factory()->create();

    $this->postJson(route('store.carts.shipping-quote', $cart))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'El carrito no tiene dirección de envío.');
});
