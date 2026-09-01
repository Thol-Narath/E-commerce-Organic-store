<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\AdminOrderService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    // ------------------------------------------------------------------
    // Access control
    // ------------------------------------------------------------------

    public function test_unauthenticated_inventory_requests_require_login(): void
    {
        $this->getJson('/api/v1/admin/inventory')->assertStatus(401);
        $this->getJson('/api/v1/staff/inventory')->assertStatus(401);
        $this->getJson('/api/v1/admin/inventory/transactions')->assertStatus(401);
        $this->postJson('/api/v1/admin/inventory/adjust', ['product_id' => 1, 'quantity_change' => 1])
            ->assertStatus(401);
    }

    public function test_customer_cannot_access_inventory_routes(): void
    {
        $token = $this->login($this->customer());

        $this->withToken($token)->getJson('/api/v1/admin/inventory')->assertStatus(403);
        $this->withToken($token)->getJson('/api/v1/admin/inventory/transactions')->assertStatus(403);
        $this->withToken($token)->postJson('/api/v1/admin/inventory/adjust', [
            'product_id' => Product::first()->id,
            'quantity_change' => 1,
        ])->assertStatus(403);
    }

    public function test_staff_can_view_inventory_overview(): void
    {
        $token = $this->login($this->staff());

        $response = $this->withToken($token)->getJson('/api/v1/admin/inventory')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message',
                'data' => [
                    'items' => [[
                        'id', 'name', 'sku', 'stock_quantity', 'low_stock_threshold',
                        'is_low_stock', 'available', 'status',
                    ]],
                    'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ]);

        $this->assertTrue($response->json('data.pagination.total') > 0);
    }

    public function test_staff_cannot_view_the_admin_ledger(): void
    {
        $token = $this->login($this->staff());

        $this->withToken($token)->getJson('/api/v1/admin/inventory/transactions')->assertStatus(403);
    }

    public function test_admin_can_view_the_inventory_ledger(): void
    {
        $token = $this->login($this->admin());

        $response = $this->withToken($token)->getJson('/api/v1/admin/inventory/transactions')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message',
                'data' => [
                    'items' => [[
                        'id', 'type', 'quantity_change', 'stock_before', 'stock_after',
                        'reference_type', 'created_at',
                    ]],
                    'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ]);

        $this->assertTrue($response->json('data.pagination.total') > 0);
    }

    // ------------------------------------------------------------------
    // Manual adjustments
    // ------------------------------------------------------------------

    public function test_admin_can_adjust_stock_up_and_keeps_ledger(): void
    {
        $token = $this->login($this->admin());
        $product = $this->product(['stock_quantity' => 8, 'low_stock_threshold' => 5]);

        $response = $this->withToken($token)->postJson('/api/v1/admin/inventory/adjust', [
            'product_id' => $product->id,
            'quantity_change' => 5,
            'type' => 'purchase',
            'reason' => 'Weekly restock from the farm.',
        ])->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product.stock_quantity', 13)
            ->assertJsonPath('data.transaction.quantity_change', 5)
            ->assertJsonPath('data.transaction.stock_before', 8)
            ->assertJsonPath('data.transaction.stock_after', 13)
            ->assertJsonPath('data.transaction.type', 'purchase');

        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity_change' => 5,
            'stock_before' => 8,
            'stock_after' => 13,
        ]);

        $this->assertSame(13, $product->fresh()->stock_quantity);
        $this->assertTrue($response->json('data.transaction.actor.id') === $this->admin()->id);
    }

    public function test_staff_can_adjust_stock_under_the_staff_prefix(): void
    {
        $token = $this->login($this->staff());
        $product = $this->product(['stock_quantity' => 5]);

        $this->withToken($token)->postJson('/api/v1/staff/inventory/adjust', [
            'product_id' => $product->id,
            'quantity_change' => -2,
            'reason' => 'Damage write-off.',
        ])->assertStatus(200)
            ->assertJsonPath('data.product.stock_quantity', 3)
            ->assertJsonPath('data.transaction.quantity_change', -2)
            ->assertJsonPath('data.transaction.type', 'adjustment');
    }

    public function test_adjust_never_allows_negative_stock(): void
    {
        $token = $this->login($this->admin());
        $product = $this->product(['stock_quantity' => 3]);

        $this->withToken($token)->postJson('/api/v1/admin/inventory/adjust', [
            'product_id' => $product->id,
            'quantity_change' => -4,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['quantity_change'], 'data');

        $this->assertSame(3, $product->fresh()->stock_quantity);
        $transactions = InventoryTransaction::where('product_id', $product->id)->count();
        $this->assertSame(0, $transactions, 'No ledger entry may be written for a rejected adjustment.');
    }

    public function test_adjust_rejects_unknown_product(): void
    {
        $token = $this->login($this->admin());

        $this->withToken($token)->postJson('/api/v1/admin/inventory/adjust', [
            'product_id' => 999999,
            'quantity_change' => 1,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['product_id'], 'data');
    }

    public function test_adjust_requires_a_nonzero_change(): void
    {
        $token = $this->login($this->admin());
        $product = $this->product();

        $this->withToken($token)->postJson('/api/v1/admin/inventory/adjust', [
            'product_id' => $product->id,
            'quantity_change' => 0,
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['quantity_change'], 'data');
    }

    public function test_purchase_type_cannot_decrease_stock(): void
    {
        $token = $this->login($this->admin());
        $product = $this->product(['stock_quantity' => 5]);

        $this->withToken($token)->postJson('/api/v1/admin/inventory/adjust', [
            'product_id' => $product->id,
            'quantity_change' => -1,
            'type' => 'purchase',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['type'], 'data');

        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    // ------------------------------------------------------------------
    // Low-stock notifications
    // ------------------------------------------------------------------

    public function test_crossing_the_low_stock_threshold_notifies_admins(): void
    {
        $token = $this->login($this->staff());
        $product = $this->product(['stock_quantity' => 10, 'low_stock_threshold' => 5]);

        $before = Notification::where('user_id', $this->admin()->id)
            ->where('type', 'low_stock')->count();

        $this->withToken($token)->postJson('/api/v1/admin/inventory/adjust', [
            'product_id' => $product->id,
            'quantity_change' => -6,
            'reason' => 'Sold during a promotion.',
        ])->assertStatus(200)
            ->assertJsonPath('data.product.stock_quantity', 4)
            ->assertJsonPath('data.product.is_low_stock', true);

        $this->assertSame(
            $before + 1,
            Notification::where('user_id', $this->admin()->id)->where('type', 'low_stock')->count()
        );

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->admin()->id,
            'type' => 'low_stock',
            'title' => 'Low stock: '.$product->name,
        ]);
    }

    public function test_adjusting_while_already_low_does_not_notify_again(): void
    {
        $token = $this->login($this->admin());
        $product = $this->product(['stock_quantity' => 3, 'low_stock_threshold' => 5]);

        $before = Notification::where('user_id', $this->admin()->id)
            ->where('type', 'low_stock')->count();

        $this->withToken($token)->postJson('/api/v1/admin/inventory/adjust', [
            'product_id' => $product->id,
            'quantity_change' => -1,
        ])->assertStatus(200);

        $this->assertSame(
            $before,
            Notification::where('user_id', $this->admin()->id)->where('type', 'low_stock')->count()
        );
    }

    // ------------------------------------------------------------------
    // Overview filters
    // ------------------------------------------------------------------

    public function test_inventory_overview_filters_low_stock_products(): void
    {
        $token = $this->login($this->admin());
        $low = $this->product(['name' => 'Low Stock Apples', 'stock_quantity' => 2, 'low_stock_threshold' => 5]);
        $healthy = $this->product(['name' => 'Healthy Oranges', 'stock_quantity' => 50, 'low_stock_threshold' => 5]);

        $response = $this->withToken($token)->getJson('/api/v1/admin/inventory?low_stock=true&per_page=50')
            ->assertStatus(200);

        $ids = collect($response->json('data.items'))->pluck('id')->all();
        $this->assertContains($low->id, $ids);
        $this->assertNotContains($healthy->id, $ids);

        foreach ($response->json('data.items') as $item) {
            $this->assertSame(true, $item['is_low_stock']);
        }
    }

    // ------------------------------------------------------------------
    // Phase 10 — Access control (new per-product endpoints)
    // ------------------------------------------------------------------

    public function test_guest_cannot_access_inventory_management_endpoints(): void
    {
        $product = $this->product();

        $this->getJson('/api/v1/admin/inventory/statistics')->assertStatus(401);
        $this->getJson('/api/v1/admin/inventory/'.$product->id)->assertStatus(401);
        $this->getJson('/api/v1/admin/inventory/'.$product->id.'/transactions')->assertStatus(401);
        $this->postJson('/api/v1/admin/inventory/'.$product->id.'/add', ['quantity' => 1])->assertStatus(401);
        $this->postJson('/api/v1/admin/inventory/'.$product->id.'/remove', ['quantity' => 1, 'reason' => 'damage'])->assertStatus(401);
        $this->postJson('/api/v1/admin/inventory/'.$product->id.'/adjust', ['quantity' => 5, 'reason' => 'count'])->assertStatus(401);
        $this->patchJson('/api/v1/admin/inventory/'.$product->id.'/reorder-level', ['reorder_level' => 5])->assertStatus(401);
    }

    public function test_customer_cannot_access_inventory_management_endpoints(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product();

        $this->withToken($token)->getJson('/api/v1/admin/inventory/statistics')->assertStatus(403);
        $this->withToken($token)->getJson('/api/v1/admin/inventory/'.$product->id)->assertStatus(403);
        $this->withToken($token)->getJson('/api/v1/admin/inventory/'.$product->id.'/transactions')->assertStatus(403);
        $this->withToken($token)->postJson('/api/v1/admin/inventory/'.$product->id.'/add', ['quantity' => 1])->assertStatus(403);
        $this->withToken($token)->postJson('/api/v1/admin/inventory/'.$product->id.'/adjust', ['quantity' => 5, 'reason' => 'count'])->assertStatus(403);
        $this->withToken($token)->patchJson('/api/v1/admin/inventory/'.$product->id.'/reorder-level', ['reorder_level' => 5])->assertStatus(403);
    }

    public function test_staff_can_view_detail_and_statistics_but_not_the_product_ledger(): void
    {
        $token = $this->login($this->staff());
        $product = $this->product();

        $this->withToken($token)->getJson('/api/v1/admin/inventory/statistics')
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'data' => ['total_products', 'in_stock', 'low_stock', 'out_of_stock', 'total_units']]);

        $this->withToken($token)->getJson('/api/v1/admin/inventory/'.$product->id)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $product->id);

        $this->withToken($token)->getJson('/api/v1/admin/inventory/'.$product->id.'/transactions')
            ->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // Phase 10 — Add / remove / adjust stock
    // ------------------------------------------------------------------

    public function test_add_stock_creates_purchase_ledger_entry(): void
    {
        $token = $this->login($this->admin());
        $product = $this->product(['stock_quantity' => 25, 'low_stock_threshold' => 5]);

        $this->withToken($token)->postJson('/api/v1/admin/inventory/'.$product->id.'/add', [
            'quantity' => 20,
            'reason' => 'Received from supplier.',
        ])->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product.stock_quantity', 45)
            ->assertJsonPath('data.transaction.type', 'purchase')
            ->assertJsonPath('data.transaction.quantity_change', 20)
            ->assertJsonPath('data.transaction.stock_before', 25)
            ->assertJsonPath('data.transaction.stock_after', 45)
            ->assertJsonPath('data.transaction.actor.id', $this->admin()->id)
            ->assertJsonPath('data.transaction.notes', 'Received from supplier.');

        $this->assertSame(45, $product->fresh()->stock_quantity);
        $this->assertDatabaseHas('inventory_transactions', [
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity_change' => 20,
            'stock_before' => 25,
            'stock_after' => 45,
        ]);
    }

    public function test_add_stock_rejects_zero_or_negative_quantity(): void
    {
        $token = $this->login($this->admin());
        $product = $this->product(['stock_quantity' => 5]);

        $this->withToken($token)->postJson('/api/v1/admin/inventory/'.$product->id.'/add', ['quantity' => 0])
            ->assertStatus(422)->assertJsonValidationErrors(['quantity'], 'data');
        $this->withToken($token)->postJson('/api/v1/admin/inventory/'.$product->id.'/add', ['quantity' => -3])
            ->assertStatus(422)->assertJsonValidationErrors(['quantity'], 'data');

        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_remove_stock_records_reason_and_adjustment(): void
    {
        $token = $this->login($this->admin());
        $product = $this->product(['stock_quantity' => 50]);

        $this->withToken($token)->postJson('/api/v1/admin/inventory/'.$product->id.'/remove', [
            'quantity' => 5,
            'reason' => 'damage',
        ])->assertStatus(200)
            ->assertJsonPath('data.product.stock_quantity', 45)
            ->assertJsonPath('data.transaction.type', 'adjustment')
            ->assertJsonPath('data.transaction.quantity_change', -5)
            ->assertJsonPath('data.transaction.stock_before', 50)
            ->assertJsonPath('data.transaction.stock_after', 45)
            ->assertJsonPath('data.transaction.notes', 'damage');
    }

    public function test_remove_stock_cannot_go_negative(): void
    {
        $token = $this->login($this->admin());
        $product = $this->product(['stock_quantity' => 5]);

        $this->withToken($token)->postJson('/api/v1/admin/inventory/'.$product->id.'/remove', [
            'quantity' => 10,
            'reason' => 'loss',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['quantity_change'], 'data');

        $this->assertSame(5, $product->fresh()->stock_quantity);
        $this->assertSame(0, InventoryTransaction::where('product_id', $product->id)->count(), 'No ledger entry on reject.');
    }

    public function test_remove_stock_requires_a_reason(): void
    {
        $token = $this->login($this->admin());
        $product = $this->product(['stock_quantity' => 5]);

        $this->withToken($token)->postJson('/api/v1/admin/inventory/'.$product->id.'/remove', ['quantity' => 1])
            ->assertStatus(422)->assertJsonValidationErrors(['reason'], 'data');
    }

    public function test_adjust_stock_sets_absolute_target_and_records_delta(): void
    {
        $token = $this->login($this->admin());
        $product = $this->product(['stock_quantity' => 20]);

        $this->withToken($token)->postJson('/api/v1/admin/inventory/'.$product->id.'/adjust', [
            'quantity' => 15,
            'reason' => 'Physical inventory count.',
        ])->assertStatus(200)
            ->assertJsonPath('data.product.stock_quantity', 15)
            ->assertJsonPath('data.transaction.type', 'adjustment')
            ->assertJsonPath('data.transaction.quantity_change', -5)
            ->assertJsonPath('data.transaction.stock_before', 20)
            ->assertJsonPath('data.transaction.stock_after', 15)
            ->assertJsonPath('data.transaction.notes', 'Physical inventory count.');

        // Adjust up from the same base (stock 15 -> 25, delta +10).
        $this->withToken($token)->postJson('/api/v1/admin/inventory/'.$product->id.'/adjust', [
            'quantity' => 25,
            'reason' => 'Count correction.',
        ])->assertStatus(200)
            ->assertJsonPath('data.product.stock_quantity', 25)
            ->assertJsonPath('data.transaction.quantity_change', 10)
            ->assertJsonPath('data.transaction.stock_before', 15)
            ->assertJsonPath('data.transaction.stock_after', 25);
    }

    public function test_adjust_stock_requires_a_reason(): void
    {
        $token = $this->login($this->admin());
        $product = $this->product(['stock_quantity' => 5]);

        $this->withToken($token)->postJson('/api/v1/admin/inventory/'.$product->id.'/adjust', ['quantity' => 5])
            ->assertStatus(422)->assertJsonValidationErrors(['reason'], 'data');
    }

    public function test_adjust_stock_rejects_negative_target(): void
    {
        $token = $this->login($this->admin());
        $product = $this->product(['stock_quantity' => 5]);

        $this->withToken($token)->postJson('/api/v1/admin/inventory/'.$product->id.'/adjust', [
            'quantity' => -1,
            'reason' => 'bad',
        ])->assertStatus(422)->assertJsonValidationErrors(['quantity'], 'data');
    }

    public function test_update_reorder_level_changes_low_stock_signal_without_touching_stock(): void
    {
        $token = $this->login($this->admin());
        $product = $this->product(['stock_quantity' => 8, 'low_stock_threshold' => 5]);

        $this->withToken($token)->patchJson('/api/v1/admin/inventory/'.$product->id.'/reorder-level', [
            'reorder_level' => 10,
        ])->assertStatus(200)
            ->assertJsonPath('data.reorder_level', 10)
            ->assertJsonPath('data.low_stock_threshold', 10)
            ->assertJsonPath('data.stock_status', 'low_stock');

        $this->assertSame(8, $product->fresh()->stock_quantity, 'Reorder level must not change stock.');
        $this->assertSame(10, $product->fresh()->low_stock_threshold);
    }

    public function test_reorder_level_rejects_negative(): void
    {
        $token = $this->login($this->admin());
        $product = $this->product();

        $this->withToken($token)->patchJson('/api/v1/admin/inventory/'.$product->id.'/reorder-level', [
            'reorder_level' => -5,
        ])->assertStatus(422)->assertJsonValidationErrors(['reorder_level'], 'data');
    }

    // ------------------------------------------------------------------
    // Phase 10 — Statistics, detail, ledger, filters
    // ------------------------------------------------------------------

    public function test_inventory_statistics_match_the_database(): void
    {
        $token = $this->login($this->admin());

        $expectedIn = Product::withTrashed()->whereColumn('stock_quantity', '>', 'low_stock_threshold')->count();
        $expectedLow = Product::withTrashed()->where('stock_quantity', '>', 0)
            ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')->count();
        $expectedOut = Product::withTrashed()->where('stock_quantity', 0)->count();
        $expectedUnits = (int) Product::withTrashed()->sum('stock_quantity');
        $expectedTotal = $expectedIn + $expectedLow + $expectedOut;

        $this->withToken($token)->getJson('/api/v1/admin/inventory/statistics')
            ->assertStatus(200)
            ->assertJsonPath('data.total_products', $expectedTotal)
            ->assertJsonPath('data.in_stock', $expectedIn)
            ->assertJsonPath('data.low_stock', $expectedLow)
            ->assertJsonPath('data.out_of_stock', $expectedOut)
            ->assertJsonPath('data.total_units', $expectedUnits);
    }

    public function test_product_inventory_detail_shape(): void
    {
        $token = $this->login($this->admin());
        $product = $this->product(['name' => 'Detail Apples', 'sku' => 'DET001', 'stock_quantity' => 12, 'low_stock_threshold' => 5]);

        $this->withToken($token)->getJson('/api/v1/admin/inventory/'.$product->id)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.sku', 'DET001')
            ->assertJsonPath('data.stock_quantity', 12)
            ->assertJsonPath('data.reorder_level', 5)
            ->assertJsonPath('data.stock_status', 'in_stock')
            ->assertJsonStructure(['data' => ['id', 'name', 'sku', 'category', 'price', 'stock_quantity', 'low_stock_threshold', 'reorder_level', 'stock_status', 'primary_image', 'last_inventory_transaction_at']]);
    }

    public function test_inventory_detail_returns_404_for_unknown_product(): void
    {
        $token = $this->login($this->admin());

        $this->withToken($token)->getJson('/api/v1/admin/inventory/999999')->assertStatus(404);
        $this->withToken($token)->getJson('/api/v1/admin/inventory/not-a-number')->assertStatus(404);
    }

    public function test_product_transactions_are_paginated_and_filtered_by_type(): void
    {
        $token = $this->login($this->admin());
        $product = $this->product(['stock_quantity' => 5]);

        $this->withToken($token)->postJson('/api/v1/admin/inventory/'.$product->id.'/add', ['quantity' => 10, 'reason' => 'restock']);
        $this->withToken($token)->postJson('/api/v1/admin/inventory/'.$product->id.'/remove', ['quantity' => 2, 'reason' => 'expired']);

        $all = $this->withToken($token)->getJson('/api/v1/admin/inventory/'.$product->id.'/transactions?per_page=50')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['items' => [['id', 'type', 'quantity_change', 'stock_before', 'stock_after', 'actor', 'created_at']], 'pagination']]);

        $purchases = $this->withToken($token)->getJson('/api/v1/admin/inventory/'.$product->id.'/transactions?type=purchase&per_page=50')
            ->assertStatus(200);

        $this->assertSame(2, count($all->json('data.items')));
        $this->assertSame(1, count($purchases->json('data.items')));
        $this->assertSame('purchase', $purchases->json('data.items.0.type'));
    }

    public function test_inventory_search_matches_name_sku_and_id(): void
    {
        $token = $this->login($this->admin());
        $byName = $this->product(['name' => 'Ember Apples', 'sku' => 'EBR001', 'stock_quantity' => 7]);
        $bySku = $this->product(['name' => 'Pineapples', 'sku' => 'PNA999', 'stock_quantity' => 7]);

        $byNameResponse = $this->withToken($token)->getJson('/api/v1/admin/inventory?search=Ember&per_page=50')->assertStatus(200);
        $this->assertContains($byName->id, collect($byNameResponse->json('data.items'))->pluck('id')->all());

        $bySkuResponse = $this->withToken($token)->getJson('/api/v1/admin/inventory?search=PNA999&per_page=50')->assertStatus(200);
        $this->assertContains($bySku->id, collect($bySkuResponse->json('data.items'))->pluck('id')->all());

        $byIdResponse = $this->withToken($token)->getJson('/api/v1/admin/inventory?search='.$byName->id.'&per_page=50')->assertStatus(200);
        $this->assertContains($byName->id, collect($byIdResponse->json('data.items'))->pluck('id')->all());
    }

    public function test_inventory_stock_status_filter(): void
    {
        $token = $this->login($this->admin());
        $in = $this->product(['name' => 'Stocky Apples', 'stock_quantity' => 50, 'low_stock_threshold' => 5]);
        $low = $this->product(['name' => 'Low Apples', 'stock_quantity' => 3, 'low_stock_threshold' => 5]);
        $out = $this->product(['name' => 'Empty Apples', 'stock_quantity' => 0, 'low_stock_threshold' => 5]);

        $lowResponse = $this->withToken($token)->getJson('/api/v1/admin/inventory?stock=low_stock&per_page=50')->assertStatus(200);
        $lowIds = collect($lowResponse->json('data.items'))->pluck('id')->all();
        $this->assertContains($low->id, $lowIds);
        $this->assertNotContains($in->id, $lowIds);
        $this->assertNotContains($out->id, $lowIds);

        $outResponse = $this->withToken($token)->getJson('/api/v1/admin/inventory?stock=out_of_stock&per_page=50')->assertStatus(200);
        $outIds = collect($outResponse->json('data.items'))->pluck('id')->all();
        $this->assertContains($out->id, $outIds);
        $this->assertNotContains($low->id, $outIds);

        $inResponse = $this->withToken($token)->getJson('/api/v1/admin/inventory?stock=in_stock&per_page=50')->assertStatus(200);
        foreach ($inResponse->json('data.items') as $item) {
            $this->assertSame('in_stock', $item['stock_status']);
        }
    }

    public function test_inventory_sorting_and_pagination(): void
    {
        $token = $this->login($this->admin());
        $a = $this->product(['name' => 'AAA Corn', 'stock_quantity' => 2]);
        $b = $this->product(['name' => 'ZZZ Corn', 'stock_quantity' => 99]);

        $byStock = $this->withToken($token)->getJson('/api/v1/admin/inventory?sort=stock_high&per_page=50')->assertStatus(200);
        $idsByStock = collect($byStock->json('data.items'))->pluck('id')->all();
        $this->assertLessThan(array_search($a->id, $idsByStock), array_search($b->id, $idsByStock), 'stock_high must rank high stock first.');

        $byName = $this->withToken($token)->getJson('/api/v1/admin/inventory?sort=name_asc&per_page=50')->assertStatus(200);
        $idsByName = collect($byName->json('data.items'))->pluck('id')->all();
        $this->assertLessThan(array_search($b->id, $idsByName), array_search($a->id, $idsByName), 'name_asc must rank A before Z.');

        $default = $this->withToken($token)->getJson('/api/v1/admin/inventory')->assertStatus(200);
        $this->assertSame(20, $default->json('data.pagination.per_page'));

        $custom = $this->withToken($token)->getJson('/api/v1/admin/inventory?per_page=3')->assertStatus(200);
        $this->assertSame(3, $custom->json('data.pagination.per_page'));
        $this->assertLessThanOrEqual(3, count($custom->json('data.items')));
    }

    // ------------------------------------------------------------------
    // Phase 10 — Order/payment lifecycle idempotency
    // ------------------------------------------------------------------

    public function test_payment_confirmation_never_deducts_stock_a_second_time(): void
    {
        $product = $this->product(['stock_quantity' => 10]);
        $order = $this->placeOrder($this->customer(), $product, 2);

        $saleCount = InventoryTransaction::where('product_id', $product->id)->where('type', 'sale')->count();
        $this->assertSame(1, $saleCount, 'Checkout writes exactly one sale entry.');
        $this->assertSame(8, $product->fresh()->stock_quantity);

        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'payment_method' => 'aba_pay',
            'amount' => (float) $order->total,
            'payment_status' => 'pending',
        ]);

        $service = app(PaymentService::class);
        $service->confirmPayment($payment);
        $service->confirmPayment($payment);

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame(8, $product->fresh()->stock_quantity, 'Confirming payment must not deduct stock again.');
        $this->assertSame(1, InventoryTransaction::where('product_id', $product->id)->where('type', 'sale')->count());
    }

    public function test_duplicate_cancellation_never_restores_stock_twice(): void
    {
        $product = $this->product(['stock_quantity' => 10]);
        $order = $this->placeOrder($this->customer(), $product, 2);
        $this->assertSame(8, $product->fresh()->stock_quantity);

        $admin = $this->admin();
        $service = app(AdminOrderService::class);

        $service->cancel($order, $admin);
        $this->assertSame(10, $product->fresh()->stock_quantity);
        $this->assertSame(1, InventoryTransaction::where('product_id', $product->id)->where('type', 'return')->count());

        try {
            $service->cancel($order->fresh(), $admin);
            $this->fail('A second cancellation must be rejected.');
        } catch (ValidationException $e) {
            $this->assertSame('Only pending, confirmed or processing orders can be cancelled.', $e->errors()['status'][0]);
        }

        $this->assertSame(10, $product->fresh()->stock_quantity, 'Duplicate cancellation must not restore stock twice.');
        $this->assertSame(1, InventoryTransaction::where('product_id', $product->id)->where('type', 'return')->count());
    }

    public function test_sequential_deductions_cannot_oversell(): void
    {
        $token = $this->login($this->admin());
        $product = $this->product(['stock_quantity' => 3]);

        $this->withToken($token)->postJson('/api/v1/admin/inventory/'.$product->id.'/remove', [
            'quantity' => 2,
            'reason' => 'damage',
        ])->assertStatus(200)->assertJsonPath('data.product.stock_quantity', 1);

        $this->withToken($token)->postJson('/api/v1/admin/inventory/'.$product->id.'/remove', [
            'quantity' => 5,
            'reason' => 'loss',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['quantity_change'], 'data');

        $this->assertSame(1, $product->fresh()->stock_quantity);
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

    protected function placeOrder(User $user, Product $product, int $quantity): Order
    {
        $address = Address::factory()->for($user)->create(['is_default' => true]);

        $cart = $user->cart()->where('status', 'active')->first()
            ?? Cart::factory()->for($user)->create(['status' => 'active']);

        CartItem::factory()->for($cart)->for($product)->create(['quantity' => $quantity]);

        $order = app(OrderService::class)->placeOrder($user, $address->id);
        $this->assertNotNull($order);

        return $order;
    }
}