<?php

namespace App\Domain\ErpSync\DTOs;

final readonly class ErpClientB2b
{
    /**
     * @param  array<string, mixed>  $address
     */
    public function __construct(
        public string $idClient,
        public ?string $typeClient,
        public string $firstName,
        public string $lastName,
        public ?string $email,
        public ?string $phoneNumber,
        public array $address = [],
        public bool $active = true,
    ) {}
}
