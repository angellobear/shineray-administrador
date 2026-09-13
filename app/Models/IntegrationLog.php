<?php

namespace App\Models;

use App\Enums\Integration;
use Database\Factories\IntegrationLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Bitácora de fallos/eventos de integraciones externas. Reemplaza el archivo
 * `logs/payments.jsonl` y cubre también Servientrega y el ERP.
 *
 * @property int $id
 * @property Integration $integration
 * @property string $event
 * @property string $level
 * @property string|null $loggable_type
 * @property int|null $loggable_id
 * @property array<string, mixed>|null $payload
 * @property Carbon|null $created_at
 */
#[Fillable(['integration', 'event', 'level', 'payload', 'created_at'])]
class IntegrationLog extends Model
{
    /** @use HasFactory<IntegrationLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'level' => 'error',
    ];

    /** @return MorphTo<Model, $this> */
    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Atajo para registrar un evento, opcionalmente ligado a un pago/orden/envío.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function record(Integration $integration, string $event, array $payload = [], ?Model $loggable = null, string $level = 'error'): self
    {
        $log = new self([
            'integration' => $integration,
            'event' => $event,
            'level' => $level,
            'payload' => $payload,
        ]);

        if ($loggable !== null) {
            $log->loggable()->associate($loggable);
        }

        $log->save();

        return $log;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'integration' => Integration::class,
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
