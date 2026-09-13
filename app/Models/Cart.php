<?php

namespace App\Models;

use App\Enums\CartStatus;
use Carbon\CarbonInterface;
use Database\Factories\CartFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int|null $customer_id
 * @property string|null $email
 * @property CartStatus $status
 * @property int $subtotal
 * @property int $shipping_total
 * @property int $tax_total
 * @property int $discount_total
 * @property int $total
 * @property int|null $shipping_address_id
 * @property array<string, mixed>|null $metadata
 * @property CarbonInterface|null $abandoned_completed_at
 * @property int $abandoned_count
 * @property int|null $abandoned_last_interval
 * @property CarbonInterface|null $abandoned_lastdate
 * @property CarbonInterface|null $last_activity_at
 * @property CarbonInterface|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['customer_id', 'email', 'status', 'subtotal', 'shipping_total', 'tax_total', 'discount_total', 'total', 'shipping_address_id', 'metadata', 'abandoned_completed_at', 'abandoned_count', 'abandoned_last_interval', 'abandoned_lastdate', 'last_activity_at', 'completed_at'])]
class Cart extends Model
{
    /** @use HasFactory<CartFactory> */
    use HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'abandoned_count' => 0,
    ];

    /**
     * `public_id` es el ULID que se expone al storefront; `id` sigue siendo
     * el bigint interno. Las rutas resuelven el carrito por `public_id`.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function isActive(): bool
    {
        return $this->status === CartStatus::Active;
    }

    public function itemCount(): int
    {
        return (int) $this->items->sum('quantity');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<CartItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /** @return MorphMany<Address, $this> */
    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    /** @return BelongsTo<Address, $this> */
    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }

    /** @return BelongsToMany<Discount, $this> */
    public function discounts(): BelongsToMany
    {
        return $this->belongsToMany(Discount::class);
    }

    /**
     * @param  Builder<Cart>  $query
     * @return Builder<Cart>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('status', CartStatus::Active);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CartStatus::class,
            'subtotal' => 'integer',
            'shipping_total' => 'integer',
            'tax_total' => 'integer',
            'discount_total' => 'integer',
            'total' => 'integer',
            'metadata' => 'array',
            'abandoned_completed_at' => 'datetime',
            'abandoned_count' => 'integer',
            'abandoned_last_interval' => 'integer',
            'abandoned_lastdate' => 'datetime',
            'last_activity_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
