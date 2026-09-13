<?php

namespace Database\Factories;

use App\Models\B2bTransportista;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<B2bTransportista>
 */
class B2bTransportistaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ruc' => fake()->unique()->numerify('#############'),
            'business_name' => fake()->company(),
            'erp_synced_at' => now(),
        ];
    }
}
