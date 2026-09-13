<?php

namespace Tests\Support;

use App\Domain\Shipping\Contracts\ShippingProviderContract;
use App\Domain\Shipping\DTOs\ShippingGuide;
use App\Domain\Shipping\DTOs\ShippingGuideRequest;
use App\Domain\Shipping\DTOs\ShippingQuote;
use App\Domain\Shipping\DTOs\ShippingQuoteRequest;
use App\Domain\Shipping\Exceptions\ShippingProviderException;
use App\Enums\ShippingProvider;

final class FakeShippingProvider implements ShippingProviderContract
{
    public int $quoteCents = 430;

    public bool $quoteIsFallback = false;

    public ?string $guideNumber = '0123456789';

    public bool $guideFails = false;

    /** @var list<ShippingQuoteRequest> */
    public array $quoteRequests = [];

    /** @var list<ShippingGuideRequest> */
    public array $guideRequests = [];

    public function provider(): ShippingProvider
    {
        return ShippingProvider::Servientrega;
    }

    public function quote(ShippingQuoteRequest $request): ShippingQuote
    {
        $this->quoteRequests[] = $request;

        return new ShippingQuote($this->quoteCents, $this->quoteIsFallback);
    }

    public function createGuide(ShippingGuideRequest $request): ShippingGuide
    {
        $this->guideRequests[] = $request;

        if ($this->guideFails || $this->guideNumber === null) {
            throw new ShippingProviderException('Servientrega caído');
        }

        return new ShippingGuide($this->guideNumber, raw: ['id' => $this->guideNumber]);
    }

    public function cancelGuide(string $guideNumber): void {}
}
