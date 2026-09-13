<?php

use App\Domain\Cart\Exceptions\CartException;
use App\Domain\Cart\Services\CartService;
use App\Domain\Orders\Services\CheckoutService;
use App\Domain\Payments\Exceptions\PaymentFailedException;
use App\Enums\CartStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Events\OrderPlaced;
use App\Events\PaymentAuthorized;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->checkout = app(CheckoutService::class);
    $this->carts = app(CartService::class);
});

function readyCart(?Customer $customer = null): Cart
{
    $carts = app(CartService::class);
    $cart = $carts->create($customer, 'ana@example.com');
    $carts->addItem($cart, ProductVariant::factory()->create(['price' => 10000, 'sku' => 'MOT-1']), 2);
    $carts->setShippingAddress($cart, [
        'first_name' => 'Ana', 'last_name' => 'Pérez', 'phone' => '0999999999', 'address_1' => 'Calle 1',
        'city' => 'QUITO', 'province' => 'PICHINCHA', 'metadata' => ['dni' => '0999999999', 'dni_type' => 1, 'city_id' => 42],
    ]);
    $carts->setShippingQuote($cart, 430);

    return $cart->fresh();
}

test('freezes the cart into a pending order with a pending payment and a gateway session', function () {
    $cart = readyCart();
    Discount::factory()->create(['code' => 'DIEZ', 'value' => 10]);
    $this->carts->applyDiscount($cart, 'DIEZ');

    $session = $this->checkout->start($cart, PaymentGateway::Manual, ['ip' => '1.1.1.1']);

    $order = $session->order->fresh();
    expect($order)
        ->status->toBe(OrderStatus::Pending)
        ->email->toBe('ana@example.com')
        ->subtotal->toBe(20000)
        ->discount_total->toBe(2000)
        ->shipping_total->toBe(430)
        ->tax_total->toBe(2765)
        ->total->toBe(21195);
    expect($order->order_number)->toMatch('/^SH-\d{6}-[A-Z0-9]{6}$/');
    expect($order->items)->toHaveCount(1);
    expect($order->items->first())->sku->toBe('MOT-1')->quantity->toBe(2)->unit_price->toBe(10000);
    expect($order->shippingAddress)->city->toBe('QUITO');
    expect($order->shippingAddress->metadata)->toHaveKey('dni', '0999999999');
    expect($order->metadata)->toHaveKey('city_id', 42)->toHaveKey('gateway', 'manual')->toHaveKey('is_b2b', false);
    expect($order->discounts)->toHaveCount(1);
    expect($session->payment)
        ->status->toBe(PaymentStatus::Pending)
        ->amount->toBe(21195)
        ->gateway_reference->toStartWith('manual-'.$cart->public_id);
    expect($cart->fresh()->status)->toBe(CartStatus::Active);
});

test('reuses the pending order of the same cart when the checkout is restarted', function () {
    $cart = readyCart();
    $first = $this->checkout->start($cart, PaymentGateway::Manual);
    $this->carts->addItem($cart, ProductVariant::factory()->create(['price' => 500]), 1);

    $second = $this->checkout->start($cart->fresh(), PaymentGateway::Manual);

    expect($second->order->id)->toBe($first->order->id);
    expect(Order::query()->count())->toBe(1);
    expect($second->order->fresh()->items)->toHaveCount(2);
    expect($second->order->fresh()->addresses)->toHaveCount(1);
    expect($second->order->payments()->count())->toBe(2);
});

test('refuses to start without items, email or shipping address', function () {
    $empty = $this->carts->create(email: 'x@example.com');
    expect(fn () => $this->checkout->start($empty, PaymentGateway::Manual))->toThrow(CartException::class, 'El carrito está vacío.');

    $noAddress = $this->carts->create(email: 'x@example.com');
    $this->carts->addItem($noAddress, ProductVariant::factory()->create(), 1);
    expect(fn () => $this->checkout->start($noAddress, PaymentGateway::Manual))->toThrow(CartException::class);
});

test('completing an authorized payment marks the order paid, closes the cart and dispatches the domain events', function () {
    Event::fake([PaymentAuthorized::class, OrderPlaced::class]);
    $this->freezeSecond();
    $cart = readyCart();
    $session = $this->checkout->start($cart, PaymentGateway::Manual);

    $order = $this->checkout->complete($session->payment, ['approved' => true]);

    expect($order)->status->toBe(OrderStatus::Paid)->paid_at->toEqual(now());
    expect($session->payment->fresh())
        ->status->toBe(PaymentStatus::Authorized)
        ->response_code->toBe('manual.approved')
        ->authorized_at->toEqual(now());
    expect($cart->fresh())
        ->status->toBe(CartStatus::Completed)
        ->completed_at->toEqual(now())
        ->abandoned_completed_at->toEqual(now());
    Event::assertDispatched(PaymentAuthorized::class, fn (PaymentAuthorized $event) => $event->order->is($order) && $event->payment->is($session->payment));
    Event::assertDispatched(OrderPlaced::class, 1);
    expect(IntegrationLog::query()->where('event', 'authorize_ok')->exists())->toBeTrue();
});

test('a declined payment leaves the order pending, the cart open and records the failure', function () {
    Event::fake([PaymentAuthorized::class, OrderPlaced::class]);
    $cart = readyCart();
    $session = $this->checkout->start($cart, PaymentGateway::Manual);

    expect(fn () => $this->checkout->complete($session->payment, ['approved' => false]))
        ->toThrow(PaymentFailedException::class, 'Pago manual rechazado.');

    expect($session->order->fresh()->status)->toBe(OrderStatus::Pending);
    expect($session->payment->fresh())->status->toBe(PaymentStatus::Failed)->response_code->toBe('manual.declined');
    expect($cart->fresh()->status)->toBe(CartStatus::Active);
    Event::assertNotDispatched(PaymentAuthorized::class);
    expect(IntegrationLog::query()->where('event', 'authorize_fail')->first()->loggable_id)->toBe($session->payment->id);
});

test('completing twice is idempotent and does not dispatch the events again', function () {
    Event::fake([PaymentAuthorized::class, OrderPlaced::class]);
    $session = $this->checkout->start(readyCart(), PaymentGateway::Manual);

    $this->checkout->complete($session->payment);
    $again = $this->checkout->complete($session->payment);

    expect($again->status)->toBe(OrderStatus::Paid);
    Event::assertDispatchedTimes(PaymentAuthorized::class, 1);
    Event::assertDispatchedTimes(OrderPlaced::class, 1);
});

test('marks the order as B2B when the customer is B2B or the gateway is B2B', function () {
    $b2bCustomer = Customer::factory()->b2b()->create();

    $session = $this->checkout->start(readyCart($b2bCustomer), PaymentGateway::Manual, ['installments' => 3, 'transportista_id' => 'T1']);

    expect($session->order->fresh())
        ->isB2b()->toBeTrue()
        ->customer_id->toBe($b2bCustomer->id);
    expect($session->order->metadata)->toHaveKey('installments', 3)->toHaveKey('transportista_id', 'T1');
});
