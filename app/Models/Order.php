<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use Carbon\CarbonInterface;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property string $order_number
 * @property int|null $cart_id
 * @property int|null $customer_id
 * @property string $email
 * @property OrderStatus $status
 * @property int $subtotal
 * @property int $shipping_total
 * @property int $tax_total
 * @property int $discount_total
 * @property int $total
 * @property int|null $shipping_address_id
 * @property int|null $billing_address_id
 * @property array<string, mixed>|null $metadata
 * @property CarbonInterface|null $paid_at
 * @property CarbonInterface|null $fulfilled_at
 * @property CarbonInterface|null $canceled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['order_number', 'cart_id', 'customer_id', 'email', 'status', 'subtotal', 'shipping_total', 'tax_total', 'discount_total', 'total', 'shipping_address_id', 'billing_address_id', 'metadata', 'paid_at', 'fulfilled_at', 'canceled_at'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
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

    /** @return BelongsTo<Address, $this> */
    public function billingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'billing_address_id');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** @return HasMany<Shipment, $this> */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    /** @return BelongsToMany<Discount, $this> */
    public function discounts(): BelongsToMany
    {
        return $this->belongsToMany(Discount::class);
    }

    /** @return MorphMany<IntegrationLog, $this> */
    public function integrationLogs(): MorphMany
    {
        return $this->morphMany(IntegrationLog::class, 'loggable');
    }

    /**
     * Única puerta de cambio de estado: valida la transición y fija el
     * timestamp correspondiente. No persiste; el llamador decide cuándo guardar.
     *
     * @throws LogicException si la transición no está permitida.
     */
    public function transitionTo(OrderStatus $target): static
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new LogicException(sprintf(
                'La orden %s no puede pasar de "%s" a "%s".',
                $this->order_number,
                $this->status->value,
                $target->value,
            ));
        }

        $this->status = $target;

        match ($target) {
            OrderStatus::Paid => $this->paid_at ??= now(),
            OrderStatus::Fulfilled => $this->fulfilled_at ??= now(),
            OrderStatus::Canceled => $this->canceled_at ??= now(),
            default => null,
        };

        return $this;
    }

    public function latestPayment(): ?Payment
    {
        return $this->payments()->latest('id')->first();
    }

    public function gateway(): ?PaymentGateway
    {
        $value = $this->metadata['gateway'] ?? null;

        return is_string($value) ? PaymentGateway::tryFrom($value) : null;
    }

    public function isB2b(): bool
    {
        return (bool) ($this->metadata['is_b2b'] ?? false);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => 'integer',
            'shipping_total' => 'integer',
            'tax_total' => 'integer',
            'discount_total' => 'integer',
            'total' => 'integer',
            'metadata' => 'array',
            'paid_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }
}
