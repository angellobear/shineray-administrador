<?php

namespace App\Models;

use App\Enums\ShipmentStatus;
use App\Enums\ShippingProvider;
use Database\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int $order_id
 * @property ShippingProvider $provider
 * @property string|null $guide_number
 * @property ShipmentStatus $status
 * @property int|null $cost
 * @property float|null $weight_kg
 * @property array<string, mixed>|null $raw_request
 * @property array<string, mixed>|null $raw_response
 * @property-read Order $order
 */
#[Fillable(['order_id', 'provider', 'guide_number', 'status', 'cost', 'weight_kg', 'raw_request', 'raw_response'])]
class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return MorphMany<IntegrationLog, $this> */
    public function integrationLogs(): MorphMany
    {
        return $this->morphMany(IntegrationLog::class, 'loggable');
    }

    public function hasGuide(): bool
    {
        return $this->guide_number !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => ShippingProvider::class,
            'status' => ShipmentStatus::class,
            'cost' => 'integer',
            'weight_kg' => 'float',
            'raw_request' => 'array',
            'raw_response' => 'array',
        ];
    }
}
