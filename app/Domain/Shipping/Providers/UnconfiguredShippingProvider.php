<?php

namespace App\Domain\Shipping\Providers;

use App\Domain\Shared\Exceptions\IntegrationNotImplementedException;
use App\Domain\Shipping\Contracts\ShippingProviderContract;
use App\Domain\Shipping\DTOs\ShippingGuide;
use App\Domain\Shipping\DTOs\ShippingGuideRequest;
use App\Domain\Shipping\DTOs\ShippingQuote;
use App\Domain\Shipping\DTOs\ShippingQuoteRequest;
use App\Enums\ShippingProvider;

/** Stub de la Fase 0. */
final class UnconfiguredShippingProvider implements ShippingProviderContract
{
    public function provider(): ShippingProvider
    {
        return ShippingProvider::Servientrega;
    }

    public function quote(ShippingQuoteRequest $request): ShippingQuote
    {
        throw IntegrationNotImplementedException::for(ShippingProviderContract::class, __FUNCTION__);
    }

    public function createGuide(ShippingGuideRequest $request): ShippingGuide
    {
        throw IntegrationNotImplementedException::for(ShippingProviderContract::class, __FUNCTION__);
    }

    public function cancelGuide(string $guideNumber): void
    {
        throw IntegrationNotImplementedException::for(ShippingProviderContract::class, __FUNCTION__);
    }
}
