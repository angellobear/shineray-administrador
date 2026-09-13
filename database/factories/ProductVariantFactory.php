<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-????-#####')),
            'title' => 'Default',
            'price' => fake()->numberBetween(100, 50000),
            'inventory_quantity' => fake()->numberBetween(0, 50),
            'allow_backorder' => true,
            'manage_inventory' => false,
            'weight_kg' => fake()->randomFloat(3, 0.1, 10),
            'metadata' => null,
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'inventory_quantity' => 0,
        ]);
    }
}
