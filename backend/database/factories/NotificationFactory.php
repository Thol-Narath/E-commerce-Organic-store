<?php

namespace Database\Factories;

use App\Models\Notification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => 1,
            'type' => fake()->randomElement(['order_status', 'stock_alert', 'promotion', 'system']),
            'title' => fake()->sentence(3),
            'message' => fake()->paragraph(1),
            'data' => null,
            'read_at' => fake()->boolean(50) ? now() : null,
        ];
    }
}
