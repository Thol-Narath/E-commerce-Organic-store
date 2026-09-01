<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderNote;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminOrderApiTest extends TestCase
{
    use RefreshDatabase;

    private int $orderCounter = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    // ------------------------------------------------------------------
    // Access control
    // ------------------------------------------------------------------

    public function test_unauthenticated_cannot_access_admin_order_routes(): void
    {
        $order = $this->makeOrder();

        $this->getJson('/api/v1/admin/orders')->assertStatus(401);
        $this->getJson('/api/v1/admin/orders/statistics')->assertStatus(401);
        $this->getJson('/api/v1/admin/orders/'.$order->id)->assertStatus(401);
        $this->patchJson('/api/v1/admin/orders/'.$order->id.'/status', ['status' => 'confirmed'])->assertStatus(401);
        $this->postJson('/api/v1/admin/orders/'.$order->id.'/cancel', ['note' => 'x'])->assertStatus(401);
        $this->postJson('/api/v1/admin/orders/'.$order->id.'/notes', ['note' => 'x'])->assertStatus(401);
    }

    public function test_customer_cannot_access_admin_order_routes(): void
    {
        $token = $this->login($this->customer());
        $order = $this->makeOrder();

        $this->withToken($token)->getJson('/api/v1/admin/orders')->assertStatus(403);
        $this->withToken($token)->getJson('/api/v1/admin/orders/statistics')->assertStatus(403);
        $this->withToken($token)->getJson('/api/v1/admin/orders/'.$order->id)->assertStatus(403);
        $this->withToken($token)->patchJson('/api/v1/admin/orders/'.$order->id.'/status', ['status' => 'confirmed'])->assertStatus(403);
        $this->withToken($token)->postJson('/api/v1/admin/orders/'.$order->id.'/cancel', ['note' => 'x'])->assertStatus(403);
        $this->withToken($token)->postJson('/api/v1/admin/orders/'.$order->id.'/notes', ['note' => 'x'])->assertStatus(403);
    }

    public function test_staff_can_view_orders_and_update_status_but_not_admin_only_actions(): void
    {
        $token = $this->login($this->staff());
        $order = $this->makeOrder(['status' => 'pending']);

        $this->withToken($token)->getJson('/api/v1/admin/orders')->assertStatus(200);
        $this->withToken($token)->getJson('/api/v1/admin/orders/'.$order->id)->assertStatus(200);

        $this->withToken($token)->patchJson('/api/v1/staff/orders/'.$order->id.'/status', ['status' => 'confirmed'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');

        $this->withToken($token)->getJson('/api/v1/admin/orders/statistics')->assertStatus(403);
        $this->withToken($token)->postJson('/api/v1/admin/orders/'.$order->id.'/cancel')->assertStatus(403);
        $this->withToken($token)->postJson('/api/v1/admin/orders/'.$order->id.'/notes', ['note' => 'x'])->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // List with search / filters / sorting / pagination
    // ------------------------------------------------------------------

    public function test_admin_can_list_orders_with_relations_and_default_page_size(): void
    {
        $token = $this->login($this->admin());

        $response = $this->withToken($token)->getJson('/api/v1/admin/orders')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message',
                'data' => [
                    'orders' => [[
                        'id', 'order_number', 'status', 'payment_status',
                        'customer' => ['id', 'name', 'email', 'phone'],
                        'subtotal', 'discount', 'shipping_fee', 'tax', 'total',
                        'items', 'payments', 'status_history', 'admin_notes',
                        'placed_at',
                    ]],
                    'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ]);

        $this->assertTrue($response->json('data.pagination.total') > 0);
        $this->assertSame(20, $response->json('data.pagination.per_page'));
    }

    public function test_list_respects_per_page_and_caps_at_50(): void
    {
        $token = $this->login($this->admin());

        $response = $this->withToken($token)->getJson('/api/v1/admin/orders?per_page=3');
        $this->assertSame(3, $response->json('data.pagination.per_page'));
        $this->assertCount(3, $response->json('data.orders'));

        $capped = $this->withToken($token)->getJson('/api/v1/admin/orders?per_page=100');
        $this->assertSame(50, $capped->json('data.pagination.per_page'));
    }

    public function test_search_matches_order_number(): void
    {
        $token = $this->login($this->admin());
        $order = $this->makeOrder();
        $needle = explode('-', $order->order_number)[1];

        $response = $this->withToken($token)->getJson('/api/v1/admin/orders?search='.$needle)
            ->assertStatus(200);

        $ids = collect($response->json('data.orders'))->pluck('id')->all();
        $this->assertContains($order->id, $ids);
    }

    public function test_search_matches_customer_name_email_and_phone(): void
    {
        $token = $this->login($this->admin());
        $customer = $this->customer();
        $order = $this->makeOrder(['user_id' => $customer->id]);

        foreach ([$customer->name, $customer->email, $customer->phone] as $term) {
            $response = $this->withToken($token)->getJson('/api/v1/admin/orders?search='.$term);
            $ids = collect($response->json('data.orders'))->pluck('id')->all();
            $this->assertContains($order->id, $ids, "Search term: {$term}");
        }
    }

    public function test_filter_by_status_and_payment_status(): void
    {
        $token = $this->login($this->admin());

        $pending = $this->withToken($token)->getJson('/api/v1/admin/orders?status=pending');
        foreach ($pending->json('data.orders') as $o) {
            $this->assertSame('pending', $o['status']);
        }

        $paid = $this->withToken($token)->getJson('/api/v1/admin/orders?payment_status=paid');
        foreach ($paid->json('data.orders') as $o) {
            $this->assertSame('paid', $o['payment_status']);
        }
    }

    public function test_filter_by_payment_method(): void
    {
        $token = $this->login($this->admin());
        $order = $this->makeOrder(['payment_status' => 'paid']);
        $this->makePayment($order, ['payment_method' => 'khqr', 'payment_status' => 'paid']);

        $response = $this->withToken($token)->getJson('/api/v1/admin/orders?payment_method=khqr');

        $ids = collect($response->json('data.orders'))->pluck('id')->all();
        $this->assertContains($order->id, $ids);
    }

    public function test_filter_by_date_range(): void
    {
        $token = $this->login($this->admin());

        $inside = $this->makeOrder(['placed_at' => now()->subDays(3)]);
        $outside = $this->makeOrder(['placed_at' => now()->subDays(40)]);
        $idInside = $inside->id;
        $idOutside = $outside->id;

        $response = $this->withToken($token)->getJson(
            '/api/v1/admin/orders?date_from='.now()->subDays(10)->toDateString().'&date_to='.now()->toDateString()
        );

        $ids = collect($response->json('data.orders'))->pluck('id')->all();
        $this->assertContains($idInside, $ids);
        $this->assertNotContains($idOutside, $ids);
    }

    public function test_filter_by_date_period_today(): void
    {
        $token = $this->login($this->admin());

        $today = $this->makeOrder(['placed_at' => now()]);
        $old = $this->makeOrder(['placed_at' => now()->subDays(5)]);

        $response = $this->withToken($token)->getJson('/api/v1/admin/orders?date_period=today');
        $ids = collect($response->json('data.orders'))->pluck('id')->all();

        $this->assertContains($today->id, $ids);
        $this->assertNotContains($old->id, $ids);
    }

    public function test_sorts_default_to_newest_and_supports_others(): void
    {
        $token = $this->login($this->admin());
        $target = $this->makeOrder(['placed_at' => now()]);

        $default = $this->withToken($token)->getJson('/api/v1/admin/orders');
        $this->assertSame($target->id, $default->json('data.orders.0.id'));

        $newest = $this->withToken($token)->getJson('/api/v1/admin/orders?sort=newest');
        $this->assertSame($target->id, $newest->json('data.orders.0.id'));

        $oldest = $this->withToken($token)->getJson('/api/v1/admin/orders?sort=oldest');
        $this->assertNotSame($target->id, $oldest->json('data.orders.0.id'));

        $high = $this->withToken($token)->getJson('/api/v1/admin/orders?sort=total_high');
        $rows = $high->json('data.orders');
        $this->assertGreaterThanOrEqual($rows[1]['total'], $rows[0]['total']);
    }

    // ------------------------------------------------------------------
    // Detail
    // ------------------------------------------------------------------

    public function test_admin_can_view_full_order_detail(): void
    {
        $token = $this->login($this->admin());
        $customer = $this->customer();
        $product = $this->product();
        $order = $this->makeOrder(['user_id' => $customer->id, 'status' => 'confirmed', 'payment_status' => 'paid']);
        $this->makeItem($order, $product, 2, 12.50);
        $this->makePayment($order, ['payment_method' => 'aba_pay', 'payment_status' => 'paid']);
        $this->makeHistory($order, 'pending', 'confirmed');
        $this->makeNote($order, 'Call customer before delivery.');

        $response = $this->withToken($token)->getJson('/api/v1/admin/orders/'.$order->id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.customer.email', $customer->email)
            ->assertJsonPath('data.status_history.0.new_status', 'confirmed')
            ->assertJsonPath('data.admin_notes.0.note', 'Call customer before delivery.')
            ->assertJsonStructure([
                'data' => [
                    'id', 'order_number', 'status', 'payment_status',
                    'customer' => ['id', 'name', 'email', 'phone'],
                    'subtotal', 'discount', 'shipping_fee', 'tax', 'total',
                    'items' => [[
                        'id', 'product_name', 'product_sku', 'unit_price',
                        'quantity', 'line_total', 'product',
                    ]],
                    'payments' => [['id', 'payment_number', 'payment_method', 'payment_status', 'amount']],
                    'status_history' => [['id', 'old_status', 'new_status', 'admin']],
                    'admin_notes' => [['id', 'note', 'admin', 'created_at']],
                    'shipping_address', 'placed_at', 'created_at', 'updated_at',
                ],
            ]);

        $this->assertSame('12.50', (string) $response->json('data.items.0.unit_price'));
        $this->assertSame('59.00', (string) $response->json('data.total'));
    }

    public function test_unknown_order_returns_404_envelope(): void
    {
        $token = $this->login($this->admin());

        $this->withToken($token)->getJson('/api/v1/admin/orders/999999999')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    // ------------------------------------------------------------------
    // Status transitions
    // ------------------------------------------------------------------

    public function test_admin_can_advance_status_and_history_is_recorded(): void
    {
        $token = $this->login($this->admin());
        $order = $this->makeOrder(['status' => 'pending']);

        $this->withToken($token)->patchJson('/api/v1/admin/orders/'.$order->id.'/status', [
            'status' => 'confirmed',
            'note' => 'Stock verified.',
        ])->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');

        $history = OrderStatusHistory::where('order_id', $order->id)->latest()->first();
        $this->assertSame('pending', $history->old_status);
        $this->assertSame('confirmed', $history->new_status);
        $this->assertSame('Stock verified.', $history->note);
        $this->assertSame($this->admin()->id, $history->admin_id);
    }

    public function test_status_cannot_skip_a_step(): void
    {
        $token = $this->login($this->admin());
        $order = $this->makeOrder(['status' => 'pending']);

        $this->withToken($token)->patchJson('/api/v1/admin/orders/'.$order->id.'/status', ['status' => 'processing'])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_status_cannot_stay_same(): void
    {
        $token = $this->login($this->admin());
        $order = $this->makeOrder(['status' => 'confirmed']);

        $this->withToken($token)->patchJson('/api/v1/admin/orders/'.$order->id.'/status', ['status' => 'confirmed'])
            ->assertStatus(422);
    }

    public function test_terminal_statuses_cannot_be_changed(): void
    {
        $token = $this->login($this->admin());
        $delivered = $this->makeOrder(['status' => 'delivered']);
        $cancelled = $this->makeOrder(['status' => 'cancelled']);

        $this->withToken($token)->patchJson('/api/v1/admin/orders/'.$delivered->id.'/status', ['status' => 'processing'])
            ->assertStatus(422);

        $this->withToken($token)->patchJson('/api/v1/admin/orders/'.$cancelled->id.'/status', ['status' => 'pending'])
            ->assertStatus(422);
    }

    public function test_status_update_validates_input(): void
    {
        $token = $this->login($this->admin());
        $order = $this->makeOrder(['status' => 'pending']);

        $this->withToken($token)->patchJson('/api/v1/admin/orders/'.$order->id.'/status', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status'], 'data');

        $this->withToken($token)->patchJson('/api/v1/admin/orders/'.$order->id.'/status', ['status' => 'bogus'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status'], 'data');

        $this->withToken($token)->patchJson('/api/v1/admin/orders/'.$order->id.'/status', [
            'status' => 'confirmed',
            'note' => str_repeat('x', 501),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['note'], 'data');
    }

    // ------------------------------------------------------------------
    // Cancellation
    // ------------------------------------------------------------------

    public function test_cancel_restocks_products_and_retires_pending_payment(): void
    {
        $token = $this->login($this->admin());
        $product = $this->product(['stock_quantity' => 20]);
        $order = $this->makeOrder(['status' => 'confirmed', 'payment_status' => 'unpaid']);
        $this->makeItem($order, $product, 3, 10.0);
        $this->makePayment($order, ['payment_status' => 'pending', 'payment_method' => 'aba_pay']);

        $this->withToken($token)->postJson('/api/v1/admin/orders/'.$order->id.'/cancel', [
            'note' => 'Customer changed their mind.',
        ])->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(23, $product->fresh()->stock_quantity);

        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $product->id,
            'type' => 'return',
            'quantity_change' => 3,
            'reference_type' => Order::class,
            'reference_id' => $order->id,
        ]);

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'old_status' => 'confirmed',
            'new_status' => 'cancelled',
            'note' => 'Customer changed their mind.',
        ]);

        $this->assertSame('cancelled', $order->payments()->first()->fresh()->payment_status);
    }

    public function test_cancel_paid_order_requests_refund_but_never_refunds_automatically(): void
    {
        $token = $this->login($this->admin());
        $product = $this->product(['stock_quantity' => 10]);
        $order = $this->makeOrder(['status' => 'processing', 'payment_status' => 'paid']);
        $this->makeItem($order, $product, 1, 10.0);
        $this->makePayment($order, ['payment_status' => 'paid', 'payment_method' => 'card']);

        $this->withToken($token)->postJson('/api/v1/admin/orders/'.$order->id.'/cancel')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Order cancelled successfully. Refund processing required.')
            ->assertJsonPath('data.payment_status', 'paid');

        $this->assertDatabaseMissing('inventory_transactions', [
            'product_id' => $product->id,
            'type' => 'refund',
            'reference_id' => $order->id,
        ]);

        $this->assertSame('paid', $order->payments()->first()->fresh()->payment_status);
    }

    public function test_cancel_restores_each_line_quantity(): void
    {
        $token = $this->login($this->admin());
        $a = $this->product(['stock_quantity' => 10]);
        $b = $this->product(['stock_quantity' => 10]);
        $order = $this->makeOrder(['status' => 'pending', 'payment_status' => 'unpaid']);
        $this->makeItem($order, $a, 2, 10.0);
        $this->makeItem($order, $b, 4, 10.0);

        $this->withToken($token)->postJson('/api/v1/admin/orders/'.$order->id.'/cancel')->assertStatus(200);

        $this->assertSame(12, $a->fresh()->stock_quantity);
        $this->assertSame(14, $b->fresh()->stock_quantity);
    }

    public function test_cannot_cancel_delivered_or_already_cancelled_orders(): void
    {
        $token = $this->login($this->admin());
        $delivered = $this->makeOrder(['status' => 'delivered', 'payment_status' => 'paid']);
        $cancelled = $this->makeOrder(['status' => 'cancelled']);

        $this->withToken($token)->postJson('/api/v1/admin/orders/'.$delivered->id.'/cancel')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status'], 'data');

        $this->withToken($token)->postJson('/api/v1/admin/orders/'.$cancelled->id.'/cancel')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status'], 'data');
    }

    // ------------------------------------------------------------------
    // Notes
    // ------------------------------------------------------------------

    public function test_admin_can_add_note_to_order(): void
    {
        $token = $this->login($this->admin());
        $order = $this->makeOrder();

        $response = $this->withToken($token)->postJson('/api/v1/admin/orders/'.$order->id.'/notes', [
            'note' => 'Follow up tomorrow.',
        ])->assertStatus(201)
            ->assertJsonPath('data.note', 'Follow up tomorrow.')
            ->assertJsonPath('data.admin.email', $this->admin()->email);

        $this->assertDatabaseHas('order_notes', [
            'id' => $response->json('data.id'),
            'order_id' => $order->id,
            'admin_id' => $this->admin()->id,
            'note' => 'Follow up tomorrow.',
        ]);

        $note = OrderNote::where('order_id', $order->id)->latest()->first();
        $this->assertSame('Follow up tomorrow.', $note->note);
        $this->assertSame($this->admin()->id, $note->admin_id);
    }

    public function test_note_is_required_and_limited_to_1000_chars(): void
    {
        $token = $this->login($this->admin());
        $order = $this->makeOrder();

        $this->withToken($token)->postJson('/api/v1/admin/orders/'.$order->id.'/notes', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['note'], 'data');

        $this->withToken($token)->postJson('/api/v1/admin/orders/'.$order->id.'/notes', [
            'note' => str_repeat('x', 1001),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['note'], 'data');
    }

    // ------------------------------------------------------------------
    // Statistics
    // ------------------------------------------------------------------

    public function test_statistics_aggregate_orders_and_revenue(): void
    {
        $token = $this->login($this->admin());

        $order = $this->makeOrder(['payment_status' => 'paid', 'total' => 123.45]);
        $this->makeOrder(['payment_status' => 'unpaid', 'total' => 9999.00]);

        $response = $this->withToken($token)->getJson('/api/v1/admin/orders/statistics')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'orders_by_status' => [
                        'pending', 'confirmed', 'processing', 'shipped',
                        'delivered', 'cancelled', 'refunded',
                    ],
                    'orders_by_payment_status' => ['unpaid', 'paid', 'refunded', 'failed'],
                    'total_orders', 'paid_orders_count', 'total_revenue', 'currency',
                ],
            ]);

        $this->assertSame(Order::count(), $response->json('data.total_orders'));
        $this->assertSame(Order::where('payment_status', 'paid')->count(), $response->json('data.paid_orders_count'));
        $this->assertSame(
            number_format(Order::where('payment_status', 'paid')->sum('total'), 2),
            $response->json('data.total_revenue')
        );
        $this->assertSame('USD', $response->json('data.currency'));
        $this->assertSame(Order::where('status', 'delivered')->count(), $response->json('data.orders_by_status.delivered'));
    }

    public function test_statistics_handle_empty_orders_table(): void
    {
        $token = $this->login($this->admin());

        DB::table('coupon_usages')->delete();
        DB::table('reviews')->delete();
        DB::table('notifications')->delete();
        DB::table('order_status_histories')->delete();
        DB::table('order_notes')->delete();
        DB::table('payments')->delete();
        DB::table('order_items')->delete();
        DB::table('orders')->delete();

        $response = $this->withToken($token)->getJson('/api/v1/admin/orders/statistics')
            ->assertStatus(200)
            ->assertJsonPath('data.total_orders', 0)
            ->assertJsonPath('data.paid_orders_count', 0)
            ->assertJsonPath('data.total_revenue', '0.00');

        foreach (['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'] as $key) {
            $this->assertSame(0, $response->json('data.orders_by_status.'.$key));
        }
    }

    // ------------------------------------------------------------------
    // Performance
    // ------------------------------------------------------------------

    public function test_order_list_is_eager_loaded_without_n_plus_one(): void
    {
        $token = $this->login($this->admin());
        $customer = $this->customer();
        $product = $this->product();

        for ($i = 0; $i < 8; $i++) {
            $order = $this->makeOrder(['user_id' => $customer->id]);
            $this->makeItem($order, $product, 1, 10.0);
            $this->makePayment($order);
            $this->makeHistory($order, 'pending', 'confirmed');
            $this->makeNote($order);
        }

        DB::enableQueryLog();

        $this->withToken($token)->getJson('/api/v1/admin/orders?per_page=50')->assertStatus(200);

        $this->assertLessThan(25, count(DB::getQueryLog()), 'Order list must eager-load relations.');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    protected function admin(): User
    {
        return User::where('email', 'admin@organicstore.test')->firstOrFail();
    }

    protected function staff(): User
    {
        return User::where('email', 'staff@organicstore.test')->firstOrFail();
    }

    protected function customer(): User
    {
        return User::where('email', 'maria@example.com')->firstOrFail();
    }

    protected function product(array $attributes = []): Product
    {
        $attributes['category_id'] = $attributes['category_id'] ?? \App\Models\Category::factory()->create()->id;
        $attributes['stock_quantity'] = $attributes['stock_quantity'] ?? 10;

        return Product::factory()->active()->create($attributes);
    }

    protected function makeOrder(array $attributes = []): Order
    {
        $customer = $this->customer();
        $address = $customer->addresses()->first()
            ?? Address::factory()->create(['user_id' => $customer->id]);

        return Order::create(array_merge([
            'user_id' => $customer->id,
            'address_id' => $address->id,
            'order_number' => 'ORD-T-'.now()->format('YmdHis').'-'.str_pad((string) ++$this->orderCounter, 3, '0', STR_PAD_LEFT),
            'notes' => null,
            'subtotal' => 50.00,
            'discount' => 0.00,
            'shipping_fee' => 5.00,
            'tax' => 4.00,
            'total' => 59.00,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'placed_at' => now(),
        ], $attributes))->fresh();
    }

    protected function makeItem(Order $order, Product $product, int $qty = 1, float $price = 10.0): OrderItem
    {
        return OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'unit_price' => $price,
            'quantity' => $qty,
            'line_total' => $price * $qty,
        ]);
    }

    protected function makePayment(Order $order, array $attributes = []): Payment
    {
        return Payment::create(array_merge([
            'order_id' => $order->id,
            'payment_number' => 'PAY-T-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
            'payment_method' => 'cod',
            'gateway' => 'payway',
            'amount' => $order->total,
            'currency' => 'USD',
            'payment_status' => 'pending',
        ], $attributes));
    }

    protected function makeHistory(Order $order, string $old, string $new): OrderStatusHistory
    {
        return OrderStatusHistory::create([
            'order_id' => $order->id,
            'admin_id' => $this->admin()->id,
            'old_status' => $old,
            'new_status' => $new,
            'note' => null,
        ]);
    }

    protected function makeNote(Order $order, string $text = 'Internal note'): OrderNote
    {
        return OrderNote::create([
            'order_id' => $order->id,
            'admin_id' => $this->admin()->id,
            'note' => $text,
        ]);
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