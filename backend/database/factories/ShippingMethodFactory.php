<?php

namespace Database\Factories;

use App\Models\ShippingMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShippingMethod>
 */
class ShippingMethodFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Standard Shipping', 'Express Shipping', 'Free Store Pickup', 'Same-Day Delivery']),
            'code' => fake()->unique()->lexify('ship-??????'),
            'description' => fake()->sentence(),
            'base_rate' => fake()->randomFloat(2, 0, 20),
            'free_over' => fake()->boolean() ? fake()->randomFloat(2, 30, 200) : null,
            'estimated_days' => fake()->numberBetween(1, 14),
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 0,
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}