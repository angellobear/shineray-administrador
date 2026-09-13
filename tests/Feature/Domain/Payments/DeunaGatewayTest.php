<?php

use App\Domain\Payments\Exceptions\PaymentGatewayException;
use App\Domain\Payments\Services\PaymentGatewayResolver;
use App\Enums\PaymentGateway;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.deuna', ['base_url' => 'https://deuna.test/merchant/api/v1', 'api_key' => 'key', 'api_secret' => 'secret', 'point_of_sale' => '12260', 'webhook_secret' => 'hook', 'timeout' => 30]);
    Http::preventStrayRequests();
});

test('requests a dynamic QR with the order number as internal reference', function () {
    Http::fake(['deuna.test/merchant/api/v1/payment/request' => Http::response(file_get_contents(base_path('tests/Fixtures/deuna/payment_request.json')), 200, ['Content-Type' => 'application/json'])]);
    $order = Order::factory()->create(['order_number' => 'SH-260913-ABC123', 'total' => 1150]);

    $session = app(PaymentGatewayResolver::class)->resolve(PaymentGateway::Deuna)
        ->initiate(Cart::factory()->create(), ['order' => $order, 'payment' => Payment::factory()->for($order)->create()]);

    expect($session)
        ->gatewayReference->toBe('DEUNA-TX-001')
        ->data->toHaveKey('qr')
        ->data->toHaveKey('deeplink', 'deuna://pay/DEUNA-TX-001')
        ->data->toHaveKey('internal_reference', 'SH-260913-ABC123');
    Http::assertSent(fn (Request $request) => $request->hasHeader('x-api-key', 'key')
        && $request->hasHeader('x-api-secret', 'secret')
        && $request['pointOfSale'] === '12260'
        && $request['amount'] === 11.5
        && $request['qrType'] === 'dynamic'
        && $request['internalTransactionReference'] === 'SH-260913-ABC123'
        && ! isset($request['expiredTime']));
});

test('the B2B variant sets an expiry on the QR', function () {
    Http::fake(['deuna.test/*' => Http::response(file_get_contents(base_path('tests/Fixtures/deuna/payment_request.json')), 200, ['Content-Type' => 'application/json'])]);

    $gateway = app(PaymentGatewayResolver::class)->resolve(PaymentGateway::DeunaB2b);
    $gateway->initiate(Cart::factory()->create());

    expect($gateway->gateway())->toBe(PaymentGateway::DeunaB2b);
    Http::assertSent(fn (Request $request) => $request['expiredTime'] === 5);
});

test('fails loudly when DeUna returns no transaction id', function () {
    Http::fake(['deuna.test/*' => Http::response(['error' => 'bad request'], 400)]);

    expect(fn () => app(PaymentGatewayResolver::class)->resolve(PaymentGateway::Deuna)->initiate(Cart::factory()->create()))
        ->toThrow(PaymentGatewayException::class);
});

test('authorizes only after payment info reports the transaction approved', function () {
    Http::fake(['deuna.test/merchant/api/v1/payment/info' => Http::response(file_get_contents(base_path('tests/Fixtures/deuna/payment_info_approved.json')), 200, ['Content-Type' => 'application/json'])]);
    $payment = Payment::factory()->gateway(PaymentGateway::Deuna)->create(['gateway_reference' => 'DEUNA-TX-001']);

    $result = app(PaymentGatewayResolver::class)->resolve(PaymentGateway::Deuna)->authorize($payment);

    expect($result)->isSuccessful()->toBeTrue()->gatewayReference->toBe('DEUNA-TX-001')->responseCode->toBe('APPROVED');
    Http::assertSent(fn (Request $request) => $request['idTransacionReference'] === 'DEUNA-TX-001' && $request['idType'] === '0');
});

test('rejects a transaction that is not approved', function () {
    Http::fake(['deuna.test/*' => Http::response(['status' => 'PENDING'])]);

    $result = app(PaymentGatewayResolver::class)->resolve(PaymentGateway::Deuna)
        ->authorize(Payment::factory()->create(['gateway_reference' => 'DEUNA-TX-002']));

    expect($result)->isSuccessful()->toBeFalse()->responseCode->toBe('PENDING');
});
