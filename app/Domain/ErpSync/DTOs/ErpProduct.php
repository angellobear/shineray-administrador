<?php

namespace App\Domain\ErpSync\DTOs;

/**
 * Producto ya normalizado desde el feed del ERP (`/api/all_parts`). El cliente
 * del ERP es responsable de: deduplicar por `COD_PRODUCTO` (primera fila gana),
 * quitar el IVA al precio y mapear los niveles de taxonomía a `metadata`.
 */
final readonly class ErpProduct
{
    /**
     * @param  int  $priceCents  Precio SIN IVA en centavos.
     * @param  array<string, mixed>  $metadata  codigo_marca, moto_modelo, nivel_1..4, cod_nivel_1..4, anio_desde/hasta, is_b2b, etc.
     * @param  list<string>  $imageUrls
     */
    public function __construct(
        public string $sku,
        public string $title,
        public int $priceCents,
        public int $stockQuantity,
        public ?string $description = null,
        public ?float $weightKg = null,
        public array $imageUrls = [],
        public array $metadata = [],
    ) {}
}
