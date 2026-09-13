<?php

namespace App\Domain\ErpSync\Exceptions;

use RuntimeException;

/** Respuesta inesperada o fallida del ERP Shineray/Massline. */
class ErpRequestException extends RuntimeException {}
