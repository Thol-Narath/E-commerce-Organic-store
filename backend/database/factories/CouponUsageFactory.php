<?php

namespace Database\Factories;

use App\Models\CouponUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CouponUsage>
 */
class CouponUsageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'coupon_id' => 1,
            'user_id' => 1,
            'order_id' => 1,
            'discount_applied' => fake()->randomFloat(2, 2, 25),
        ];
    }
}
