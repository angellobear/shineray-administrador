<?php

namespace App\Models;

use Database\Factories\B2bPolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Política de crédito de un cliente B2B (factor de crédito y cuotas).
 *
 * @property int $id
 * @property int $b2b_client_id
 * @property bool $is_active
 * @property float $credit_factor
 * @property int $installments
 * @property Carbon|null $erp_synced_at
 * @property-read B2bClient $client
 */
#[Fillable(['b2b_client_id', 'is_active', 'credit_factor', 'installments', 'erp_synced_at'])]
class B2bPolicy extends Model
{
    /** @use HasFactory<B2bPolicyFactory> */
    use HasFactory;

    /** @return BelongsTo<B2bClient, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(B2bClient::class, 'b2b_client_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'credit_factor' => 'float',
            'installments' => 'integer',
            'erp_synced_at' => 'datetime',
        ];
    }
}
