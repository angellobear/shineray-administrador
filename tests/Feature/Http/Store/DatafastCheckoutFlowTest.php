<?php

use App\Domain\Cart\Services\CartService;
use App\Domain\Payments\Jobs\SaveInvoiceToErpJob;
use App\Domain\Shipping\Jobs\CreateShipmentGuideJob;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

test('a storefront checkout with Datafast runs end to end and queues the ERP invoice', function () {
    Bus::fake();
    config()->set('services.datafast', ['base_url' => 'https://oppwa.test', 'entity_id' => 'ENT', 'entity_id_b2b' => null, 'bearer_token' => 'tok', 'shopper_mid' => 'M', 'shopper_tid' => 'T', 'shopper_eci' => 'E', 'shopper_pserv' => 'P', 'timeout' => 30]);
    Http::fake([
        'oppwa.test/v1/checkouts' => Http::response(file_get_contents(base_path('tests/Fixtures/datafast/checkout_created.json')), 200, ['Content-Type' => 'application/json']),
        'oppwa.test/v1/checkouts/CHK123/payment*' => Http::response(file_get_contents(base_path('tests/Fixtures/datafast/payment_success.json')), 200, ['Content-Type' => 'application/json']),
    ]);
    $carts = app(CartService::class);
    $cart = $carts->create(email: 'ana@example.com');
    $carts->addItem($cart, ProductVariant::factory()->create(['price' => 10000]), 1);
    $carts->setShippingAddress($cart, ['first_name' => 'Ana', 'last_name' => 'Pérez', 'phone' => '0999', 'address_1' => 'Calle 1', 'city' => 'QUITO', 'province' => 'PICHINCHA', 'metadata' => ['dni' => '0999999999', 'dni_type' => 1, 'city_id' => 42]]);

    $start = $this->postJson(route('store.carts.checkout.start', $cart->fresh()), ['gateway' => 'datafast'])
        ->assertCreated()
        ->assertJsonPath('payment.reference', 'CHK123')
        ->assertJsonPath('payment.data.checkout_id', 'CHK123');

    $this->postJson(route('store.orders.complete', $start->json('order.order_number')), ['session_data' => ['resourcePath' => '/v1/checkouts/CHK123/payment']])
        ->assertOk()
        ->assertJsonPath('data.status', 'paid');

    $order = Order::query()->where('order_number', $start->json('order.order_number'))->firstOrFail();
    expect($order->status)->toBe(OrderStatus::Paid);
    expect($order->latestPayment()->raw_response['card']['last4Digits'])->toBe('1111');
    Bus::assertChained([CreateShipmentGuideJob::class, SaveInvoiceToErpJob::class]);
});
