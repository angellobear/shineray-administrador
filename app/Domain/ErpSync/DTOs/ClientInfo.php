<?php

namespace App\Domain\ErpSync\DTOs;

/**
 * Cliente tal como lo conoce el sistema de facturación del ERP.
 */
final readonly class ClientInfo
{
    /**
     * @param  array<string, mixed>  $address
     */
    public function __construct(
        public string $idClient,
        public string $firstName,
        public string $lastName,
        public string $email,
        public ?string $phone = null,
        public ?string $dni = null,
        public ?string $ruc = null,
        public array $address = [],
    ) {}
}
