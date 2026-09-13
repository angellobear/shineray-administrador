<?php

use App\Domain\Cart\Services\CartService;
use App\Domain\Payments\Exceptions\PaymentGatewayException;
use App\Domain\Payments\Exceptions\PaymentOperationNotSupportedException;
use App\Domain\Payments\Gateways\DatafastGateway;
use App\Domain\Payments\Services\PaymentGatewayResolver;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.datafast', [
        'base_url' => 'https://oppwa.test', 'entity_id' => 'ENT-B2C', 'entity_id_b2b' => 'ENT-B2B', 'bearer_token' => 'tok',
        'shopper_mid' => 'MID', 'shopper_tid' => 'TID', 'shopper_eci' => 'ECI', 'shopper_pserv' => 'PSERV', 'timeout' => 30,
    ]);
    Http::preventStrayRequests();
});

function datafastCart(): Cart
{
    $carts = app(CartService::class);
    $cart = $carts->create(email: 'ana@example.com');
    $carts->addItem($cart, ProductVariant::factory()->for(Product::factory(['title' => 'Bujía NGK']))->create(['price' => 5000]), 2);
    $carts->setShippingAddress($cart, [
        'first_name' => 'Ana', 'last_name' => 'Pérez', 'phone' => '0999999999', 'address_1' => 'Calle 1',
        'city' => 'QUITO', 'province' => 'PICHINCHA', 'metadata' => ['dni' => '09999999991234', 'dni_type' => 1],
    ]);

    return $cart->fresh();
}

test('creates the OPPWa checkout with the customer, tax breakdown and cart items', function () {
    Http::fake(['oppwa.test/v1/checkouts' => Http::response(file_get_contents(base_path('tests/Fixtures/datafast/checkout_created.json')), 200, ['Content-Type' => 'application/json'])]);
    $cart = datafastCart();
    $order = Order::factory()->create(['order_number' => 'SH-1', 'total' => 11500]);
    $payment = Payment::factory()->for($order)->create();

    $session = app(PaymentGatewayResolver::class)->resolve(PaymentGateway::Datafast)
        ->initiate($cart, ['ip' => '10.0.0.1', 'order' => $order, 'payment' => $payment]);

    expect($session)
        ->gatewayReference->toBe('CHK123')
        ->data->toHaveKey('widget_url', 'https://oppwa.test/v1/paymentWidgets.js?checkoutId=CHK123');
    Http::assertSent(function (Request $request) {
        $data = $request->data();

        return $request->isForm()
            && $request->hasHeader('Authorization', 'Bearer tok')
            && $data['entityId'] === 'ENT-B2C'
            && $data['amount'] === '115.00'
            && $data['customParameters[SHOPPER_VAL_BASEIMP]'] === '100.00'
            && $data['customParameters[SHOPPER_VAL_IVA]'] === '15.00'
            && $data['customParameters[SHOPPER_MID]'] === 'MID'
            && $data['customer.identificationDocId'] === '0999999999'
            && $data['customer.givenName'] === 'Ana'
            && $data['customer.ip'] === '10.0.0.1'
            && str_starts_with($data['merchantTransactionId'], 'SH-1_')
            && $data['billing.country'] === 'EC'
            && $data['cart.items[0].name'] === rawurlencode('Bujía NGK')
            && $data['cart.items[0].price'] === '100.00'
            && $data['cart.items[0].quantity'] === 2;
    });
    expect(IntegrationLog::query()->where('event', 'initiate')->first()->loggable_id)->toBe($payment->id);
});

test('uses the B2B entity for the B2B gateway', function () {
    Http::fake(['oppwa.test/v1/checkouts' => Http::response(file_get_contents(base_path('tests/Fixtures/datafast/checkout_created.json')), 200, ['Content-Type' => 'application/json'])]);

    $gateway = app(PaymentGatewayResolver::class)->resolve(PaymentGateway::DatafastB2b);
    $gateway->initiate(datafastCart());

    expect($gateway->gateway())->toBe(PaymentGateway::DatafastB2b);
    Http::assertSent(fn (Request $request) => $request['entityId'] === 'ENT-B2B');
});

