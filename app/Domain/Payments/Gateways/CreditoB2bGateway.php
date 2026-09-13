<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Contracts\PaymentGatewayContract;
use App\Domain\Payments\DTOs\PaymentResult;
use App\Domain\Payments\DTOs\PaymentSessionData;
use App\Domain\Payments\Exceptions\PaymentOperationNotSupportedException;
use App\Domain\Payments\Gateways\Concerns\BuildsCheckoutContext;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\Payment;

/**
 * Crédito directo B2B: no hay pasarela. El "pago" se aprueba y la factura a
 * crédito se registra en el ERP (`cf1/credito_directo`) por el listener de
 * `PaymentAuthorized`. El `transactionId` de 32 hex replica el que hoy genera
 * `credito-b2b-payment-processor.ts`.
 */
final class CreditoB2bGateway implements PaymentGatewayContract
{
    use BuildsCheckoutContext;

    public function gateway(): PaymentGateway
    {
        return PaymentGateway::CreditoB2b;
    }

    public function initiate(Cart $cart, array $context = []): PaymentSessionData
    {
        $transactionId = mb_substr(dechex(now()->getTimestampMs()).bin2hex(random_bytes(16)), 0, 32);

        return new PaymentSessionData(
            gatewayReference: $transactionId,
            data: [
                'transaction_id' => $transactionId,
                'installments' => $context['installments'] ?? null,
            ],
        );
    }

    public function authorize(Payment $payment, array $sessionData = []): PaymentResult
    {
        return PaymentResult::authorized((string) $payment->gateway_reference, 'credito.approved');
    }

    public function capture(Payment $payment): PaymentResult
    {
        return new PaymentResult(PaymentStatus::Captured, $payment->gateway_reference, $payment->response_code);
    }

    public function refund(Payment $payment, int $amountCents): PaymentResult
    {
        throw PaymentOperationNotSupportedException::for($this->gateway(), 'refund');
    }

    public function cancel(Payment $payment): PaymentResult
    {
        return new PaymentResult(PaymentStatus::Canceled, $payment->gateway_reference);
    }

    public function status(Payment $payment): PaymentStatus
    {
        return $payment->status;
    }
}
