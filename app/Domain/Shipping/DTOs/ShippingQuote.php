<?php

namespace App\Domain\Shipping\DTOs;

final readonly class ShippingQuote
{
    /**
     * @param  array<string, mixed>  $raw  Respuesta cruda ya parseada (no XML) para auditoría.
     */
    public function __construct(
        public int $costCents,
        public bool $isFallback = false,
        public array $raw = [],
    ) {}
}
