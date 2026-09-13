<?php

namespace App\Domain\ErpSync\DTOs;

final readonly class StockResult
{
    public function __construct(
        public string $sku,
        public bool $completePurchase,
        public int $quantity,
    ) {}

    public static function unavailable(string $sku): self
    {
        return new self($sku, false, 0);
    }
}
