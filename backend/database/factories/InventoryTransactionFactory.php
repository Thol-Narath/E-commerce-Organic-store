<?php

namespace Database\Factories;

use App\Models\InventoryTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryTransaction>
 */
class InventoryTransactionFactory extends Factory
{
    public function definition(): array
    {
        $change = fake()->numberBetween(-20, 50);
        $before = fake()->numberBetween(0, 100);
        $after = max(0, $before + $change);

        return [
            'product_id' => 1,
            'user_id' => null,
            'type' => fake()->randomElement(['purchase', 'sale', 'adjustment', 'return', 'initial']),
            'quantity_change' => $change,
            'stock_before' => $before,
            'stock_after' => $after,
            'reference_type' => null,
            'reference_id' => null,
            'notes' => fake()->sentence(),
        ];
    }
}
