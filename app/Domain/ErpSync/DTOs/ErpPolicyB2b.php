<?php

namespace App\Domain\ErpSync\DTOs;

final readonly class ErpPolicyB2b
{
    public function __construct(
        public string $clientType,
        public bool $isActive,
        public float $creditFactor,
        public int $installments,
    ) {}
}
