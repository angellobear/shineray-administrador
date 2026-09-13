<?php

namespace App\Domain\Payments\Contracts;

use App\Domain\Payments\DTOs\PaymentResult;
use App\Domain\Payments\DTOs\PaymentSessionData;
use App\Domain\Payments\Exceptions\PaymentOperationNotSupportedException;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\Payment;

/**
 * Contrato de pasarela de pago. Un gateway SOLO habla con el proveedor
 * externo: nunca crea guías, factura en el ERP ni envía correos — eso lo hacen
 * los listeners de `PaymentAuthorized`.
 */
interface PaymentGatewayContract
{
    public function gateway(): PaymentGateway;

    /**
     * Inicia la sesión de pago (equivalente a `initiatePayment` de Medusa).
     *
     * @param  array<string, mixed>  $context  Datos extra del checkout (ip, user agent, cuotas B2B, etc.).
     */
    public function initiate(Cart $cart, array $context = []): PaymentSessionData;

    /**
     * Confirma/autoriza el pago tras el flujo del gateway (3DS, redirect, webhook).
     *
     * @param  array<string, mixed>  $sessionData
     */
    public function authorize(Payment $payment, array $sessionData = []): PaymentResult;

    /** @throws PaymentOperationNotSupportedException si el gateway no soporta captura diferida. */
    public function capture(Payment $payment): PaymentResult;

    /** @throws PaymentOperationNotSupportedException si el gateway no soporta reembolsos. */
    public function refund(Payment $payment, int $amountCents): PaymentResult;

    /** @throws PaymentOperationNotSupportedException si el gateway no soporta cancelación. */
    public function cancel(Payment $payment): PaymentResult;

    /** Consulta de estado real contra el gateway, no un valor cacheado. */
    public function status(Payment $payment): PaymentStatus;
}
