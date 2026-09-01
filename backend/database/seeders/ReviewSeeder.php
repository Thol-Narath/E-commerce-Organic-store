<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Review;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $deliveredOrders = Order::where('status', 'delivered')->with(['items', 'user'])->get();

        $created = [];

        foreach ($deliveredOrders as $order) {
            foreach ($order->items as $item) {
                if ($item->product_id === null) {
                    continue;
                }

                $key = $order->user_id.'-'.$item->product_id;
                if (isset($created[$key])) {
                    continue;
                }

                if (fake()->boolean(70) === false) {
                    continue;
                }

                Review::create([
                    'product_id' => $item->product_id,
                    'user_id' => $order->user_id,
                    'order_id' => $order->id,
                    'rating' => fake()->numberBetween(3, 5),
                    'title' => fake()->sentence(4),
                    'comment' => fake()->paragraph(2),
                    'status' => 'approved',
                ]);

                $created[$key] = true;
            }
        }
    }
}
