<?php

namespace Database\Factories;

use App\Models\B2bPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<B2bPolicy>
 */
class B2bPolicyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_type' => fake()->randomElement(['DM', 'DP', 'MY']),
            'is_active' => true,
            'credit_factor' => fake()->randomFloat(4, 0.5, 2),
            'installments' => fake()->numberBetween(1, 12),
            'erp_synced_at' => now(),
        ];
    }
}
