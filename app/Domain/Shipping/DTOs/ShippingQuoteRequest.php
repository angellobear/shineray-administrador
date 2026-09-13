<?php

namespace App\Domain\Shipping\DTOs;

/**
 * Primitivos necesarios para cotizar un envío. Deliberadamente NO recibe un
 * `Cart` completo: el provider no debe conocer el resto del dominio.
 */
final readonly class ShippingQuoteRequest
{
    public function __construct(
        public float $totalWeightKg,
        public string $destinationCity,
        public string $destinationProvince,
        public int $declaredValueCents,
        public ?string $destinationCityId = null,
    ) {}
}
