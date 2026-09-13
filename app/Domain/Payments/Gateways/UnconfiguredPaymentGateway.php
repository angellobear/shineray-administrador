<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Contracts\PaymentGatewayContract;
use App\Domain\Payments\DTOs\PaymentResult;
use App\Domain\Payments\DTOs\PaymentSessionData;
use App\Domain\Shared\Exceptions\IntegrationNotImplementedException;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\Payment;

/**
 * Stub de la Fase 0: permite desarrollar contra el contrato antes de que
 * existan las implementaciones reales. Cada método falla ruidoso.
 */
final readonly class UnconfiguredPaymentGateway implements PaymentGatewayContract
{
    public function __construct(private PaymentGateway $gateway) {}

    public function gateway(): PaymentGateway
    {
        return $this->gateway;
    }

    public function initiate(Cart $cart, array $context = []): PaymentSessionData
    {
        throw $this->notImplemented(__FUNCTION__);
    }

    public function authorize(Payment $payment, array $sessionData = []): PaymentResult
    {
        throw $this->notImplemented(__FUNCTION__);
    }

    public function capture(Payment $payment): PaymentResult
    {
        throw $this->notImplemented(__FUNCTION__);
    }

    public function refund(Payment $payment, int $amountCents): PaymentResult
    {
        throw $this->notImplemented(__FUNCTION__);
    }

    public function cancel(Payment $payment): PaymentResult
    {
        throw $this->notImplemented(__FUNCTION__);
    }

    public function status(Payment $payment): PaymentStatus
    {
        throw $this->notImplemented(__FUNCTION__);
    }

    private function notImplemented(string $method): IntegrationNotImplementedException
    {
        return IntegrationNotImplementedException::for(
            PaymentGatewayContract::class.'['.$this->gateway->value.']',
            $method,
        );
    }
}
