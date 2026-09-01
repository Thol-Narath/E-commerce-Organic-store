<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = ucwords(fake()->unique()->words(3, true));
        $price = fake()->randomFloat(2, 1.5, 60);

        return [
            'category_id' => 1,
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(3),
            'short_description' => fake()->sentence(8),
            'sku' => Str::upper(Str::random(8)),
            'barcode' => fake()->numerify('############'),
            'price' => $price,
            'compare_at_price' => fake()->boolean(30) ? $price * 1.25 : null,
            'cost_price' => $price * 0.6,
            'stock_quantity' => fake()->numberBetween(0, 120),
            'low_stock_threshold' => 5,
            'is_featured' => fake()->boolean(25),
            'status' => fake()->randomElement(['active', 'active', 'active', 'inactive', 'draft']),
            'unit' => fake()->randomElement(['kg', 'pcs', 'bunch', 'pack', 'litre']),
            'weight' => fake()->randomFloat(3, 0.1, 10),
            'min_order_qty' => 1,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }
}
