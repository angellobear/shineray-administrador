<?php

namespace App\Domain\Orders\DTOs;

use App\Domain\Payments\DTOs\PaymentSessionData;
use App\Models\Order;
use App\Models\Payment;

/** Resultado de iniciar un checkout: la orden pendiente, su pago y lo que el gateway necesita del frontend. */
final readonly class CheckoutSession
{
    public function __construct(
        public Order $order,
        public Payment $payment,
        public PaymentSessionData $session,
    ) {}
}
