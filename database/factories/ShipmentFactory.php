<?php

namespace Database\Factories;

use App\Enums\ShipmentStatus;
use App\Enums\ShippingProvider;
use App\Models\Order;
use App\Models\Shipment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shipment>
 */
class ShipmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => ShippingProvider::Servientrega,
            'guide_number' => null,
            'status' => ShipmentStatus::Pending,
            'cost' => null,
            'weight_kg' => fake()->randomFloat(3, 2, 20),
            'raw_request' => null,
            'raw_response' => null,
        ];
    }

    public function created(): static
    {
        return $this->state(fn (array $attributes) => [
            'guide_number' => fake()->numerify('##########'),
            'status' => ShipmentStatus::Created,
            'cost' => fake()->numberBetween(300, 2000),
        ]);
    }
}
