<?php

namespace App\Domain\ErpSync\DTOs;

use App\Enums\PaymentGateway;

/**
 * Factura a registrar en el ERP tras un pago autorizado.
 */
final readonly class InvoicePayload
{
    /**
     * @param  list<array{sku: string, quantity: int, unit_price_cents: int, title: string}>  $items
     * @param  array<string, mixed>  $metadata  B2B: transportista_id, numero_cuota, cod_client, ruc.
     */
    public function __construct(
        public string $orderNumber,
        public ClientInfo $client,
        public PaymentGateway $gateway,
        public array $items,
        public int $subtotalCents,
        public int $taxCents,
        public int $shippingCents,
        public int $discountCents,
        public int $totalCents,
        public ?string $guideNumber = null,
        public array $metadata = [],
    ) {}
}
