<?php

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;

test('generates a non-sequential public id for the storefront', function () {
    $first = Cart::factory()->create();
    $second = Cart::factory()->create();

    expect($first->public_id)->toHaveLength(26);
    expect($second->public_id)->not->toBe($first->public_id);
    expect($first->getRouteKey())->toBe($first->public_id);
});

test('keeps the cart when its customer is deleted', function () {
    $customer = Customer::factory()->create();
    $cart = Cart::factory()->forCustomer($customer)->create();

    $customer->forceDelete();

    expect($cart->fresh()->customer_id)->toBeNull();
});

test('computes line totals from the price snapshot', function () {
    $item = CartItem::factory()->make(['quantity' => 3, 'unit_price' => 1250]);

    expect($item->lineTotal())->toBe(3750);
});

test('lists only active carts through the scope', function () {
    Cart::factory()->create();
    Cart::factory()->completed()->create();

    expect(Cart::active()->count())->toBe(1);
});
