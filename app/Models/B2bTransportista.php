<?php

namespace App\Models;

use Database\Factories\B2bTransportistaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ruc
 * @property string $business_name
 * @property Carbon|null $erp_synced_at
 */
#[Fillable(['ruc', 'business_name', 'erp_synced_at'])]
class B2bTransportista extends Model
{
    /** @use HasFactory<B2bTransportistaFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'erp_synced_at' => 'datetime',
        ];
    }
}
