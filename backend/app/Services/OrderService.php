<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Order placement, history and detail lookups.
 *
 * Core principles (business-rules.md §4, §5, §6):
 *   - Prices, subtotal, shipping fee and total are ALWAYS calculated here from
 *     live product prices — never trusted from the client;
 *   - Every order line stores a product name/SKU/price snapshot so historical
 *     orders stay valid when products change or are disabled;
 *   - The shipping address is snapshotted so later edits never alter orders;
 *   - Stock is validated and decremented (InventoryService::sell) inside the
 *     same atomic checkout transaction — stock can never go negative and every
 *     sale is written to the inventory ledger;
 *   - Checkout runs inside a single database transaction: an invalid line
 *     aborts the whole order, leaves the cart untouched and rolls the stock
 *     decrement back too.
 */
class OrderService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * Eager loads shared by order listing and detail lookups so resources
     * never trigger N+1 queries.
     */
    private function eagerLoads(): array
    {
        return [
            'items' => fn ($q) => $q->orderBy('id'),
            'items.product' => fn ($q) => $q->withTrashed(),
            'items.product.primaryImage',
            'payments' => fn ($q) => $q->orderByDesc('id'),
        ];
    }

    /**
     * Place an order from the customer's current cart.
     *
     * @return Order|null null when the address is not owned by the user
     *
     * @throws ValidationException when the cart is empty or any line is
     *                             unavailable / beyond available stock
     */
    public function placeOrder(User $user, int $addressId): ?Order
    {
        return DB::transaction(function () use ($user, $addressId) {
            $address = $user->addresses()->find($addressId);

            if (! $address) {
                return null;
            }

            $cart = $user->cart()->where('status', 'active')->first();
            $cartItems = $cart?->items()->with('product')->get() ?? collect();

            if ($cartItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => 'Your cart is empty. Add products before checking out.',
                ]);
            }

            $this->assertLinesCheckoutable($cartItems);

            $subtotal = 0.0;

            foreach ($cartItems as $cartItem) {
                $subtotal += (float) $cartItem->product->price * $cartItem->quantity;
            }

            $subtotal = round($subtotal, 2);
            $shippingFee = (float) config('store.shipping_fee');
            $discount = 0.0;
            $tax = 0.0;
            $total = round($subtotal + $shippingFee + $tax - $discount, 2);

            $order = Order::create([
                'order_number' => 'TMP-'.bin2hex(random_bytes(6)),
                'user_id' => $user->id,
                'address_id' => $address->id,
                'coupon_id' => null,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'shipping_fee' => $shippingFee,
                'tax' => $tax,
                'total' => $total,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'shipping_address_snapshot' => json_encode([
                    'label' => $address->label,
                    'recipient_name' => $address->recipient_name,
                    'recipient_phone' => $address->recipient_phone,
                    'address_line1' => $address->address_line1,
                    'address_line2' => $address->address_line2,
                    'city' => $address->city,
                    'state' => $address->state,
                    'postal_code' => $address->postal_code,
                    'country' => $address->country,
                ]),
                'notes' => null,
                'placed_at' => now(),
            ]);

            $order->order_number = $this->resolveOrderNumber($order);
            $order->save();

            foreach ($cartItems as $cartItem) {
                $product = $cartItem->product;

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'unit_price' => $product->price,
                    'quantity' => $cartItem->quantity,
                    'line_total' => round((float) $product->price * $cartItem->quantity, 2),
                ]);
            }

            // Reserve stock: re-validate under a row lock so concurrent
            // checkouts can never over-sell, then record the sale in the
            // inventory ledger — all inside this same atomic transaction.
            foreach ($cartItems as $cartItem) {
                $product = Product::query()->whereKey($cartItem->product_id)->lockForUpdate()->first();

                if (! $product || ! $product->isAvailable()) {
                    throw ValidationException::withMessages([
                        'cart_item' => ($product?->name ?? 'A product').' is no longer available.',
                    ]);
                }

                if ($cartItem->quantity > $product->stock_quantity) {
                    throw ValidationException::withMessages([
                        'cart_item' => "Only {$product->stock_quantity} unit(s) of {$product->name} are available.",
                    ]);
                }

                $this->inventory->sell($user, $product, $cartItem->quantity, $order);
            }

            $cart->items()->delete();

            return $order->load($this->eagerLoads());
        });
    }

    /**
     * The customer's orders, newest first, paginated for the UI.
     */
    public function list(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return Order::with($this->eagerLoads())
            ->where('user_id', $user->id)
            ->orderByDesc('placed_at')
            ->orderByDesc('id')
            ->paginate(max(1, min(50, $perPage)));
    }

    /**
     * A single order by its human-readable order number (own orders only).
     */
    public function findByNumber(User $user, string $orderNumber): ?Order
    {
        return Order::with($this->eagerLoads())
            ->where('user_id', $user->id)
            ->where('order_number', $orderNumber)
            ->first();
    }

    /**
     * @throws ValidationException on the first offending line
     */
    private function assertLinesCheckoutable($cartItems): void
    {
        foreach ($cartItems as $cartItem) {
            $product = $cartItem->product;

            if (! $product || $product->trashed() || ! $product->isAvailable()) {
                $name = $product?->name ?? 'A product';
                throw ValidationException::withMessages([
                    'cart_item' => "{$name} is no longer available.",
                ]);
            }

            if ($cartItem->quantity > $product->stock_quantity) {
                throw ValidationException::withMessages([
                    'cart_item' => "Only {$product->stock_quantity} unit(s) of {$product->name} are available.",
                ]);
            }
        }
    }

    /**
     * Human-readable, unique order number: ORD-YYYYMMDD-###### (6-digit id pad).
     * Generated server-side inside the checkout transaction; the id-based pad
     * guarantees uniqueness (backed by the DB unique constraint).
     */
    private function resolveOrderNumber(Order $order): string
    {
        return 'ORD-'.$order->created_at->format('Ymd').'-'.str_pad((string) $order->id, 6, '0', STR_PAD_LEFT);
    }
}