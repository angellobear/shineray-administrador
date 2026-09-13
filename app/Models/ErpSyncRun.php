<?php

namespace App\Models;

use App\Enums\ErpSyncRunStatus;
use App\Enums\ErpSyncType;
use Database\Factories\ErpSyncRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * @property int $id
 * @property ErpSyncType $type
 * @property ErpSyncRunStatus $status
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property int $fetched_count
 * @property int $created_count
 * @property int $updated_count
 * @property int $unpublished_count
 * @property string|null $error
 * @property array<string, mixed>|null $metadata
 */
#[Fillable(['type', 'status', 'started_at', 'finished_at', 'fetched_count', 'created_count', 'updated_count', 'unpublished_count', 'error', 'metadata'])]
class ErpSyncRun extends Model
{
    /** @use HasFactory<ErpSyncRunFactory> */
    use HasFactory;

    public static function start(ErpSyncType $type): self
    {
        return self::create([
            'type' => $type,
            'status' => ErpSyncRunStatus::Running,
            'started_at' => now(),
        ]);
    }

    /**
     * @param  array{fetched?: int, created?: int, updated?: int, unpublished?: int}  $counts
     * @param  array<string, mixed>  $metadata
     */
    public function finish(array $counts = [], ErpSyncRunStatus $status = ErpSyncRunStatus::Succeeded, array $metadata = []): self
    {
        $this->fill([
            'status' => $status,
            'finished_at' => now(),
            'fetched_count' => $counts['fetched'] ?? $this->fetched_count,
            'created_count' => $counts['created'] ?? $this->created_count,
            'updated_count' => $counts['updated'] ?? $this->updated_count,
            'unpublished_count' => $counts['unpublished'] ?? $this->unpublished_count,
            'metadata' => $metadata === [] ? $this->metadata : $metadata,
        ])->save();

        return $this;
    }

    public function fail(Throwable $exception): self
    {
        $this->fill([
            'status' => ErpSyncRunStatus::Failed,
            'finished_at' => now(),
            'error' => mb_substr($exception->getMessage(), 0, 2000),
        ])->save();

        return $this;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ErpSyncType::class,
            'status' => ErpSyncRunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'fetched_count' => 'integer',
            'created_count' => 'integer',
            'updated_count' => 'integer',
            'unpublished_count' => 'integer',
            'metadata' => 'array',
        ];
    }
}
