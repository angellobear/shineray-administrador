<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'email' => $this->email,
            'gateway' => $this->gateway()?->value,
            'is_b2b' => $this->isB2b(),
            'items' => $this->items->map(fn ($item): array => [
                'sku' => $item->sku,
                'title' => $item->title,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->lineTotal(),
            ])->values(),
            'shipping_address' => $this->whenLoaded('shippingAddress', fn () => $this->shippingAddress === null ? null : [
                'first_name' => $this->shippingAddress->first_name,
                'last_name' => $this->shippingAddress->last_name,
                'phone' => $this->shippingAddress->phone,
                'address_1' => $this->shippingAddress->address_1,
                'city' => $this->shippingAddress->city,
                'province' => $this->shippingAddress->province,
                'metadata' => $this->shippingAddress->metadata ?? [],
            ]),
            'shipments' => $this->whenLoaded('shipments', fn () => $this->shipments->map(fn ($shipment): array => [
                'provider' => $shipment->provider->value,
                'guide_number' => $shipment->guide_number,
                'status' => $shipment->status->value,
            ])->values()),
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'shipping_total' => $this->shipping_total,
            'tax_total' => $this->tax_total,
            'total' => $this->total,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
        ];
    }
}
