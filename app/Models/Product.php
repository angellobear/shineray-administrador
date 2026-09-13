<?php

namespace App\Models;

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

    /**
     * Forma del documento en Meilisearch. La Fase 1 completa esto con la
     * taxonomía (nivel 1-4, deriveSubsistema), hasStock, isB2b, etc.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'handle' => $this->handle,
            'thumbnail' => $this->thumbnail,
        ];
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
