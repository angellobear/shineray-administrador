<?php

namespace App\Domain\Payments\Exceptions;

use App\Enums\PaymentGateway;
use RuntimeException;

/** El gateway no pudo iniciar la sesión de pago (error HTTP o código de respuesta inesperado). */
class PaymentGatewayException extends RuntimeException
{
    public static function initiateFailed(PaymentGateway $gateway, string $detail): self
    {
        return new self(sprintf('No se pudo iniciar el pago con %s: %s', $gateway->value, $detail));
    }
}
