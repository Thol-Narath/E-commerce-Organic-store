<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_number' => 'ORD-'.strtoupper(fake()->unique()->bothify('##########')),
            'user_id' => 1,
            'address_id' => 1,
            'coupon_id' => null,
            'subtotal' => 50.00,
            'discount' => 0.00,
            'shipping_fee' => 5.00,
            'tax' => 4.00,
            'total' => 59.00,
            'status' => fake()->randomElement(['pending', 'processing', 'shipped', 'delivered']),
            'payment_status' => fake()->randomElement(['unpaid', 'paid']),
            'shipping_address_snapshot' => null,
            'notes' => null,
            'placed_at' => now(),
        ];
    }
}
