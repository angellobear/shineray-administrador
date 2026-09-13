<?php

use App\Domain\Payments\Services\PaymentGatewayResolver;
use App\Enums\PaymentGateway;
use App\Models\Cart;
use App\Models\Payment;

test('issues a 32 character hexadecimal transaction id and approves without an external call', function () {
    $gateway = app(PaymentGatewayResolver::class)->resolve(PaymentGateway::CreditoB2b);

    $session = $gateway->initiate(Cart::factory()->create(), ['installments' => 3]);
    $result = $gateway->authorize(Payment::factory()->create(['gateway_reference' => $session->gatewayReference]));

    expect($session->gatewayReference)->toMatch('/^[0-9a-f]{32}$/');
    expect($session->data)->toHaveKey('installments', 3);
    expect($result)->isSuccessful()->toBeTrue()->responseCode->toBe('credito.approved');
});
