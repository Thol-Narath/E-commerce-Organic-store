<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Coupon;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNote;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $customers = User::where('role', 'customer')->get();
        $admin = User::where('role', 'admin')->first();
        $coupons = Coupon::where('status', 'active')->get();
        $taxRate = 0.08;
        $shippingFee = 5.00;

        // Realistic status distribution: delivered is the most common outcome,
        // with a spread across confirmation, processing, shipping and a few
        // cancellations so the admin dashboard has data for every status.
        $statusPool = [
            'pending', 'confirmed', 'processing', 'shipped',
            'delivered', 'delivered', 'delivered', 'cancelled',
        ];

        $orderNumber = 0;

        // @var array<int, array{order: Order, product_ids: array<int>, user_id: int}>
        $created = [];

        foreach ($customers as $customer) {
            $address = Address::where('user_id', $customer->id)->where('is_default', true)->first();
            $products = Product::where('status', 'active')->inRandomOrder()->limit(5)->get();

            // Each customer places between 2 and 4 orders
            foreach (range(1, fake()->numberBetween(2, 4)) as $i) {
                $status = fake()->randomElement($statusPool);

                // The order-level payment status reflects the aggregate state.
                // Cancelled orders may be paid (refund pending) or unpaid.
                $orderPaymentStatus = match ($status) {
                    'pending' => 'unpaid',
                    'cancelled' => fake()->randomElement(['paid', 'unpaid']),
                    default => 'paid',
                };

                $paymentRecordStatus = match ($orderPaymentStatus) {
                    'paid' => 'paid',
                    'unpaid' => $status === 'cancelled' ? 'cancelled' : 'pending',
                };

                // Pick 1-3 distinct items for this order
                $orderProducts = $products->shuffle()->take(fake()->numberBetween(1, 3));
                $subtotal = 0.00;
                $lineItems = [];

                foreach ($orderProducts as $product) {
                    $qty = fake()->numberBetween(1, 3);
                    $lineTotal = round($product->price * $qty, 2);
                    $subtotal += $lineTotal;
                    $lineItems[] = ['product' => $product, 'qty' => $qty, 'line_total' => $lineTotal];
                }

                // Attempt to apply a valid coupon (only for paid orders, deterministic for predictability)
                $coupon = null;
                $discount = 0.00;
                if ($orderPaymentStatus === 'paid' && $i % 2 === 0) {
                    $candidate = $coupons->first(
                        fn ($c) => $c->min_order_amount === null || $subtotal >= $c->min_order_amount
                    );
                    if ($candidate) {
                        $coupon = $candidate;
                        if ($coupon->type === 'fixed') {
                            $discount = min($coupon->value, $subtotal);
                        } else {
                            $discount = round($subtotal * ($coupon->value / 100), 2);
                            if ($coupon->max_discount) {
                                $discount = min($discount, $coupon->max_discount);
                            }
                        }
                    }
                }

                $taxable = max(0, $subtotal - $discount);
                $tax = round($taxable * $taxRate, 2);
                $total = round($subtotal + $shippingFee + $tax - $discount, 2);

                $orderNumber++;

                $order = Order::create([
                    'order_number' => 'ORD-2026-'.str_pad((string) $orderNumber, 6, '0', STR_PAD_LEFT),
                    'user_id' => $customer->id,
                    'address_id' => $address->id,
                    'coupon_id' => $coupon ? $coupon->id : null,
                    'subtotal' => round($subtotal, 2),
                    'discount' => $discount,
                    'shipping_fee' => $shippingFee,
                    'tax' => $tax,
                    'total' => $total,
                    'status' => $status,
                    'payment_status' => $orderPaymentStatus,
                    'shipping_address_snapshot' => json_encode([
                        'recipient_name' => $address->recipient_name,
                        'address_line1' => $address->address_line1,
                        'city' => $address->city,
                        'state' => $address->state,
                        'country' => $address->country,
                        'postal_code' => $address->postal_code,
                    ]),
                    'notes' => fake()->boolean(30) ? 'Please leave at the door.' : null,
                    'placed_at' => now()->subDays(fake()->numberBetween(1, 40))->subHours(fake()->numberBetween(1, 12)),
                ]);

                foreach ($lineItems as $item) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['product']->id,
                        'product_name' => $item['product']->name,
                        'product_sku' => $item['product']->sku,
                        'unit_price' => $item['product']->price,
                        'quantity' => $item['qty'],
                        'line_total' => $item['line_total'],
                    ]);
                }

                Payment::create([
                    'order_id' => $order->id,
                    'payment_number' => 'PAY-'.$order->created_at->format('Ymd').'-'.fake()->unique()->numerify('######'),
                    'payment_method' => $paymentMethod = $orderPaymentStatus === 'paid'
                        ? fake()->randomElement(['aba_pay', 'khqr', 'card', 'cod', 'bank_transfer', 'online'])
                        : fake()->randomElement(['cod', 'card', 'aba_pay']),
                    'gateway' => 'payway',
                    'transaction_id' => $orderPaymentStatus === 'paid' ? 'TXN-'.strtoupper(fake()->bothify('#########')) : null,
                    'gateway_transaction_id' => $orderPaymentStatus === 'paid'
                        ? 'PY'.fake()->unique()->numerify('#########') : null,
                    'amount' => $total,
                    'currency' => 'USD',
                    'payment_status' => $paymentRecordStatus,
                    'paid_at' => $orderPaymentStatus === 'paid' ? now()->subDays(fake()->numberBetween(1, 40)) : null,
                    'gateway_response' => null,
                ]);

                if ($coupon && $discount > 0) {
                    $coupon->usages()->create([
                        'user_id' => $customer->id,
                        'order_id' => $order->id,
                        'discount_applied' => $discount,
                    ]);
                    $coupon->increment('used_count');
                }

                // Inventory: record sale transactions and decrement stock
                foreach ($lineItems as $item) {
                    $product = $item['product'];
                    $before = $product->stock_quantity;
                    $after = max(0, $before - $item['qty']);
                    InventoryTransaction::create([
                        'product_id' => $product->id,
                        'user_id' => null,
                        'type' => 'sale',
                        'quantity_change' => -$item['qty'],
                        'stock_before' => $before,
                        'stock_after' => $after,
                        'reference_type' => 'App\\Models\\Order',
                        'reference_id' => $order->id,
                        'notes' => 'Order '.$order->order_number,
                    ]);
                    $product->update(['stock_quantity' => $after]);
                }

                // Cancelled orders release their reserved stock, matching the
                // real inventory flow: a `return` ledger entry per line restores
                // the product so the ledger stays consistent with stock.
                if ($status === 'cancelled') {
                    foreach ($lineItems as $item) {
                        $product = $item['product'];
                        $before = $product->stock_quantity;
                        $after = $before + $item['qty'];
                        InventoryTransaction::create([
                            'product_id' => $product->id,
                            'user_id' => null,
                            'type' => 'return',
                            'quantity_change' => $item['qty'],
                            'stock_before' => $before,
                            'stock_after' => $after,
                            'reference_type' => 'App\\Models\\Order',
                            'reference_id' => $order->id,
                            'notes' => 'Restocked after order cancellation',
                        ]);
                        $product->update(['stock_quantity' => $after]);
                    }
                }

                // Replay the order timeline so the admin dashboard shows real,
                // ordered history events for every non-pending order.
                $timeline = $this->statusTimeline($status);
                if (count($timeline) > 1 && $admin) {
                    for ($step = 1; $step < count($timeline); $step++) {
                        OrderStatusHistory::create([
                            'order_id' => $order->id,
                            'admin_id' => $admin->id,
                            'old_status' => $timeline[$step - 1],
                            'new_status' => $timeline[$step],
                            'note' => $step === 1 && $timeline[$step] === 'cancelled'
                                ? 'Customer request cancelled'
                                : null,
                            'created_at' => $order->placed_at->copy()->addMinutes($step * fake()->numberBetween(15, 90)),
                        ]);
                    }
                }

                // A few orders carry free-form admin notes.
                if ($status !== 'pending' && fake()->boolean(35)) {
                    OrderNote::create([
                        'order_id' => $order->id,
                        'admin_id' => $admin?->id ?? 1,
                        'note' => fake()->randomElement([
                            'Customer asked for a call before delivery.',
                            'Double-check packaging for this order.',
                            'Confirmed stock is available for this order.',
                            'Follow up on delivery confirmation.',
                        ]),
                        'created_at' => $order->placed_at->copy()->addHours(fake()->numberBetween(2, 24)),
                    ]);
                }

                $created[] = [
                    'order' => $order,
                    'items' => $orderProducts->map(fn ($p) => $p->id)->all(),
                    'user_id' => $customer->id,
                    'status' => $status,
                ];
            }
        }

        // Store created orders for ReviewSeeder to consume via a temporary binding is not reliable;
        // ReviewSeeder re-derives from delivered orders directly.
    }

    /**
     * The valid status chain that leads to a given order status, used to seed
     * realistic timeline entries (old → new transitions).
     */
    private function statusTimeline(string $status): array
    {
        return match ($status) {
            'confirmed' => ['pending', 'confirmed'],
            'processing' => ['pending', 'confirmed', 'processing'],
            'shipped' => ['pending', 'confirmed', 'processing', 'shipped'],
            'delivered' => ['pending', 'confirmed', 'processing', 'shipped', 'delivered'],
            'cancelled' => ['pending', 'cancelled'],
            default => [$status],
        };
    }
}
