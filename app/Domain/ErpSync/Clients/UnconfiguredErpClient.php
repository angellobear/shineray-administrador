<?php

namespace App\Domain\ErpSync\Clients;

use App\Domain\ErpSync\Contracts\ErpClientContract;
use App\Domain\ErpSync\DTOs\ClientInfo;
use App\Domain\ErpSync\DTOs\InvoicePayload;
use App\Domain\ErpSync\DTOs\InvoiceResult;
use App\Domain\ErpSync\DTOs\StockResult;
use App\Domain\Shared\Exceptions\IntegrationNotImplementedException;
use Illuminate\Support\Collection;

/** Stub de la Fase 0. */
final class UnconfiguredErpClient implements ErpClientContract
{
    public function fetchProducts(): Collection
    {
        throw IntegrationNotImplementedException::for(ErpClientContract::class, __FUNCTION__);
    }

    public function fetchClientsB2b(): Collection
    {
        throw IntegrationNotImplementedException::for(ErpClientContract::class, __FUNCTION__);
    }

    public function fetchPoliciesB2b(): Collection
    {
        throw IntegrationNotImplementedException::for(ErpClientContract::class, __FUNCTION__);
    }

    public function fetchTransportistas(): Collection
    {
        throw IntegrationNotImplementedException::for(ErpClientContract::class, __FUNCTION__);
    }

    public function checkStock(string $sku): StockResult
    {
        throw IntegrationNotImplementedException::for(ErpClientContract::class, __FUNCTION__);
    }

    public function getInfoClient(string $idClient): ?ClientInfo
    {
        throw IntegrationNotImplementedException::for(ErpClientContract::class, __FUNCTION__);
    }

    public function saveInfoClient(ClientInfo $client): void
    {
        throw IntegrationNotImplementedException::for(ErpClientContract::class, __FUNCTION__);
    }

    public function saveInvoice(InvoicePayload $payload): InvoiceResult
    {
        throw IntegrationNotImplementedException::for(ErpClientContract::class, __FUNCTION__);
    }
}
