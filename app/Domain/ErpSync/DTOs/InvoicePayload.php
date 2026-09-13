<?php

namespace App\Domain\ErpSync\DTOs;

use App\Enums\PaymentGateway;

/**
 * Factura a registrar en el ERP tras un pago autorizado. Refleja el payload
 * que hoy arman los payment processors (`paymentData`), en unidades de dominio
 * (centavos, enums); el cliente del ERP lo traduce a strings con 2 decimales.
 */
final readonly class InvoicePayload
{
    /**
     * @param  list<array{sku: string, line_total_cents: int, quantity: int}>  $items
     * @param  array{cardType: string, bin: string, last4Digits: string, holder: string, expiryMonth: string, expiryYear: string}|null  $card
     */
    public function __construct(
        public PaymentGateway $gateway,
        public string $transactionId,
        public ClientInfo $client,
        public array $items,
        public int $totalCents,
        public int $subtotalCents,
        public int $discountCents = 0,
        public float $discountPercentage = 0.0,
        public int $shippingCostCents = 0,
        public int $shippingDiscountCents = 0,
        public ?string $guideNumber = null,
        public ?string $paymentType = null,
        public ?string $paymentBrand = null,
        public ?array $card = null,
        public ?string $transportistaId = null,
        public ?string $transportistaName = null,
        public ?int $installments = null,
        public string $currency = 'USD',
    ) {}
}
