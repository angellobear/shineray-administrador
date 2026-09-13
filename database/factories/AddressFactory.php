<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Address>
 */
class AddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'addressable_type' => (new Customer)->getMorphClass(),
            'addressable_id' => Customer::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->numerify('09########'),
            'address_1' => fake()->streetAddress(),
            'address_2' => null,
            'city' => fake()->randomElement(['GUAYAQUIL', 'QUITO', 'CUENCA', 'MANTA']),
            'province' => fake()->randomElement(['GUAYAS', 'PICHINCHA', 'AZUAY', 'MANABI']),
            'country_code' => 'EC',
            'postal_code' => '000000',
            'metadata' => ['dni' => fake()->numerify('##########')],
        ];
    }
}
