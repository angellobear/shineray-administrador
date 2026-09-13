<?php

namespace App\Domain\Payments\Exceptions;

use App\Enums\PaymentGateway;
use RuntimeException;

/**
 * El gateway no soporta la operación (ej. reembolso en DeUna). Reemplaza los
 * stubs silenciosos `return {}` del código Node: si algo intenta usarla, falla.
 */
class PaymentOperationNotSupportedException extends RuntimeException
{
    public static function for(PaymentGateway $gateway, string $operation): self
    {
        return new self(sprintf('El gateway %s no soporta la operación "%s".', $gateway->value, $operation));
    }
}