test('fails loudly when OPPWa does not create the checkout', function () {
    Http::fake(['oppwa.test/v1/checkouts' => Http::response(['result' => ['code' => '800.100.100', 'description' => 'invalid entity']])]);

    expect(fn () => app(PaymentGatewayResolver::class)->resolve(PaymentGateway::Datafast)->initiate(datafastCart()))
        ->toThrow(PaymentGatewayException::class, 'invalid entity');
    expect(IntegrationLog::query()->where('event', 'initiate_fail')->exists())->toBeTrue();
});

test('fails loudly on an HTTP error while creating the checkout', function () {
    Http::fake(['oppwa.test/v1/checkouts' => Http::response('down', 503)]);

    expect(fn () => app(PaymentGatewayResolver::class)->resolve(PaymentGateway::Datafast)->initiate(datafastCart()))
        ->toThrow(PaymentGatewayException::class);
});

test('authorizes when the payment status query returns a success code and keeps the card data', function () {
    Http::fake(['oppwa.test/v1/checkouts/CHK123/payment*' => Http::response(file_get_contents(base_path('tests/Fixtures/datafast/payment_success.json')), 200, ['Content-Type' => 'application/json'])]);
    $payment = Payment::factory()->create(['gateway_reference' => 'CHK123']);

    $result = app(PaymentGatewayResolver::class)->resolve(PaymentGateway::Datafast)
        ->authorize($payment, ['resourcePath' => '/v1/checkouts/CHK123/payment']);

    expect($result)
        ->isSuccessful()->toBeTrue()
        ->status->toBe(PaymentStatus::Authorized)
        ->gatewayReference->toBe('8ac7a4a08b1')
        ->responseCode->toBe('000.000.000');
    expect($result->raw['card']['last4Digits'])->toBe('1111');
    Http::assertSent(fn (Request $request) => str_contains($request->url(), 'entityId=ENT-B2C'));
});

test('treats a declined code as a failed payment', function () {
    Http::fake(['oppwa.test/*' => Http::response(file_get_contents(base_path('tests/Fixtures/datafast/payment_declined.json')), 200, ['Content-Type' => 'application/json'])]);

    $result = app(PaymentGatewayResolver::class)->resolve(PaymentGateway::Datafast)
        ->authorize(Payment::factory()->create(), ['transactionURL' => '/v1/checkouts/CHK123/payment']);

    expect($result)->isSuccessful()->toBeFalse()->responseCode->toBe('800.100.151');
});

test('recognises the documented OPPWa success codes', function (string $code, bool $success) {
    expect(preg_match(DatafastGateway::SUCCESS_PATTERN, $code) === 1)->toBe($success);
})->with([
    'succeeded' => ['000.000.000', true],
    'succeeded (3D)' => ['000.100.110', true],
    'pending' => ['000.200.000', false],
    'declined' => ['800.100.151', false],
    'rejected risk' => ['100.380.401', false],
]);

test('fails without the widget resource path', function () {
    $result = app(PaymentGatewayResolver::class)->resolve(PaymentGateway::Datafast)->authorize(Payment::factory()->create(), []);

    expect($result)->isSuccessful()->toBeFalse()->responseCode->toBe('MISSING_TRANSACTION_URL');
});

test('refunds and cancellations are not supported', function () {
    $gateway = app(PaymentGatewayResolver::class)->resolve(PaymentGateway::Datafast);
    $payment = Payment::factory()->authorized()->create();

    expect(fn () => $gateway->refund($payment, 100))->toThrow(PaymentOperationNotSupportedException::class);
    expect(fn () => $gateway->cancel($payment))->toThrow(PaymentOperationNotSupportedException::class);
});
