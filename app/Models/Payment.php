<?php

namespace App\Models;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property PaymentGateway $gateway
 * @property PaymentStatus $status
 * @property int $amount
 * @property string|null $gateway_reference
 * @property string|null $response_code
 * @property array<string, mixed>|null $raw_request
 * @property array<string, mixed>|null $raw_response
 * @property Carbon|null $authorized_at
 * @property-read Order $order
 */
#[Fillable(['order_id', 'gateway', 'status', 'amount', 'gateway_reference', 'response_code', 'raw_request', 'raw_response', 'authorized_at'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
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

    public function isSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gateway' => PaymentGateway::class,
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'raw_request' => 'array',
            'raw_response' => 'array',
            'authorized_at' => 'datetime',
        ];
    }
}
