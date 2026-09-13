<?php

namespace App\Domain\ErpSync\DTOs;

final readonly class StockResult
{
    public function __construct(
        public string $sku,
        public bool $available,
        public int $quantity,
    ) {}
}
