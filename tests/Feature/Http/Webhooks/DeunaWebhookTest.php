<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Events\PaymentAuthorized;
use App\Models\IntegrationLog;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.deuna', ['base_url' => 'https://deuna.test/merchant/api/v1', 'api_key' => 'key', 'api_secret' => 'secret', 'point_of_sale' => '12260', 'webhook_secret' => 'hook-secret', 'timeout' => 30]);
    Http::preventStrayRequests();
});

function deunaPayload(string $status = 'APPROVED'): array
{
    return ['status' => $status, 'currency' => 'USD', 'amount' => 11.5, 'idTransaction' => 'DEUNA-TX-001', 'internalTransactionReference' => 'SH-260913-ABC123'];
}

test('rejects requests without the shared secret', function () {
    $this->postJson(route('webhooks.deuna'), deunaPayload())->assertUnauthorized();
    $this->withHeader('x-api-key', 'wrong')->postJson(route('webhooks.deuna'), deunaPayload())->assertUnauthorized();
});

test('confirms the order after verifying the transaction with DeUna', function () {
    Event::fake([PaymentAuthorized::class]);
    Http::fake(['deuna.test/merchant/api/v1/payment/info' => Http::response(file_get_contents(base_path('tests/Fixtures/deuna/payment_info_approved.json')), 200, ['Content-Type' => 'application/json'])]);
    $order = Order::factory()->create(['order_number' => 'SH-260913-ABC123']);
    $payment = Payment::factory()->for($order)->gateway(PaymentGateway::Deuna)->create(['gateway_reference' => 'DEUNA-TX-001']);

    $this->withHeader('Authorization', 'Bearer hook-secret')
        ->postJson(route('webhooks.deuna'), deunaPayload())
        ->assertOk()
        ->assertJsonPath('message', 'Success');

    expect($order->fresh()->status)->toBe(OrderStatus::Paid);
    expect($payment->fresh()->isSuccessful())->toBeTrue();
    Event::assertDispatched(PaymentAuthorized::class);
});

test('answers 200 for an unknown transaction and records it', function () {
    $this->withHeader('x-api-key', 'hook-secret')
        ->postJson(route('webhooks.deuna'), deunaPayload())
        ->assertOk()
        ->assertJsonPath('message', 'Unknown transaction');

    expect(IntegrationLog::query()->where('event', 'webhook_unmatched')->exists())->toBeTrue();
});

test('ignores non-approved statuses without touching the payment', function () {
    $payment = Payment::factory()->gateway(PaymentGateway::Deuna)->create(['gateway_reference' => 'DEUNA-TX-001']);

    $this->withHeader('x-api-key', 'hook-secret')
        ->postJson(route('webhooks.deuna'), deunaPayload('REJECTED'))
        ->assertOk()
        ->assertJsonPath('message', 'Ignored');

    expect($payment->fresh()->isSuccessful())->toBeFalse();
});

test('does not re-process an order that is already paid', function () {
    Event::fake([PaymentAuthorized::class]);
    $order = Order::factory()->paid()->create();
    Payment::factory()->for($order)->gateway(PaymentGateway::Deuna)->authorized()->create(['gateway_reference' => 'DEUNA-TX-001']);

    $this->withHeader('x-api-key', 'hook-secret')
        ->postJson(route('webhooks.deuna'), deunaPayload())
        ->assertOk()
        ->assertJsonPath('message', 'Success');

    Event::assertNotDispatched(PaymentAuthorized::class);
    Http::assertNothingSent();
});
