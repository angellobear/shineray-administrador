<?php

namespace Database\Factories;

use App\Enums\CartStatus;
use App\Models\Cart;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
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
            'email' => fake()->safeEmail(),
            'status' => CartStatus::Active,
            'subtotal' => 0,
            'shipping_total' => 0,
            'tax_total' => 0,
            'discount_total' => 0,
            'total' => 0,
            'metadata' => null,
            'last_activity_at' => now(),
        ];
    }

    public function forCustomer(?Customer $customer = null): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => $customer->id ?? Customer::factory(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CartStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function inactiveSince(int $minutes): static
    {
        return $this->state(fn (array $attributes) => [
            'last_activity_at' => now()->subMinutes($minutes),
        ]);
    }
}
