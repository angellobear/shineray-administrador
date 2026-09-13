<?php

namespace App\Http\Resources;

use App\Models\Cart;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Cart
 */
class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'email' => $this->email,
            'status' => $this->status->value,
            'items' => $this->items->map(fn ($item): array => [
                'id' => $item->id,
                'product_variant_id' => $item->product_variant_id,
                'sku' => $item->variant->sku,
                'title' => $item->variant->product?->title,
                'thumbnail' => $item->variant->product?->thumbnail,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'line_total' => $item->lineTotal(),
            ])->values(),
            'discounts' => $this->discounts->map(fn ($discount): array => [
                'code' => $discount->code,
                'type' => $discount->type->value,
                'value' => $discount->value,
            ])->values(),
            'shipping_address' => $this->whenLoaded('shippingAddress', fn () => $this->shippingAddress === null ? null : [
                'first_name' => $this->shippingAddress->first_name,
                'last_name' => $this->shippingAddress->last_name,
                'phone' => $this->shippingAddress->phone,
                'address_1' => $this->shippingAddress->address_1,
                'city' => $this->shippingAddress->city,
                'province' => $this->shippingAddress->province,
                'country_code' => $this->shippingAddress->country_code,
                'postal_code' => $this->shippingAddress->postal_code,
                'metadata' => $this->shippingAddress->metadata ?? [],
            ]),
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'shipping_total' => $this->shipping_total,
            'shipping_quote' => $this->metadata['shipping_quote_cents'] ?? null,
            'tax_total' => $this->tax_total,
            'total' => $this->total,
            'item_count' => $this->itemCount(),
            'updated_at' => $this->updated_at,
        ];
    }
}
