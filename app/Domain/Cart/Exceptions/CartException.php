<?php

namespace App\Domain\Cart\Exceptions;

use RuntimeException;

/** Regla de negocio del carrito violada (carrito cerrado, producto no disponible, cupón inválido). */
class CartException extends RuntimeException
{
    public static function notActive(): self
    {
        return new self('El carrito ya fue completado o abandonado.');
    }

    public static function productUnavailable(): self
    {
        return new self('El producto no está disponible.');
    }

    public static function discountNotUsable(string $code): self
    {
        return new self(sprintf('El cupón "%s" no es válido.', $code));
    }
}
