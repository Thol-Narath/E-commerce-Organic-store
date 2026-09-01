<?php

namespace Database\Factories;

use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => 1,
            'product_id' => 1,
            'product_name' => fake()->words(2, true),
            'product_sku' => strtoupper(fake()->bothify('??######')),
            'unit_price' => fake()->randomFloat(2, 2, 30),
            'quantity' => fake()->numberBetween(1, 4),
            'line_total' => 0.00,
        ];
    }
}
