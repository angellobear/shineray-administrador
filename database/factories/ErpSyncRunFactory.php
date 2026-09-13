<?php

namespace Database\Factories;

use App\Enums\ErpSyncRunStatus;
use App\Enums\ErpSyncType;
use App\Models\ErpSyncRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ErpSyncRun>
 */
class ErpSyncRunFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ErpSyncType::Products,
            'status' => ErpSyncRunStatus::Running,
            'started_at' => now(),
            'finished_at' => null,
            'fetched_count' => 0,
            'created_count' => 0,
            'updated_count' => 0,
            'unpublished_count' => 0,
            'error' => null,
            'metadata' => null,
        ];
    }
}
