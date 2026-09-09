<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    // ------------------------------------------------------------------
    // Listing
    // ------------------------------------------------------------------

    public function test_customer_can_list_their_orders(): void
    {
        $token = $this->login($this->customer());
        $baseline = Order::where('user_id', $this->customer()->id)->count();
        $this->addToCart($token, $this->product(['price' => 5.00]), 1);
        $order = $this->checkout($token);

        $this->withToken($token)->getJson('/api/v1/orders')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', $baseline + 1)
            ->assertJsonPath('data.orders.0.order_number', $order->order_number)
            ->assertJsonPath('data.orders.0.status', 'pending')
            ->assertJsonPath('data.orders.0.payment_status', 'unpaid')
            ->assertJsonStructure([
                'success', 'message',
                'data' => [
                    'orders' => [[
                        'id', 'order_number', 'status', 'payment_status',
                        'subtotal', 'discount', 'shipping_fee', 'tax', 'total',
                        'placed_at',
                    ]],
                    'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ]);
    }

    public function test_orders_list_only_contains_own_orders(): void
    {
        $token = $this->login($this->customer());
        $this->addToCart($token, $this->product(), 1);
        $order = $this->checkout($token);

        $johnBaseline = Order::where('user_id', $this->otherCustomer()->id)->count();

        $otherToken = $this->login($this->otherCustomer());
        $response = $this->withToken($otherToken)->getJson('/api/v1/orders')
            ->assertStatus(200)
            ->assertJsonPath('data.pagination.total', $johnBaseline);

        $this->assertNotContains($order->order_number, collect($response->json('data.orders'))->pluck('order_number')->all());
    }

    public function test_orders_list_is_paginated(): void
    {
        $token = $this->login($this->customer());
        $addressId = $this->ownAddressId();
        $baseline = Order::where('user_id', $this->customer()->id)->count();

        for ($i = 1; $i <= 16; $i++) {
            Order::create([
                'order_number' => 'ORD-TEST-' . str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'user_id' => $this->customer()->id,
                'address_id' => $addressId,
                'subtotal' => 10.00,
                'shipping_fee' => 2.00,
                'total' => 12.00,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'placed_at' => now()->subMinutes(16 - $i),
            ]);
        }

        $total = $baseline + 16;

        $this->withToken($token)->getJson('/api/v1/orders')
            ->assertStatus(200)
            ->assertJsonPath('data.pagination.total', $total)
            ->assertJsonPath('data.pagination.per_page', 15)
            ->assertJsonPath('data.pagination.last_page', (int) ceil($total / 15))
            ->assertJsonPath('data.pagination.current_page', 1)
            ->assertJsonCount(min(15, $total), 'data.orders');

        $this->withToken($token)->getJson('/api/v1/orders?page=2')
            ->assertJsonPath('data.pagination.current_page', 2)
            ->assertJsonCount($total - 15, 'data.orders');
    }

    // ------------------------------------------------------------------
    // Detail
    // ------------------------------------------------------------------

    public function test_customer_can_view_order_by_order_number(): void
    {
        $token = $this->login($this->customer());
        $this->addToCart($token, $this->product(['price' => 5.00]), 1);
        $order = $this->checkout($token);

        $this->withToken($token)->getJson("/api/v1/orders/{$order->order_number}")
            ->assertStatus(200)
            ->assertJsonPath('data.order_number', $order->order_number)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total', '7.00')
            ->assertJsonCount(1, 'data.items');
    }

    public function test_unknown_order_number_returns_404(): void
    {
        $token = $this->login($this->customer());

        $this->withToken($token)->getJson('/api/v1/orders/ORD-999999-000001')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_customer_cannot_view_another_customers_order(): void
    {
        $owner = $this->login($this->customer());
        $this->addToCart($owner, $this->product(), 1);
        $order = $this->checkout($owner);

        $other = $this->login($this->otherCustomer());
        $this->withToken($other)->getJson("/api/v1/orders/{$order->order_number}")
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_order_detail_returns_item_snapshots(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product(['name' => 'Organic Carrots', 'price' => 2.50]);
        $this->addToCart($token, $product, 3);
        $order = $this->checkout($token);

        $this->withToken($token)->getJson("/api/v1/orders/{$order->order_number}")
            ->assertStatus(200)
            ->assertJsonPath('data.items.0.product_name', 'Organic Carrots')
            ->assertJsonPath('data.items.0.unit_price', '2.50')
            ->assertJsonPath('data.items.0.quantity', 3)
            ->assertJsonPath('data.items.0.line_total', '7.50');
    }

    public function test_order_snapshot_is_immutable_when_product_price_changes(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product(['name' => 'Organic Kale', 'price' => 4.00]);
        $this->addToCart($token, $product, 1);
        $order = $this->checkout($token);

        $product->update(['price' => 8.00, 'status' => 'inactive']);

        $this->withToken($token)->getJson("/api/v1/orders/{$order->order_number}")
            ->assertStatus(200)
            ->assertJsonPath('data.items.0.unit_price', '4.00')
            ->assertJsonPath('data.items.0.product_name', 'Organic Kale')
            ->assertJsonPath('data.subtotal', '4.00')
            ->assertJsonPath('data.total', '6.00')
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_order_totals_are_reconciled_from_stored_values(): void
    {
        $token = $this->login($this->customer());
        $this->addToCart($token, $this->product(['price' => 12.25]), 2);
        $order = $this->checkout($token);

        $this->assertEquals('24.50', $order->subtotal);
        $this->assertEquals('2.00', $order->shipping_fee);
        $this->assertEquals('26.50', $order->total);
        $this->assertEquals(round((float) $order->subtotal + (float) $order->shipping_fee + (float) $order->tax - (float) $order->discount, 2), (float) $order->total);
    }

    public function test_order_placed_at_is_recorded(): void
    {
        $token = $this->login($this->customer());
        $this->addToCart($token, $this->product(), 1);
        $order = $this->checkout($token);

        $this->assertNotNull($order->placed_at);
    }

    // ------------------------------------------------------------------
    // Customer cancellation
    // ------------------------------------------------------------------

    public function test_customer_can_cancel_their_own_pending_order(): void
    {
        $token = $this->login($this->customer());
        $this->addToCart($token, $this->product(['stock_quantity' => 5]), 2);
        $order = $this->checkout($token);

        $this->assertSame('pending', $order->status);

        $this->withToken($token)->postJson('/api/v1/orders/'.$order->order_number.'/cancel')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_customer_cannot_cancel_another_customers_order(): void
    {
        $token = $this->login($this->customer());
        $this->addToCart($token, $this->product(), 1);
        $order = $this->checkout($token);

        $otherToken = $this->login($this->otherCustomer());
        $this->withToken($otherToken)->postJson('/api/v1/orders/'.$order->order_number.'/cancel')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_customer_cannot_cancel_a_delivered_order(): void
    {
        $token = $this->login($this->customer());
        $this->addToCart($token, $this->product(), 1);
        $order = $this->checkout($token);
        $order->update(['status' => 'delivered']);

        $this->withToken($token)->postJson('/api/v1/orders/'.$order->order_number.'/cancel')
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function ownAddressId(): int
    {
        return Address::where('user_id', $this->customer()->id)->where('is_default', true)->firstOrFail()->id;
    }

    protected function addToCart(string $token, Product $product, int $quantity): void
    {
        $this->withToken($token)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => $quantity,
        ])->assertStatus(201);
    }

    protected function checkout(string $token): Order
    {
        $this->withToken($token)->postJson('/api/v1/checkout', ['address_id' => $this->ownAddressId()])
            ->assertStatus(201);

        return Order::where('user_id', $this->customer()->id)->orderByDesc('id')->firstOrFail();
    }

    protected function customer(): User
    {
        return User::where('email', 'maria@example.com')->firstOrFail();
    }

    protected function otherCustomer(): User
    {
        return User::where('email', 'john@example.com')->firstOrFail();
    }

    protected function product(array $attributes = []): Product
    {
        $attributes['category_id'] = $attributes['category_id'] ?? Category::factory()->create()->id;
        $attributes['stock_quantity'] = $attributes['stock_quantity'] ?? 10;

        return Product::factory()->active()->create($attributes);
    }

    protected function login(User $user): string
    {
        $this->flushHeaders();
        $this->app->make(\Illuminate\Contracts\Auth\Factory::class)->forgetGuards();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200);

        return $response->json('data.token');
    }
}