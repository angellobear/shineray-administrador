<?php

namespace App\Models;

use Database\Factories\B2bClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Cliente B2B tal como lo conoce el ERP. Se enlaza a `Customer` por FK real.
 *
 * @property int $id
 * @property int|null $customer_id
 * @property string $id_client
 * @property string|null $type_client
 * @property string $first_name
 * @property string $last_name
 * @property string|null $email
 * @property string|null $phone_number
 * @property array<string, mixed>|null $address
 * @property bool $active
 * @property Carbon|null $erp_synced_at
 * @property-read Customer|null $customer
 * @property-read B2bPolicy|null $policy
 */
#[Fillable(['customer_id', 'id_client', 'type_client', 'first_name', 'last_name', 'email', 'phone_number', 'address', 'active', 'erp_synced_at'])]
class B2bClient extends Model
{
    /** @use HasFactory<B2bClientFactory> */
    use HasFactory;

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasOne<B2bPolicy, $this> */
    public function policy(): HasOne
    {
        return $this->hasOne(B2bPolicy::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'address' => 'array',
            'active' => 'boolean',
            'erp_synced_at' => 'datetime',
        ];
    }
}
