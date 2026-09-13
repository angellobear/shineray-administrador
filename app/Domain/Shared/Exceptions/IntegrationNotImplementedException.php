<?php

namespace App\Domain\Shared\Exceptions;

use RuntimeException;

/**
 * Lanzada por los stubs de contratos cuya implementación concreta todavía no
 * existe. Falla ruidoso a propósito: nunca debe llegar a producción.
 */
class IntegrationNotImplementedException extends RuntimeException
{
    public static function for(string $contract, string $method): self
    {
        return new self(sprintf('%s::%s() no tiene implementación todavía.', $contract, $method));
    }
}
