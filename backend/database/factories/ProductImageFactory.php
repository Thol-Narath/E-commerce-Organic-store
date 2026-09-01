<?php

namespace Database\Factories;

use App\Models\ProductImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductImage>
 */
class ProductImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => 1,
            'image' => 'images/products/'.fake()->randomElement(['apple.png', 'tomato.png', 'greens.png', 'bread.png', 'milk.png']),
            'alt_text' => fake()->words(4, true),
            'sort_order' => 0,
            'is_primary' => false,
        ];
    }
}
