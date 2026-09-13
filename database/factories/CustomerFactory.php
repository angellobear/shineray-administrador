<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->numerify('09########'),
            'dni' => fake()->numerify('##########'),
            'is_b2b' => false,
            'email_verified_at' => now(),
            'metadata' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function b2b(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_b2b' => true,
        ]);
    }

    /** Cliente autocreado desde el ERP que aún no fijó su password. */
    public function withoutPassword(): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => null,
        ]);
    }
}
