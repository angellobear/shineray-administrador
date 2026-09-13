<?php

namespace App\Models;

use App\Domain\Catalog\CatalogTaxonomy;
use App\Enums\ProductStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property string $handle
 * @property string|null $thumbnail
 * @property list<string>|null $images
 * @property ProductStatus $status
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $erp_synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['title', 'description', 'handle', 'thumbnail', 'images', 'status', 'metadata', 'erp_synced_at'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, Searchable, SoftDeletes;

    /** @return HasMany<ProductVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * El catálogo de motopartes es 1:1 producto → variante (sin opciones de
     * color/talla); esta relación es la forma cómoda de llegar a precio/SKU.
     *
     * @return HasOne<ProductVariant, $this>
     */
    public function variant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)->oldestOfMany();
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    #[Scope]
    protected function published(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Published);
    }

    public function isPublished(): bool
    {
        return $this->status === ProductStatus::Published;
    }

    /**
     * Solo los productos publicados existen en el índice de búsqueda
     * (reemplaza el filtro `status !== 'published'` del transformer actual).
     */
    public function shouldBeSearchable(): bool
    {
        return $this->isPublished() && $this->deleted_at === null;
    }

    public function searchableAs(): string
    {
        return 'products';
    }

    /**
     * Forma del documento en Meilisearch: réplica del `transformer` de
     * medusa-plugin-meilisearch para no romper el storefront. Las claves de
     * `metadata` son las del ERP (mayúsculas).
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $metadata = $this->metadata ?? [];
        $variant = $this->relationLoaded('variant') ? $this->variant : $this->variant()->first();

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'title' => $this->title,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail,
            'handle' => $this->handle,
            'price' => $variant?->price,
            'codigoMarca' => $metadata['CODIGO_MARCA'] ?? null,
            'codigoCategoria' => $metadata['CODIGO_CATEGORIA'] ?? null,
            'motoModelo' => $metadata['MOTO_MODELO'] ?? null,
            'codigoSubsistema' => $metadata['CODIGO_SUBSISTEMA'] ?? null,
            'anioDesde' => $metadata['ANIO_DESDE'] ?? null,
            'anioHasta' => $metadata['ANIO_HASTA'] ?? null,
            'codigoProducto' => $metadata['COD_PRODUCTO'] ?? $variant?->sku,
            'nombreMarca' => $metadata['NOMBRE_MARCA'] ?? null,
            'nombreCategoria' => $metadata['NOMBRE_CATEGORIA'] ?? null,
            'codigoModeloMoto' => $metadata['CODIGO_MODELO_MOTO'] ?? null,
            'nombreSubsistema' => CatalogTaxonomy::subsistemaFor($metadata),
            'nivel1' => $metadata['NIVEL_1'] ?? null,
            'codNivel1' => $metadata['COD_NIVEL_1'] ?? null,
            'nivel2' => $metadata['NIVEL_2'] ?? null,
            'codNivel2' => $metadata['COD_NIVEL_2'] ?? null,
            'nivel3' => $metadata['NIVEL_3'] ?? null,
            'codNivel3' => $metadata['COD_NIVEL_3'] ?? null,
            'nivel4' => $metadata['NIVEL_4'] ?? null,
            'codNivel4' => $metadata['COD_NIVEL_4'] ?? null,
            'idItemNS' => $metadata['ID_ITEM_NS'] ?? null,
            'isB2b' => (bool) ($metadata['is_b2b'] ?? false),
            'isLandingPromo' => (bool) ($metadata['IS_PROMO'] ?? false),
            'OldPrice' => $metadata['OLD_PRICE'] ?? null,
            'hasStock' => ($variant->inventory_quantity ?? 0) > 0,
        ];
    }

    /**
     * Scout carga la variante al indexar en lote (`scout:import`, sync).
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->with('variant');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'images' => 'array',
            'metadata' => 'array',
            'erp_synced_at' => 'datetime',
        ];
    }
}
