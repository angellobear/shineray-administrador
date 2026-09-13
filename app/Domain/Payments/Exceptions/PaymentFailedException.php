<?php

namespace App\Domain\Payments\Exceptions;

use App\Domain\Payments\DTOs\PaymentResult;
use RuntimeException;

/** El gateway rechazó o no pudo autorizar el pago. */
class PaymentFailedException extends RuntimeException
{
    public function __construct(public readonly PaymentResult $result)
    {
        parent::__construct($result->message ?? 'El pago no fue autorizado.');
    }
}
