<?php

namespace Tests\Support;

use App\Domain\ErpSync\Contracts\ErpClientContract;
use App\Domain\ErpSync\DTOs\ClientInfo;
use App\Domain\ErpSync\DTOs\ErpClientB2b;
use App\Domain\ErpSync\DTOs\ErpPolicyB2b;
use App\Domain\ErpSync\DTOs\ErpProduct;
use App\Domain\ErpSync\DTOs\ErpTransportistaB2b;
use App\Domain\ErpSync\DTOs\InvoicePayload;
use App\Domain\ErpSync\DTOs\InvoiceResult;
use App\Domain\ErpSync\DTOs\StockResult;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Implementación en memoria del contrato del ERP para tests de jobs y
 * servicios: devuelve lo que se le configure y registra lo que se le pidió.
 */
final class FakeErpClient implements ErpClientContract
{
    /** @var Collection<int, ErpProduct> */
    public Collection $products;

    /** @var Collection<int, ErpClientB2b> */
    public Collection $clientsB2b;

    /** @var Collection<int, ErpPolicyB2b> */
    public Collection $policiesB2b;

    /** @var Collection<int, ErpTransportistaB2b> */
    public Collection $transportistas;

    /** @var array<string, StockResult> */
    public array $stock = [];

    /** @var array<string, ClientInfo> */
    public array $clients = [];

    /** @var list<ClientInfo> */
    public array $savedClients = [];

    /** @var list<InvoicePayload> */
    public array $savedInvoices = [];

    /** @var array<string, list<string>> */
    public array $recommended = [];

    /** @var array<string, array<string, mixed>> */
    public array $debts = [];

    public ?RuntimeException $failWith = null;

    public bool $invoiceFails = false;

    public function __construct()
    {
        $this->products = collect();
        $this->clientsB2b = collect();
        $this->policiesB2b = collect();
        $this->transportistas = collect();
    }

    public function fetchProducts(bool $withStock = true): Collection
    {
        $this->failIfConfigured();

        return $this->products;
    }

    public function fetchClientsB2b(): Collection
    {
        $this->failIfConfigured();

        return $this->clientsB2b;
    }

    public function fetchPoliciesB2b(): Collection
    {
        $this->failIfConfigured();

        return $this->policiesB2b;
    }

    public function fetchTransportistas(): Collection
    {
        $this->failIfConfigured();

        return $this->transportistas;
    }

    public function checkStock(string $sku): StockResult
    {
        $this->failIfConfigured();

        return $this->stock[$sku] ?? StockResult::unavailable($sku);
    }

    public function checkStockMany(array $skus): array
    {
        $this->failIfConfigured();

        return array_intersect_key($this->stock, array_flip($skus));
    }

    public function getInfoClient(int $idType, string $id): ?ClientInfo
    {
        $this->failIfConfigured();

        return $this->clients[$id] ?? null;
    }

    public function saveInfoClient(ClientInfo $client): void
    {
        $this->failIfConfigured();
        $this->savedClients[] = $client;
        $this->clients[$client->id] = $client;
    }

    public function saveInvoice(InvoicePayload $payload): InvoiceResult
    {
        $this->failIfConfigured();

        if ($this->invoiceFails) {
            throw new RuntimeException('ERP invoice failed');
        }

        $this->savedInvoices[] = $payload;

        return new InvoiceResult(true, 'FAC-'.count($this->savedInvoices));
    }

    public function fetchRecommendedSkusB2b(string $ruc): array
    {
        $this->failIfConfigured();

        return $this->recommended[$ruc] ?? [];
    }

    public function fetchCreditDebt(string $ruc): array
    {
        $this->failIfConfigured();

        return $this->debts[$ruc] ?? [];
    }

    private function failIfConfigured(): void
    {
        if ($this->failWith !== null) {
            throw $this->failWith;
        }
    }
}
