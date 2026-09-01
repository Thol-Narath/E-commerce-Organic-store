<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Cart;
use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    // ------------------------------------------------------------------
    // Access & validation
    // ------------------------------------------------------------------

    public function test_unauthenticated_checkout_receives_401(): void
    {
        $this->postJson('/api/v1/checkout', ['address_id' => 1])->assertStatus(401);
        $this->getJson('/api/v1/orders')->assertStatus(401);
        $this->getJson('/api/v1/orders/ORD-1')->assertStatus(401);
    }

    public function test_checkout_requires_an_address(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product();
        $this->addToCart($token, $product, 1);

        $this->withToken($token)->postJson('/api/v1/checkout', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['address_id'], 'data');
    }

    public function test_checkout_rejects_nonexistent_address(): void
    {
        $token = $this->login($this->customer());
        $this->addToCart($token, $this->product(), 1);

        $this->withToken($token)->postJson('/api/v1/checkout', ['address_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['address_id'], 'data');
    }

    public function test_checkout_with_another_customers_address_returns_404(): void
    {
        $token = $this->login($this->customer());
        $this->addToCart($token, $this->product(), 1);

        $otherAddress = Address::where('user_id', $this->otherCustomer()->id)->firstOrFail();

        $this->withToken($token)->postJson('/api/v1/checkout', ['address_id' => $otherAddress->id])
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_checkout_with_empty_cart_returns_validation_error(): void
    {
        $token = $this->login($this->customer());
        $ordersBefore = Order::count();

        $this->withToken($token)->postJson('/api/v1/checkout', ['address_id' => $this->ownAddressId()])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cart'], 'data');

        $this->assertDatabaseCount('orders', $ordersBefore);
    }

    // ------------------------------------------------------------------
    // Successful order creation
    // ------------------------------------------------------------------

    public function test_checkout_creates_order_with_pending_and_unpaid_status(): void
    {
        $token = $this->login($this->customer());
        $this->addToCart($token, $this->product(), 1);

        $this->withToken($token)->postJson('/api/v1/checkout', ['address_id' => $this->ownAddressId()])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment_status', 'unpaid');

        $this->assertDatabaseHas('orders', [
            'user_id' => $this->customer()->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);
    }

    public function test_checkout_computes_totals_from_live_prices(): void
    {
        $token = $this->login($this->customer());
        $this->addToCart($token, $this->product(['price' => 4.50]), 2);

        $this->withToken($token)->postJson('/api/v1/checkout', ['address_id' => $this->ownAddressId()])
            ->assertStatus(201)
            ->assertJsonPath('data.subtotal', '9.00')
            ->assertJsonPath('data.discount', '0.00')
            ->assertJsonPath('data.tax', '0.00')
            ->assertJsonPath('data.shipping_fee', '2.00')
            ->assertJsonPath('data.total', '11.00')
            ->assertJsonPath('data.items.0.unit_price', '4.50')
            ->assertJsonPath('data.items.0.line_total', '9.00')
            ->assertJsonPath('data.items.0.quantity', 2);

        $order = Order::where('user_id', $this->customer()->id)->orderByDesc('id')->firstOrFail();
        $this->assertEquals('9.00', $order->subtotal);
        $this->assertEquals('2.00', $order->shipping_fee);
        $this->assertEquals('11.00', $order->total);
    }

    public function test_shipping_fee_is_taken_from_store_config(): void
    {
        config(['store.shipping_fee' => 5.50]);

        $token = $this->login($this->customer());
        $this->addToCart($token, $this->product(['price' => 10.00]), 1);

        $this->withToken($token)->postJson('/api/v1/checkout', ['address_id' => $this->ownAddressId()])
            ->assertStatus(201)
            ->assertJsonPath('data.shipping_fee', '5.50')
            ->assertJsonPath('data.total', '15.50');
    }

    public function test_checkout_total_is_subtotal_plus_shipping(): void
    {
        $token = $this->login($this->customer());
        $this->addToCart($token, $this->product(['price' => 7.25]), 3);

        $this->withToken($token)->postJson('/api/v1/checkout', ['address_id' => $this->ownAddressId()])
            ->assertStatus(201)
            ->assertJsonPath('data.subtotal', '21.75')
            ->assertJsonPath('data.total', '23.75');
    }

    public function test_checkout_ignores_client_supplied_totals_and_status(): void
    {
        $token = $this->login($this->customer());
        $this->addToCart($token, $this->product(['price' => 5.00]), 2);

        $this->withToken($token)->postJson('/api/v1/checkout', [
            'address_id' => $this->ownAddressId(),
            'subtotal' => '0.01',
            'discount' => '5.00',
            'shipping_fee' => '0.00',
            'tax' => '1.00',
            'total' => '0.01',
            'status' => 'delivered',
            'payment_status' => 'paid',
        ])->assertStatus(201)
            ->assertJsonPath('data.subtotal', '10.00')
            ->assertJsonPath('data.discount', '0.00')
            ->assertJsonPath('data.shipping_fee', '2.00')
            ->assertJsonPath('data.tax', '0.00')
            ->assertJsonPath('data.total', '12.00')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.payment_status', 'unpaid');

        $order = Order::where('user_id', $this->customer()->id)->orderByDesc('id')->firstOrFail();
        $this->assertEquals('12.00', $order->total);
        $this->assertEquals('pending', $order->status);
        $this->assertEquals('unpaid', $order->payment_status);
    }

    public function test_checkout_multiple_items_sums_subtotal(): void
    {
        $token = $this->login($this->customer());
        $this->addToCart($token, $this->product(['price' => 2.00]), 2);
        $this->addToCart($token, $this->product(['price' => 3.50]), 3);

        $this->withToken($token)->postJson('/api/v1/checkout', ['address_id' => $this->ownAddressId()])
            ->assertStatus(201)
            ->assertJsonPath('data.subtotal', '14.50')
            ->assertJsonPath('data.total', '16.50')
            ->assertJsonCount(2, 'data.items');
    }

    public function test_checkout_uses_price_at_time_of_checkout(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product(['price' => 5.00]);
        $this->addToCart($token, $product, 1);

        $product->update(['price' => 9.00]);

        $this->withToken($token)->postJson('/api/v1/checkout', ['address_id' => $this->ownAddressId()])
            ->assertStatus(201)
            ->assertJsonPath('data.subtotal', '9.00')
            ->assertJsonPath('data.items.0.unit_price', '9.00');
    }

    // ------------------------------------------------------------------
    // Stock & availability guards
    // ------------------------------------------------------------------

    public function test_cannot_checkout_inactive_product(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product(['price' => 5.00]);
        $this->addToCart($token, $product, 1);

        $product->update(['status' => 'inactive']);

        $this->withToken($token)->postJson('/api/v1/checkout', ['address_id' => $this->ownAddressId()])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cart_item'], 'data');

        $this->assertDatabaseCount('orders', Order::count());
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 1]);
    }

    public function test_cannot_checkout_out_of_stock_product(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product(['stock_quantity' => 3]);
        $this->addToCart($token, $product, 2);

        $product->update(['stock_quantity' => 0]);

        $this->withToken($token)->postJson('/api/v1/checkout', ['address_id' => $this->ownAddressId()])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cart_item'], 'data');

        $this->assertDatabaseCount('orders', Order::count());
    }

    public function test_cannot_checkout_when_cart_quantity_exceeds_stock(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product(['name' => 'Organic Apples', 'stock_quantity' => 2]);
        $this->addToCart($token, $product, 2);

        $product->update(['stock_quantity' => 1]);

        $this->withToken($token)->postJson('/api/v1/checkout', ['address_id' => $this->ownAddressId()])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cart_item'], 'data');

        $this->assertDatabaseCount('orders', Order::count());
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 2]);
    }

    public function test_checkout_fails_atomically_when_one_line_is_unavailable(): void
    {
        $token = $this->login($this->customer());
        $good = $this->product(['price' => 4.00]);
        $bad = $this->product(['price' => 3.00]);
        $this->addToCart($token, $good, 1);
        $this->addToCart($token, $bad, 1);

        $bad->update(['status' => 'inactive']);

        $this->withToken($token)->postJson('/api/v1/checkout', ['address_id' => $this->ownAddressId()])
            ->assertStatus(422);

        $this->assertDatabaseCount('orders', Order::count());
        $this->assertDatabaseCount('order_items', OrderItem::count());
        $this->assertDatabaseCount('cart_items', 2);
    }

    // ------------------------------------------------------------------
    // Snapshots & order integrity
    // ------------------------------------------------------------------

    public function test_checkout_stores_product_name_and_price_snapshots(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product(['name' => 'Organic Bananas', 'price' => 1.99]);
        $this->addToCart($token, $product, 2);

        $order = $this->checkout($token);

        $this->assertDatabaseHas('order_items', [
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => 'Organic Bananas',
            'unit_price' => '1.99',
            'quantity' => 2,
            'line_total' => '3.98',
        ]);
    }

    // ------------------------------------------------------------------
    // Inventory reservation (Phase 8)
    // ------------------------------------------------------------------

    public function test_checkout_decrements_stock_and_records_sale(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product(['name' => 'Organic Mangoes', 'stock_quantity' => 10]);
        $this->addToCart($token, $product, 3);

        $order = $this->checkout($token);

        $this->assertSame(7, $product->fresh()->stock_quantity);

        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $product->id,
            'type' => 'sale',
            'quantity_change' => -3,
            'stock_before' => 10,
            'stock_after' => 7,
            'reference_type' => 'App\\Models\\Order',
            'reference_id' => $order->id,
        ]);
    }

    public function test_failed_checkout_does_not_decrement_stock(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product(['stock_quantity' => 5]);
        $this->addToCart($token, $product, 1);

        $product->update(['stock_quantity' => 0]);

        $this->withToken($token)->postJson('/api/v1/checkout', ['address_id' => $this->ownAddressId()])
            ->assertStatus(422);

        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertSame(0, InventoryTransaction::where('product_id', $product->id)->where('type', 'sale')->count());
    }

    public function test_checkout_snapshots_the_shipping_address(): void
    {
        $token = $this->login($this->customer());
        $this->addToCart($token, $this->product(), 1);

        $order = $this->checkout($token);

        $snapshot = json_decode($order->shipping_address_snapshot, true);
        $this->assertSame($this->ownAddress()->recipient_name, $snapshot['recipient_name']);
        $this->assertSame($this->ownAddress()->address_line1, $snapshot['address_line1']);
        $this->assertSame($this->ownAddress()->city, $snapshot['city']);
        $this->assertSame($this->ownAddress()->country, $snapshot['country']);
        $this->assertArrayHasKey('recipient_phone', $snapshot);
        $this->assertArrayHasKey('postal_code', $snapshot);

        $this->withToken($token)->getJson("/api/v1/orders/{$order->order_number}")
            ->assertJsonPath('data.shipping_address.recipient_name', $this->ownAddress()->recipient_name);
    }

    public function test_order_number_is_generated_server_side_and_unique(): void
    {
        $token = $this->login($this->customer());
        $this->addToCart($token, $this->product(), 1);
        $order = $this->checkout($token);

        $this->assertMatchesRegularExpression('/^ORD-\d{8}-\d{6}$/', $order->order_number);

        // Second order gets a different number.
        $token2 = $this->login($this->otherCustomer());
        $this->addToCart($token2, $this->product(), 1);
        $order2 = Order::where('user_id', $this->otherCustomer()->id)->orderByDesc('id')->firstOrFail();

        $this->assertNotSame($order->order_number, $order2->order_number);
    }

    public function test_checkout_clears_the_cart_after_ordering(): void
    {
        $token = $this->login($this->customer());
        $ordersBefore = Order::count();
        $this->addToCart($token, $this->product(), 2);

        $this->checkout($token);

        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseCount('orders', $ordersBefore + 1);
    }

    public function test_duplicate_submission_is_rejected_after_cart_is_cleared(): void
    {
        $token = $this->login($this->customer());
        $ordersBefore = Order::count();
        $this->addToCart($token, $this->product(), 1);

        $this->withToken($token)->postJson('/api/v1/checkout', ['address_id' => $this->ownAddressId()])
            ->assertStatus(201);

        // Second submission of the same intent hits an empty cart.
        $this->withToken($token)->postJson('/api/v1/checkout', ['address_id' => $this->ownAddressId()])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['cart'], 'data');

        $this->assertDatabaseCount('orders', $ordersBefore + 1);
    }

    public function test_checkout_orders_are_scoped_to_the_authenticated_user(): void
    {
        $token = $this->login($this->customer());
        $this->addToCart($token, $this->product(), 1);
        $order = $this->checkout($token);

        $johnBaseline = Order::where('user_id', $this->otherCustomer()->id)->count();

        $otherToken = $this->login($this->otherCustomer());
        $response = $this->withToken($otherToken)->getJson('/api/v1/orders')->assertStatus(200);
        $response->assertJsonPath('data.pagination.total', $johnBaseline);
        $this->assertNotContains($order->order_number, collect($response->json('data.items'))->pluck('order_number')->all());
    }

    // ------------------------------------------------------------------
    // Resource structure
    // ------------------------------------------------------------------

    public function test_checkout_response_structure(): void
    {
        $token = $this->login($this->customer());
        $this->addToCart($token, $this->product(), 1);

        $this->withToken($token)->postJson('/api/v1/checkout', ['address_id' => $this->ownAddressId()])
            ->assertStatus(201)
            ->assertJsonStructure([
                'success', 'message',
                'data' => [
                    'id', 'order_number', 'status', 'payment_status',
                    'subtotal', 'discount', 'shipping_fee', 'tax', 'total',
                    'shipping_address',
                    'items' => [[
                        'id', 'product_id', 'product_name', 'product_sku',
                        'quantity', 'unit_price', 'line_total', 'product',
                    ]],
                ],
            ]);
    }

    public function test_checkout_places_at_records_date(): void
    {
        $token = $this->login($this->customer());
        $this->addToCart($token, $this->product(), 1);

        $this->withToken($token)->postJson('/api/v1/checkout', ['address_id' => $this->ownAddressId()])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['placed_at']]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function ownAddress(): Address
    {
        return Address::where('user_id', $this->customer()->id)->where('is_default', true)->firstOrFail();
    }

    protected function ownAddressId(): int
    {
        return $this->ownAddress()->id;
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
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

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