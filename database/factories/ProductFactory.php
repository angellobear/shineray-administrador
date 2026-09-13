<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'title' => Str::title($title),
            'description' => fake()->sentence(),
            'handle' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'thumbnail' => null,
            'images' => [],
            'status' => ProductStatus::Published,
            'metadata' => [
                'cod_producto' => strtoupper(fake()->bothify('??####')),
                'codigo_marca' => 'SHINERAY',
                'nombre_categoria' => fake()->randomElement(['MOTOR', 'FRENOS', 'SUSPENSION', 'ELECTRICO']),
            ],
            'erp_synced_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ProductStatus::Draft,
        ]);
    }

    /** Producto con su única variante creada (patrón 1:1 del catálogo). */
    public function withVariant(): static
    {
        return $this->has(ProductVariant::factory(), 'variants');
    }
}
