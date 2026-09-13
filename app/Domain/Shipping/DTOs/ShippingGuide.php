<?php

namespace App\Domain\Shipping\DTOs;

final readonly class ShippingGuide
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $guideNumber,
        public ?int $costCents = null,
        public ?string $labelUrl = null,
        public array $raw = [],
    ) {}
}
