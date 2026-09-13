<?php

namespace App\Domain\Shipping\Contracts;

use App\Domain\Shipping\DTOs\ShippingGuide;
use App\Domain\Shipping\DTOs\ShippingGuideRequest;
use App\Domain\Shipping\DTOs\ShippingQuote;
use App\Domain\Shipping\DTOs\ShippingQuoteRequest;
use App\Enums\ShippingProvider;

interface ShippingProviderContract
{
    public function provider(): ShippingProvider;

    /**
     * Cotiza el envío. Si el proveedor falla y se aplica un costo de respaldo,
     * la implementación DEBE registrar el fallo en `integration_logs` antes y
     * devolver `isFallback = true` — nunca tragar la excepción en silencio.
     */
    public function quote(ShippingQuoteRequest $request): ShippingQuote;

    public function createGuide(ShippingGuideRequest $request): ShippingGuide;

    /** Hoy no existe implementación real en Node — decidir si se implementa. */
    public function cancelGuide(string $guideNumber): void;
}
