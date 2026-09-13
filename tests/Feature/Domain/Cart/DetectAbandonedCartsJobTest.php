<?php

use App\Domain\Cart\Jobs\DetectAbandonedCartsJob;
use App\Mail\AbandonedCartMail;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    config()->set('shineray.abandoned_cart.intervals_minutes', [60, 1440]);
    config()->set('shineray.abandoned_cart.max_overdue_minutes', 120);
    config()->set('shineray.abandoned_cart.days_to_track', 30);
    $this->freezeSecond();
});

function abandonedCart(int $createdMinutesAgo, array $attributes = []): Cart
{
    $cart = Cart::factory()->create(array_merge([
        'email' => 'ana@example.com',
        'created_at' => now()->subMinutes($createdMinutesAgo),
        'updated_at' => now()->subMinutes($createdMinutesAgo),
        'last_activity_at' => now()->subMinutes($createdMinutesAgo),
    ], $attributes));
    CartItem::factory()->for($cart)->create();

    return $cart;
}

test('does nothing when no intervals are configured', function () {
    config()->set('shineray.abandoned_cart.intervals_minutes', []);
    abandonedCart(90);

    DetectAbandonedCartsJob::dispatchSync();

    Mail::assertNothingSent();
});

test('sends the first email once the first interval has passed and records the attempt', function () {
    $cart = abandonedCart(70);

    DetectAbandonedCartsJob::dispatchSync();

    Mail::assertSent(AbandonedCartMail::class, fn (AbandonedCartMail $mail) => $mail->hasTo('ana@example.com') && $mail->cart->is($cart));
    expect($cart->fresh())
        ->abandoned_count->toBe(1)
        ->abandoned_last_interval->toBe(60)
        ->abandoned_lastdate->toEqual(now())
        ->abandoned_completed_at->toBeNull();
});

test('waits until the interval is due', function () {
    abandonedCart(30);

    DetectAbandonedCartsJob::dispatchSync();

    Mail::assertNothingSent();
});

test('marks a cart completed instead of emailing when it is too overdue', function () {
    $cart = abandonedCart(60 + 121);

    DetectAbandonedCartsJob::dispatchSync();

    Mail::assertNothingSent();
    expect($cart->fresh()->abandoned_completed_at)->not->toBeNull();
});

test('sends the next interval and closes the sequence on the last one', function () {
    $cart = abandonedCart(1500, ['abandoned_count' => 1, 'abandoned_last_interval' => 60, 'abandoned_lastdate' => now()->subMinutes(1441)]);

    DetectAbandonedCartsJob::dispatchSync();

    Mail::assertSent(AbandonedCartMail::class, 1);
    expect($cart->fresh())
        ->abandoned_count->toBe(2)
        ->abandoned_last_interval->toBe(1440)
        ->abandoned_completed_at->toEqual(now());
});

test('does not resend while the previous interval has not elapsed since the last email', function () {
    abandonedCart(1500, ['abandoned_last_interval' => 60, 'abandoned_lastdate' => now()->subMinutes(30)]);

    DetectAbandonedCartsJob::dispatchSync();

    Mail::assertNothingSent();
});

test('ignores carts already closed, bot carts, empty carts and carts without email', function () {
    abandonedCart(70, ['abandoned_completed_at' => now()->subDay()]);
    abandonedCart(70, ['email' => 'test@storebotmail.com']);
    abandonedCart(70, ['email' => null]);
    Cart::factory()->create(['email' => 'vacio@example.com', 'created_at' => now()->subMinutes(70)]);
    abandonedCart(70, ['status' => 'completed']);

    DetectAbandonedCartsJob::dispatchSync();

    Mail::assertNothingSent();
});

test('counts the interval from the last activity when the customer kept editing the cart', function () {
    abandonedCart(200, ['last_activity_at' => now()->subMinutes(20)]);

    DetectAbandonedCartsJob::dispatchSync();

    Mail::assertNothingSent();
});

test('the recovery email lists the items and links back to the storefront cart', function () {
    config()->set('shineray.storefront_url', 'https://tienda.test');
    $cart = abandonedCart(70);

    $rendered = (new AbandonedCartMail($cart))->render();

    expect($rendered)
        ->toContain($cart->items->first()->variant->product->title)
        ->toContain('https://tienda.test/cart?cart='.$cart->public_id);
});
