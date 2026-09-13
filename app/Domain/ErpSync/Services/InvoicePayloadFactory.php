<?php

namespace App\Domain\ErpSync\Services;

use App\Domain\ErpSync\DTOs\ClientInfo;
use App\Domain\ErpSync\DTOs\InvoicePayload;
use App\Enums\DiscountType;
use App\Enums\PaymentGateway;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;

/**
 * Traduce una orden pagada al `paymentData` que espera el ERP. Replica las
 * reglas de los payment processors: envío gratis se reporta como costo $4.30 y
 * descuento $4.30 en B2C (0 en crédito B2B), porcentaje/monto de descuento,
 * datos de tarjeta desde la respuesta de Datafast.
 */
final class InvoicePayloadFactory
{
    public function fromOrder(Order $order, Payment $payment, ClientInfo $client): InvoicePayload
    {
        $order->loadMissing(['items', 'discounts', 'shipments']);
        $gateway = $payment->gateway;
        $raw = $payment->raw_response ?? [];

        $freeShipping = $order->discounts->contains(fn ($discount): bool => $discount->type === DiscountType::FreeShipping);
        $percentage = $order->discounts->first(fn ($discount): bool => $discount->type === DiscountType::Percentage);

        $freeShippingCents = $gateway === PaymentGateway::CreditoB2b ? 0 : (int) config('shineray.shipping.free_shipping_cost_cents');
        $shippingCost = $freeShipping ? $freeShippingCents : $order->shipping_total;
        $shippingDiscount = $freeShipping ? $freeShippingCents : 0;

        $card = null;

        if (is_array($raw['card'] ?? null)) {
            $card = [
                'cardType' => (string) ($raw['paymentBrand'] ?? ''),
                'bin' => (string) ($raw['card']['bin'] ?? ''),
                'last4Digits' => (string) ($raw['card']['last4Digits'] ?? ''),
                'holder' => (string) ($raw['card']['holder'] ?? ''),
                'expiryMonth' => (string) ($raw['card']['expiryMonth'] ?? ''),
                'expiryYear' => (string) ($raw['card']['expiryYear'] ?? ''),
            ];
        }

        $guide = $order->shipments->whereNotNull('guide_number')->sortByDesc('id')->first()?->guide_number;

        return new InvoicePayload(
            gateway: $gateway,
            transactionId: (string) ($raw['id'] ?? $payment->gateway_reference ?? $order->order_number),
            client: $client,
            items: array_values($order->items->map(fn (OrderItem $item): array => [
                'sku' => (string) ($item->metadata['cod_producto'] ?? $item->sku),
                'line_total_cents' => (int) $item->lineTotal(),
                'quantity' => (int) $item->quantity,
            ])->all()),
            totalCents: $order->total,
            subtotalCents: $order->subtotal,
            discountCents: $percentage !== null || $order->discounts->contains(fn ($d): bool => $d->type === DiscountType::Fixed) ? $order->discount_total : 0,
            discountPercentage: $percentage !== null ? (float) $percentage->value : 0.0,
            shippingCostCents: $shippingCost,
            shippingDiscountCents: $shippingDiscount,
            guideNumber: $guide,
            paymentType: isset($raw['paymentType']) ? (string) $raw['paymentType'] : null,
            paymentBrand: isset($raw['paymentBrand']) ? (string) $raw['paymentBrand'] : null,
            card: $card,
            transportistaId: isset($order->metadata['transportista_id']) ? (string) $order->metadata['transportista_id'] : null,
            transportistaName: isset($order->metadata['transportista_name']) ? (string) $order->metadata['transportista_name'] : null,
            installments: isset($order->metadata['installments']) ? (int) $order->metadata['installments'] : ($gateway->isB2b() ? 0 : null),
            currency: (string) ($raw['currency'] ?? 'USD'),
        );
    }

    /**
     * Cliente de facturación según el tipo de orden: B2C usa la cédula de la
     * dirección de envío; B2B el RUC y la dirección del cliente B2B.
     */
    public function clientFromOrder(Order $order): ClientInfo
    {
        $order->loadMissing('shippingAddress');
        $address = $order->shippingAddress;
        $metadata = $order->metadata ?? [];

        if ($order->isB2b()) {
            return new ClientInfo(
                idType: 1,
                id: (string) ($metadata['ruc'] ?? $metadata['dni'] ?? $address->metadata['dni'] ?? ''),
                firstName: (string) ($address->first_name ?? ''),
                lastName: (string) ($address->last_name ?? ''),
                address: (string) ($metadata['address'] ?? trim(($address->city ?? '').', '.($address->address_1 ?? ''), ', ')),
                phone: $address?->phone,
                email: $order->email,
            );
        }

        return new ClientInfo(
            idType: (int) ($metadata['dni_type'] ?? $address->metadata['dni_type'] ?? 1),
            id: (string) ($metadata['dni'] ?? $address->metadata['dni'] ?? ''),
            firstName: (string) ($address->first_name ?? ''),
            lastName: (string) ($address->last_name ?? ''),
            address: (string) ($address->address_1 ?? ''),
            phone: $address?->phone,
            email: $order->email,
        );
    }
}
