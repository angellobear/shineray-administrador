<?php

namespace Database\Factories;

use App\Models\B2bClient;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<B2bClient>
 */
class B2bClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => null,
            'id_client' => fake()->unique()->numerify('#############'),
            'type_client' => 'DISTRIBUIDOR',
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone_number' => fake()->numerify('09########'),
            'address' => ['city' => 'GUAYAQUIL', 'address' => fake()->streetAddress()],
            'active' => true,
            'erp_synced_at' => now(),
        ];
    }

    public function forCustomer(?Customer $customer = null): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => $customer->id ?? Customer::factory()->b2b(),
        ]);
    }
}
