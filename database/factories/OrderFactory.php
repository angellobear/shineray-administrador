<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(1000, 100000);
        $tax = (int) round($subtotal * 0.15);
        $shipping = 430;

        return [
            'order_number' => 'SH-'.fake()->unique()->numerify('########'),
            'cart_id' => null,
            'customer_id' => Customer::factory(),
            'email' => fake()->safeEmail(),
            'status' => OrderStatus::Pending,
            'subtotal' => $subtotal,
            'shipping_total' => $shipping,
            'tax_total' => $tax,
            'discount_total' => 0,
            'total' => $subtotal + $tax + $shipping,
            'metadata' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function b2b(): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => ['is_b2b' => true],
        ]);
    }
}
