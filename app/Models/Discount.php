<?php

namespace App\Models;

use App\Enums\DiscountType;
use Database\Factories\DiscountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property DiscountType $type
 * @property int $value
 * @property bool $is_active
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property int|null $usage_limit
 * @property int $usage_count
 */
#[Fillable(['code', 'type', 'value', 'is_active', 'starts_at', 'ends_at', 'usage_limit', 'usage_count'])]
class Discount extends Model
{
    /** @use HasFactory<DiscountFactory> */
    use HasFactory;

    /**
     * @param  Builder<Discount>  $query
     * @return Builder<Discount>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function isUsable(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return false;
        }

        return $this->usage_limit === null || $this->usage_count < $this->usage_limit;
    }

    /** Monto a descontar (centavos) sobre un subtotal dado. */
    public function amountFor(int $subtotalCents): int
    {
        return match ($this->type) {
            DiscountType::Percentage => (int) round($subtotalCents * min($this->value, 100) / 100),
            DiscountType::Fixed => min($this->value, $subtotalCents),
            DiscountType::FreeShipping => 0,
        };
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'value' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'usage_limit' => 'integer',
            'usage_count' => 'integer',
        ];
    }
}
