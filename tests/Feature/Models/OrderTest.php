<?php

use App\Enums\OrderStatus;
use App\Models\Order;

test('marking an order as paid records the payment timestamp', function () {
    $this->freezeSecond();
    $order = Order::factory()->create();

    $order->transitionTo(OrderStatus::Paid)->save();

    expect($order->fresh())
        ->status->toBe(OrderStatus::Paid)
        ->paid_at->toEqual(now());
});

test('rejects a transition that is not allowed', function () {
    $order = Order::factory()->paid()->create();

    expect(fn () => $order->transitionTo(OrderStatus::Pending))
        ->toThrow(LogicException::class);

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
});

test('resolves route bindings by order number', function () {
    expect((new Order)->getRouteKeyName())->toBe('order_number');
});

test('detects B2B orders from metadata', function () {
    expect(Order::factory()->b2b()->make()->isB2b())->toBeTrue();
    expect(Order::factory()->make()->isB2b())->toBeFalse();
});
