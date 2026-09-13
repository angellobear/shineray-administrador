<?php

namespace App\Models;

use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property string $title
 * @property int $price Centavos USD sin IVA.
 * @property int $inventory_quantity
 * @property bool $allow_backorder
 * @property bool $manage_inventory
 * @property float|null $weight_kg
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product|null $product  Null si el producto fue borrado (soft delete).
 */
#[Fillable(['product_id', 'sku', 'title', 'price', 'inventory_quantity', 'allow_backorder', 'manage_inventory', 'weight_kg', 'metadata'])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    /**
     * REGLA DE NEGOCIO (no es un descuido): el stock real lo controla el ERP
     * Shineray/Massline, no esta base. Si el checkout bloqueara por stock
     * local, podría rechazar una orden DESPUÉS de que la pasarela cobró y el
     * ERP facturó. Por eso `allow_backorder` es true por defecto y el checkout
     * nunca valida `inventory_quantity`. Ver modulos/01-productos-catalogo.md.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'inventory_quantity' => 0,
        'allow_backorder' => true,
        'manage_inventory' => false,
    ];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function hasStock(): bool
    {
        return $this->inventory_quantity > 0;
    }

    public function weightOrDefault(): float
    {
        return $this->weight_kg ?? (float) config('shineray.shipping.default_variant_weight_kg');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'inventory_quantity' => 'integer',
            'allow_backorder' => 'boolean',
            'manage_inventory' => 'boolean',
            'weight_kg' => 'float',
            'metadata' => 'array',
        ];
    }
}
