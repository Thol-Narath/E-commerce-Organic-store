<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();

        $recentOrders = Order::orderByDesc('placed_at')->limit(5)->get();

        foreach ($recentOrders as $order) {
            Notification::create([
                'user_id' => $order->user_id,
                'type' => 'order_status',
                'title' => 'Order '.$order->order_number,
                'message' => "Your order {$order->order_number} is now {$order->status}.",
                'data' => ['order_id' => $order->id],
                'read_at' => null,
            ]);
        }

        Notification::create([
            'user_id' => $admin->id,
            'type' => 'stock_alert',
            'title' => 'Low stock alert',
            'message' => 'Some products have fallen below the low-stock threshold. Check the inventory dashboard.',
            'data' => null,
            'read_at' => null,
        ]);

        Notification::create([
            'user_id' => null,
            'type' => 'system',
            'title' => 'Welcome to Organic Store',
            'message' => 'Thank you for choosing fresh, certified organic produce.',
            'data' => null,
            'read_at' => null,
        ]);
    }
}
