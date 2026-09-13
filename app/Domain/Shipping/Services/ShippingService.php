<?php

namespace App\Domain\Shipping\Services;

use App\Domain\Cart\Exceptions\CartException;
use App\Domain\Cart\Services\CartService;
use App\Domain\Shipping\Contracts\ShippingProviderContract;
use App\Domain\Shipping\DTOs\ShippingGuideRequest;
use App\Domain\Shipping\DTOs\ShippingQuote;
use App\Domain\Shipping\DTOs\ShippingQuoteRequest;
use App\Domain\Shipping\Exceptions\ShippingProviderException;
use App\Enums\ShipmentStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Support\TaxCalculator;

/**
 * Traduce carritos/órdenes a los DTOs del proveedor de envío y guarda el
 * resultado. El proveedor nunca ve un `Cart` ni una `Order`.
 */
final readonly class ShippingService
{
    public function __construct(
        private ShippingProviderContract $provider,
        private CartService $carts,
        private TaxCalculator $tax,
    ) {}

    /**
     * Cotiza y deja el costo (sin IVA) en el carrito. El cotizador devuelve el
     * flete sin IVA; hoy el checkout lo cobra con IVA, que aquí lo agrega
     * `CartTotals` de forma uniforme.
     */
    public function quoteForCart(Cart $cart): ShippingQuote
    {
        $cart->loadMissing(['items.variant', 'shippingAddress']);
        $address = $cart->shippingAddress;

        if ($address === null) {
            throw new CartException('El carrito no tiene dirección de envío.');
        }

        $quote = $this->provider->quote(new ShippingQuoteRequest(
            totalWeightKg: $this->totalWeight($cart->items),
            destinationCity: $address->city,
            destinationProvince: $address->province,
            declaredValueCents: $this->tax->grossFromNet($cart->subtotal - $cart->discount_total),
            destinationCityId: isset($address->metadata['city_id']) ? (string) $address->metadata['city_id'] : null,
        ));

        $this->carts->setShippingQuote($cart, $quote->costCents);

        return $quote;
    }

    /**
     * Crea la guía de una orden pagada y la registra en `shipments`. Ante un
     * fallo deja el envío en `failed` con la respuesta y relanza para que el
     * job decida los reintentos.
     */
    public function createGuideForOrder(Order $order): Shipment
    {
        $order->loadMissing(['items', 'shippingAddress', 'shipments']);
        $address = $order->shippingAddress;

        $existing = $order->shipments->first(fn (Shipment $shipment): bool => $shipment->hasGuide());

        if ($existing instanceof Shipment) {
            return $existing;
        }

        $shipment = $order->shipments->firstWhere('status', ShipmentStatus::Pending)
            ?? $order->shipments()->create(['provider' => $this->provider->provider(), 'status' => ShipmentStatus::Pending]);

        if ($address === null) {
            $shipment->update(['status' => ShipmentStatus::Failed, 'raw_response' => ['error' => 'Orden sin dirección de envío']]);

            throw new ShippingProviderException('La orden no tiene dirección de envío.');
        }

        $weight = $this->totalWeight($order->items);

        $request = new ShippingGuideRequest(
            orderNumber: $order->order_number,
            recipientName: $address->first_name,
            recipientLastName: $address->last_name,
            recipientDni: (string) ($address->metadata['dni'] ?? $order->metadata['dni'] ?? ''),
            recipientPhone: (string) $address->phone,
            recipientAddress: $address->address_1,
            destinationCity: $address->city,
            destinationProvince: $address->province,
            totalWeightKg: $weight,
            declaredValueCents: $order->total,
            items: array_values($order->items->map(fn (OrderItem $item): array => [
                'description' => (string) $item->title,
                'quantity' => (int) $item->quantity,
                'weight_kg' => $this->itemWeight($item),
            ])->all()),
            destinationCityId: isset($address->metadata['city_id']) ? (string) $address->metadata['city_id'] : (isset($order->metadata['city_id']) ? (string) $order->metadata['city_id'] : null),
            recipientEmail: $order->email,
        );

        $shipment->update(['weight_kg' => $weight, 'raw_request' => (array) $request]);

        try {
            $guide = $this->provider->createGuide($request);
        } catch (\Throwable $exception) {
            $shipment->update(['status' => ShipmentStatus::Failed, 'raw_response' => ['error' => $exception->getMessage()]]);

            throw $exception;
        }

        $shipment->update([
            'guide_number' => $guide->guideNumber,
            'status' => ShipmentStatus::Created,
            'cost' => $guide->costCents,
            'raw_response' => $guide->raw,
        ]);

        $address->update(['metadata' => array_merge($address->metadata ?? [], ['servientrega_guide_id' => $guide->guideNumber])]);

        return $shipment;
    }

    /**
     * Σ (peso de la variante o 1 kg por defecto) × cantidad. El mínimo de 2 kg
     * lo aplica el proveedor.
     *
     * @param  iterable<int, CartItem|OrderItem>  $items
     */
    private function totalWeight(iterable $items): float
    {
        $total = 0.0;

        foreach ($items as $item) {
            $total += $this->itemWeight($item) * $item->quantity;
        }

        return $total;
    }

    private function itemWeight(CartItem|OrderItem $item): float
    {
        return $item->variant?->weightOrDefault() ?? (float) config('shineray.shipping.default_variant_weight_kg');
    }
}
