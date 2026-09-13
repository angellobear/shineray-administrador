<?php

namespace App\Domain\ErpSync\Contracts;

use App\Domain\ErpSync\DTOs\ClientInfo;
use App\Domain\ErpSync\DTOs\ErpClientB2b;
use App\Domain\ErpSync\DTOs\ErpPolicyB2b;
use App\Domain\ErpSync\DTOs\ErpProduct;
use App\Domain\ErpSync\DTOs\ErpTransportistaB2b;
use App\Domain\ErpSync\DTOs\InvoicePayload;
use App\Domain\ErpSync\DTOs\InvoiceResult;
use App\Domain\ErpSync\DTOs\StockResult;
use Illuminate\Support\Collection;

/**
 * Único cliente del ERP Shineray/Massline. Unifica catálogo, B2B y
 * facturación (hoy repartidos en tres archivos con lógica de token duplicada).
 * El manejo de token/autenticación vive dentro de la implementación.
 */
interface ErpClientContract
{
    /** @return Collection<int, ErpProduct> Ya deduplicado por SKU. */
    public function fetchProducts(): Collection;

    /** @return Collection<int, ErpClientB2b> */
    public function fetchClientsB2b(): Collection;

    /** @return Collection<int, ErpPolicyB2b> */
    public function fetchPoliciesB2b(): Collection;

    /** @return Collection<int, ErpTransportistaB2b> */
    public function fetchTransportistas(): Collection;

    public function checkStock(string $sku): StockResult;

    public function getInfoClient(string $idClient): ?ClientInfo;

    public function saveInfoClient(ClientInfo $client): void;

    public function saveInvoice(InvoicePayload $payload): InvoiceResult;
}
