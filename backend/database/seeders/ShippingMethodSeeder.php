<?php

namespace Database\Seeders;

use App\Models\ShippingMethod;
use Illuminate\Database\Seeder;

class ShippingMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            [
                'name' => 'Standard Shipping',
                'code' => 'standard',
                'description' => 'Delivered within 3–5 business days via standard courier.',
                'base_rate' => 2.00,
                'free_over' => 50.00,
                'estimated_days' => 5,
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Express Shipping',
                'code' => 'express',
                'description' => 'Priority handling, delivered within 1–2 business days.',
                'base_rate' => 8.00,
                'free_over' => 100.00,
                'estimated_days' => 2,
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 2,
            ],
            [
                'name' => 'In-Store Pickup',
                'code' => 'pickup',
                'description' => 'Pick up your order free of charge at our store.',
                'base_rate' => 0.00,
                'free_over' => null,
                'estimated_days' => 1,
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 3,
            ],
        ];

        foreach ($methods as $method) {
            ShippingMethod::updateOrCreate(['code' => $method['code']], $method);
        }
    }
}