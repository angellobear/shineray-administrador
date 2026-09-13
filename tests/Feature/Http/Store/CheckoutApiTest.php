<?php

use App\Domain\Cart\Services\CartService;
use App\Enums\OrderStatus;
use App\Events\PaymentAuthorized;
use App\Models\Cart;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    config()->set('shineray.checkout.storefront_gateways', ['manual', 'datafast']);
});

function apiReadyCart(): Cart
{
    $carts = app(CartService::class);
    $cart = $carts->create(email: 'ana@example.com');
    $carts->addItem($cart, ProductVariant::factory()->create(['price' => 1000]), 1);
    $carts->setShippingAddress($cart, [
        'first_name' => 'Ana', 'last_name' => 'Pérez', 'phone' => '0999999999', 'address_1' => 'Calle 1',
        'city' => 'QUITO', 'province' => 'PICHINCHA', 'metadata' => ['dni' => '0999999999', 'dni_type' => 1],
    ]);

    return $cart->fresh();
}

test('starts a checkout and returns the pending order with the payment session', function () {
    $cart = apiReadyCart();

    $response = $this->postJson(route('store.carts.checkout.start', $cart), ['gateway' => 'manual']);

    $response->assertCreated()
        ->assertJsonPath('order.status', 'pending')
        ->assertJsonPath('order.total', 1150)
        ->assertJsonPath('payment.gateway', 'manual')
        ->assertJsonPath('payment.status', 'pending');
    expect($response->json('payment.reference'))->toStartWith('manual-');
});

test('rejects gateways that are not enabled for the storefront', function () {
    $cart = apiReadyCart();

    $this->postJson(route('store.carts.checkout.start', $cart), ['gateway' => 'deuna'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['gateway']);
});

test('returns a business error when the cart is not ready', function () {
    $cart = Cart::factory()->create(['email' => 'x@example.com']);

    $this->postJson(route('store.carts.checkout.start', $cart), ['gateway' => 'manual'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'El carrito está vacío.');
});

test('completes the order after the gateway approves', function () {
    Event::fake([PaymentAuthorized::class]);
    $cart = apiReadyCart();
    $orderNumber = $this->postJson(route('store.carts.checkout.start', $cart), ['gateway' => 'manual'])->json('order.order_number');

    $this->postJson(route('store.orders.complete', $orderNumber), ['session_data' => ['approved' => true]])
        ->assertOk()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.order_number', $orderNumber);

    expect(Order::query()->where('order_number', $orderNumber)->firstOrFail()->status)->toBe(OrderStatus::Paid);
    Event::assertDispatched(PaymentAuthorized::class);
});

test('reports a declined payment as 422 with the gateway code', function () {
    $cart = apiReadyCart();
    $orderNumber = $this->postJson(route('store.carts.checkout.start', $cart), ['gateway' => 'manual'])->json('order.order_number');

    $this->postJson(route('store.orders.complete', $orderNumber), ['session_data' => ['approved' => false]])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'manual.declined');
});

test('shows an order to its buyer by email or as the owning customer, and hides it otherwise', function () {
    $customer = Customer::factory()->create();
    $order = Order::factory()->for($customer)->create(['email' => 'ana@example.com']);

    $this->getJson(route('store.orders.show', $order))->assertNotFound();
    $this->getJson(route('store.orders.show', [$order, 'email' => 'otra@example.com']))->assertNotFound();
    $this->getJson(route('store.orders.show', [$order, 'email' => 'ANA@example.com']))->assertOk()->assertJsonPath('data.order_number', $order->order_number);

    Sanctum::actingAs(Customer::factory()->create(), guard: 'customer');
    $this->getJson(route('store.orders.show', $order))->assertNotFound();

    Sanctum::actingAs($customer, guard: 'customer');
    $this->getJson(route('store.orders.show', $order))->assertOk();
});
