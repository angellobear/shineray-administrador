<?php

use App\Enums\OrderStatus;

test('allows only the documented transitions', function (OrderStatus $from, OrderStatus $to, bool $allowed) {
    expect($from->canTransitionTo($to))->toBe($allowed);
})->with([
    'pending to paid' => [OrderStatus::Pending, OrderStatus::Paid, true],
    'pending to canceled' => [OrderStatus::Pending, OrderStatus::Canceled, true],
    'pending to fulfilled' => [OrderStatus::Pending, OrderStatus::Fulfilled, false],
    'paid to fulfilled' => [OrderStatus::Paid, OrderStatus::Fulfilled, true],
    'paid to refunded' => [OrderStatus::Paid, OrderStatus::Refunded, true],
    'paid to pending' => [OrderStatus::Paid, OrderStatus::Pending, false],
    'fulfilled to refunded' => [OrderStatus::Fulfilled, OrderStatus::Refunded, true],
    'fulfilled to canceled' => [OrderStatus::Fulfilled, OrderStatus::Canceled, false],
    'canceled to paid' => [OrderStatus::Canceled, OrderStatus::Paid, false],
    'refunded to paid' => [OrderStatus::Refunded, OrderStatus::Paid, false],
]);

test('treats canceled and refunded as terminal states', function () {
    expect(OrderStatus::Canceled->isTerminal())->toBeTrue();
    expect(OrderStatus::Refunded->isTerminal())->toBeTrue();
    expect(OrderStatus::Paid->isTerminal())->toBeFalse();
});
