<?php

namespace Database\Factories;

use App\Enums\Integration;
use App\Models\IntegrationLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntegrationLog>
 */
class IntegrationLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'integration' => fake()->randomElement(Integration::cases()),
            'event' => fake()->randomElement(['servientrega_fail', 'invoice_fail', 'quote_fallback']),
            'level' => 'error',
            'payload' => ['message' => fake()->sentence()],
            'created_at' => now(),
        ];
    }
}
