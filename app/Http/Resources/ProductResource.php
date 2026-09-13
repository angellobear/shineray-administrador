<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $variant = $this->variant;
        $searchable = $this->toSearchableArray();
        unset($searchable['id'], $searchable['title'], $searchable['description'], $searchable['thumbnail'], $searchable['handle'], $searchable['status']);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'handle' => $this->handle,
            'thumbnail' => $this->thumbnail,
            'images' => $this->images ?? [],
            'status' => $this->status->value,
            'variant' => $variant === null ? null : [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'price' => $variant->price,
                'inventory_quantity' => $variant->inventory_quantity,
                'has_stock' => $variant->hasStock(),
                'allow_backorder' => $variant->allow_backorder,
                'weight_kg' => $variant->weight_kg,
            ],
            'attributes' => $searchable,
            'metadata' => $this->metadata ?? [],
            'updated_at' => $this->updated_at,
        ];
    }
}
