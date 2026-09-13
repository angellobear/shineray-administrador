<?php

namespace App\Domain\Shipping\DTOs;

/**
 * Datos del destinatario y del paquete para crear una guía. El remitente lo
 * toma el provider de `config('shineray.shipping.sender')`.
 */
final readonly class ShippingGuideRequest
{
    /**
     * @param  list<array{description: string, quantity: int, weight_kg: float}>  $items
     */
    public function __construct(
        public string $orderNumber,
        public string $recipientName,
        public string $recipientDni,
        public string $recipientPhone,
        public string $recipientAddress,
        public string $destinationCity,
        public string $destinationProvince,
        public float $totalWeightKg,
        public int $declaredValueCents,
        public array $items = [],
        public ?string $destinationCityId = null,
        public ?string $recipientEmail = null,
    ) {}
}
