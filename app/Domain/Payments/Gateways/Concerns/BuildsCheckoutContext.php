<?php

namespace App\Domain\Payments\Gateways\Concerns;

use App\Models\Order;
use App\Models\Payment;

/** Helpers compartidos para leer la orden/pago que `CheckoutService` pasa en el contexto. */
trait BuildsCheckoutContext
{
    /**
     * @param  array<string, mixed>  $context
     */
    protected function orderFromContext(array $context): ?Order
    {
        $order = $context['order'] ?? null;

        return $order instanceof Order ? $order : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function paymentFromContext(array $context): ?Payment
    {
        $payment = $context['payment'] ?? null;

        return $payment instanceof Payment ? $payment : null;
    }

    protected function dollars(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
