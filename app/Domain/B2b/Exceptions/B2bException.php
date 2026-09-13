<?php

namespace App\Domain\B2b\Exceptions;

use RuntimeException;

class B2bException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public static function clientNotFound(): self
    {
        return new self('No encontramos un cliente B2B con esa identificación. Verifique los datos e intente de nuevo.', 404);
    }

    public static function accountAlreadyExists(): self
    {
        return new self('El cliente ya existe con una cuenta. Inicie sesión o recupere su contraseña.', 409);
    }

    public static function notB2b(): self
    {
        return new self('Esta operación es solo para clientes B2B.', 403);
    }
}
