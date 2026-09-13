<?php

namespace App\Domain\ErpSync\DTOs;

/**
 * Producto ya normalizado desde el feed del ERP (`/api/all_parts`). El cliente
 * del ERP es responsable de deduplicar por `COD_PRODUCTO` (primera fila gana),
 * quitar el IVA al precio y conservar las claves del ERP en `metadata`.
 */
final readonly class ErpProduct
{
    /**
     * @param  int  $priceCents  Precio SIN IVA en centavos.
     * @param  array<string, mixed>  $metadata  Claves en mayúsculas tal como las manda el ERP (COD_PRODUCTO, NIVEL_1...).
     */
    public function __construct(
        public string $sku,
        public string $title,
        public string $handle,
        public int $priceCents,
        public int $stockQuantity,
        public ?string $thumbnail = null,
        public ?string $description = null,
        public ?float $weightKg = null,
        public array $metadata = [],
    ) {}
}
