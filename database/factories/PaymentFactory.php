<?php

namespace Database\Factories;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'gateway' => PaymentGateway::Datafast,
            'status' => PaymentStatus::Pending,
            'amount' => fake()->numberBetween(1000, 100000),
            'gateway_reference' => fake()->unique()->uuid(),
            'response_code' => null,
            'raw_request' => null,
            'raw_response' => null,
        ];
    }

    public function authorized(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Authorized,
            'response_code' => '000.000.000',
            'authorized_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Failed,
            'response_code' => '800.100.151',
        ]);
    }

    public function gateway(PaymentGateway $gateway): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway' => $gateway,
        ]);
    }
}
