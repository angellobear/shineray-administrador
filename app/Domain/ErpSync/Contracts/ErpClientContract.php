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
 * facturación (hoy repartidos en cuatro archivos con lógica de token
 * duplicada). El manejo de token/autenticación vive dentro de la implementación.
 */
interface ErpClientContract
{
    /**
     * Catálogo completo, ya deduplicado por SKU (primera fila gana).
     *
     * @param  bool  $withStock  Consulta stock por SKU (en lotes). El sync de imágenes lo omite.
     * @return Collection<int, ErpProduct>
     */
    public function fetchProducts(bool $withStock = true): Collection;

    /** @return Collection<int, ErpClientB2b> */
    public function fetchClientsB2b(): Collection;

    /** @return Collection<int, ErpPolicyB2b> Una entrada por (cliente, número de cuotas). */
    public function fetchPoliciesB2b(): Collection;

    /** @return Collection<int, ErpTransportistaB2b> */
    public function fetchTransportistas(): Collection;

    public function checkStock(string $sku): StockResult;

    /**
     * @param  list<string>  $skus
     * @return array<string, StockResult> Indexado por SKU.
     */
    public function checkStockMany(array $skus): array;

    /** `null` cuando el ERP responde `estado = "NO REGISTRADO"`. */
    public function getInfoClient(int $idType, string $id): ?ClientInfo;

    public function saveInfoClient(ClientInfo $client): void;

    public function saveInvoice(InvoicePayload $payload): InvoiceResult;

    /**
     * SKUs recomendados para un cliente B2B (`parts_ecommerce_recomended_b2b`).
     *
     * @return list<string>
     */
    public function fetchRecommendedSkusB2b(string $ruc): array;

    /**
     * Deuda/pedidos a crédito de un cliente B2B (`get_client_orders_ecommerce`).
     *
     * @return array<string, mixed>
     */
    public function fetchCreditDebt(string $ruc): array;
}
