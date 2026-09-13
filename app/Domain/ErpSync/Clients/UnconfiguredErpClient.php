<?php

namespace App\Domain\ErpSync\Clients;

use App\Domain\ErpSync\Contracts\ErpClientContract;
use App\Domain\ErpSync\DTOs\ClientInfo;
use App\Domain\ErpSync\DTOs\InvoicePayload;
use App\Domain\ErpSync\DTOs\InvoiceResult;
use App\Domain\ErpSync\DTOs\StockResult;
use App\Domain\Shared\Exceptions\IntegrationNotImplementedException;
use Illuminate\Support\Collection;

/** Stub que falla ruidoso; útil para entornos sin credenciales del ERP. */
final class UnconfiguredErpClient implements ErpClientContract
{
    public function fetchProducts(bool $withStock = true): Collection
    {
        throw $this->fail(__FUNCTION__);
    }

    public function fetchClientsB2b(): Collection
    {
        throw $this->fail(__FUNCTION__);
    }

    public function fetchPoliciesB2b(): Collection
    {
        throw $this->fail(__FUNCTION__);
    }

    public function fetchTransportistas(): Collection
    {
        throw $this->fail(__FUNCTION__);
    }

    public function checkStock(string $sku): StockResult
    {
        throw $this->fail(__FUNCTION__);
    }

    public function checkStockMany(array $skus): array
    {
        throw $this->fail(__FUNCTION__);
    }

    public function getInfoClient(int $idType, string $id): ?ClientInfo
    {
        throw $this->fail(__FUNCTION__);
    }

    public function saveInfoClient(ClientInfo $client): void
    {
        throw $this->fail(__FUNCTION__);
    }

    public function saveInvoice(InvoicePayload $payload): InvoiceResult
    {
        throw $this->fail(__FUNCTION__);
    }

    public function fetchRecommendedSkusB2b(string $ruc): array
    {
        throw $this->fail(__FUNCTION__);
    }

    public function fetchCreditDebt(string $ruc): array
    {
        throw $this->fail(__FUNCTION__);
    }

    private function fail(string $method): IntegrationNotImplementedException
    {
        return IntegrationNotImplementedException::for(ErpClientContract::class, $method);
    }
}
