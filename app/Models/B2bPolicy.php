<?php

namespace App\Models;

use Database\Factories\B2bPolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Política de crédito por TIPO de cliente B2B (factor de crédito y cuotas).
 * `client_type` es el `COD_CLIENTEH` del ERP; el alias histórico `DI` se
 * consulta como `DM` (ver `B2bClient::policyType()`).
 *
 * @property int $id
 * @property string $client_type
 * @property bool $is_active
 * @property float $credit_factor
 * @property int $installments
 * @property Carbon|null $erp_synced_at
 */
#[Fillable(['client_type', 'is_active', 'credit_factor', 'installments', 'erp_synced_at'])]
class B2bPolicy extends Model
{
    /** @use HasFactory<B2bPolicyFactory> */
    use HasFactory;

    /**
     * @param  Builder<B2bPolicy>  $query
     * @return Builder<B2bPolicy>
     */
    #[Scope]
    protected function forClientType(Builder $query, string $clientType): Builder
    {
        return $query->where('client_type', B2bClient::normalizePolicyType($clientType));
    }

    /**
     * @param  Builder<B2bPolicy>  $query
     * @return Builder<B2bPolicy>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
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
