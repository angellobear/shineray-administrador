<?php

namespace App\Domain\Payments\Gateways;

use App\Domain\Payments\Contracts\PaymentGatewayContract;
use App\Domain\Payments\DTOs\PaymentResult;
use App\Domain\Payments\DTOs\PaymentSessionData;
use App\Domain\Payments\Exceptions\PaymentOperationNotSupportedException;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\Payment;

/**
 * Gateway sin proveedor externo: aprueba salvo que la sesión diga
 * `approved = false`. Sirve para órdenes manuales del admin y para probar el
 * checkout de punta a punta sin Datafast/DeUna.
 */
final class ManualPaymentGateway implements PaymentGatewayContract
{
    public function gateway(): PaymentGateway
    {
        return PaymentGateway::Manual;
    }

    public function initiate(Cart $cart, array $context = []): PaymentSessionData
    {
        $payment = $context['payment'] ?? null;
        $suffix = $payment instanceof Payment ? (string) $payment->id : (string) now()->getTimestampMs();

        return new PaymentSessionData(
            gatewayReference: 'manual-'.$cart->public_id.'-'.$suffix,
            data: ['gateway' => 'manual'],
        );
    }

    public function authorize(Payment $payment, array $sessionData = []): PaymentResult
    {
        if (($sessionData['approved'] ?? true) === false) {
            return PaymentResult::failed('manual.declined', 'Pago manual rechazado.', $sessionData);
        }

        return PaymentResult::authorized((string) $payment->gateway_reference, 'manual.approved', $sessionData);
    }

    public function capture(Payment $payment): PaymentResult
    {
        return new PaymentResult(PaymentStatus::Captured, $payment->gateway_reference);
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
