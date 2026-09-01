<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $coupons = [
            ['code' => 'WELCOME10', 'type' => 'percentage', 'value' => 10.00, 'min_order_amount' => 20.00, 'max_discount' => 15.00, 'usage_limit' => 500, 'per_user_limit' => 1, 'used' => 0, 'starts' => now()->subDays(30), 'expires' => now()->addMonths(3), 'status' => 'active'],
            ['code' => 'ORGANIC15', 'type' => 'percentage', 'value' => 15.00, 'min_order_amount' => 40.00, 'max_discount' => 25.00, 'usage_limit' => 300, 'per_user_limit' => 2, 'used' => 0, 'starts' => now()->subDays(10), 'expires' => now()->addMonths(2), 'status' => 'active'],
            ['code' => 'SAVE5', 'type' => 'fixed', 'value' => 5.00, 'min_order_amount' => 15.00, 'max_discount' => null, 'usage_limit' => 1000, 'per_user_limit' => 1, 'used' => 0, 'starts' => now()->subDays(5), 'expires' => now()->addMonth(), 'status' => 'active'],
            ['code' => 'FRESHCART20', 'type' => 'percentage', 'value' => 20.00, 'min_order_amount' => 60.00, 'max_discount' => 30.00, 'usage_limit' => 150, 'per_user_limit' => 1, 'used' => 0, 'starts' => now()->subDay(), 'expires' => now()->addWeeks(2), 'status' => 'active'],
            ['code' => 'EXPIRED10', 'type' => 'percentage', 'value' => 10.00, 'min_order_amount' => 10.00, 'max_discount' => null, 'usage_limit' => 100, 'per_user_limit' => 1, 'used' => 5, 'starts' => now()->subMonths(3), 'expires' => now()->subMonth(), 'status' => 'inactive'],
        ];

        foreach ($coupons as $c) {
            Coupon::create([
                'code' => $c['code'],
                'type' => $c['type'],
                'value' => $c['value'],
                'min_order_amount' => $c['min_order_amount'],
                'max_discount' => $c['max_discount'],
                'usage_limit' => $c['usage_limit'],
                'per_user_limit' => $c['per_user_limit'],
                'used_count' => $c['used'],
                'starts_at' => $c['starts'],
                'expires_at' => $c['expires'],
                'status' => $c['status'],
            ]);
        }
    }
}
