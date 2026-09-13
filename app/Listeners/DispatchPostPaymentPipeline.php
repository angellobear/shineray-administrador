<?php

namespace App\Listeners;

use App\Domain\Payments\Jobs\SaveInvoiceToErpJob;
use App\Events\PaymentAuthorized;
use Illuminate\Support\Facades\Bus;

/**
 * Tras un pago autorizado encadena, en orden, los efectos secundarios que hoy
 * viven dentro del payment processor: guía Servientrega → factura en el ERP →
 * email de confirmación. Cada paso tiene sus reintentos; ninguno bloquea la
 * confirmación del pago que ya ocurrió.
 */
class DispatchPostPaymentPipeline
{
    public function handle(PaymentAuthorized $event): void
    {
        Bus::chain(self::jobsFor($event))->dispatch();
    }

    /**
     * @return list<object>
     */
    public static function jobsFor(PaymentAuthorized $event): array
    {
        return [
            new SaveInvoiceToErpJob($event->order, $event->payment),
        ];
    }
}
