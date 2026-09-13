<?php

namespace App\Events;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * El gateway autorizó el pago y la orden quedó `paid`. Los efectos
 * secundarios (guía Servientrega, factura en el ERP, email) son listeners en
 * cola de este evento; ninguno bloquea la confirmación del pago.
 */
class PaymentAuthorized implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order,
        public Payment $payment,
    ) {}
}
