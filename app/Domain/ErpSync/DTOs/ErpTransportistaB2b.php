<?php

namespace App\Domain\ErpSync\DTOs;

final readonly class ErpTransportistaB2b
{
    public function __construct(
        public string $ruc,
        public string $businessName,
    ) {}
}
