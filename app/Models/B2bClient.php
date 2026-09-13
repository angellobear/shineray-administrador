<?php

namespace App\Models;

use Database\Factories\B2bClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property-read Collection<int, B2bPolicy> $policies
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

    /** Alias histórico del ERP: los clientes "DI" usan las políticas de "DM". */
    private const POLICY_TYPE_ALIASES = ['DI' => 'DM'];

    public static function normalizePolicyType(string $clientType): string
    {
        return self::POLICY_TYPE_ALIASES[$clientType] ?? $clientType;
    }

    /** Tipo bajo el que se buscan las políticas de crédito de este cliente. */
    public function policyType(): ?string
    {
        return $this->type_client === null ? null : self::normalizePolicyType($this->type_client);
    }

    /**
     * Políticas de crédito del tipo de cliente: una por número de cuotas.
     *
     * @return HasMany<B2bPolicy, $this>
     */
    public function policies(): HasMany
    {
        return $this->hasMany(B2bPolicy::class, 'client_type', 'type_client');
    }

    /**
     * Políticas activas resolviendo el alias de tipo (DI → DM).
     *
     * @return Collection<int, B2bPolicy>
     */
    public function activePolicies(): Collection
    {
        $type = $this->policyType();

        return $type === null
            ? new Collection
            : B2bPolicy::query()->forClientType($type)->active()->orderBy('installments')->get();
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
