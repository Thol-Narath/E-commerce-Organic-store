<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Review;
use App\Models\Setting;
use App\Models\User;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseStructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_all_expected_tables_exist(): void
    {
        $tables = [
            'users', 'categories', 'products', 'product_images', 'addresses',
            'carts', 'cart_items', 'wishlists', 'wishlist_items', 'orders',
            'order_items', 'payments', 'reviews', 'coupons', 'coupon_usages',
            'inventory_transactions', 'notifications', 'settings',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(
                DB::getSchemaBuilder()->hasTable($table),
                "Expected table '{$table}' to exist."
            );
        }
    }

    public function test_required_unique_constraints_are_enforced(): void
    {
        $this->expectException(QueryException::class);
        Category::create([
            'name' => 'Duplicate',
            'slug' => 'fresh-vegetables', // already exists from seed
            'status' => 'active',
        ]);
    }

    public function test_products_slug_unique_is_enforced(): void
    {
        $product = Product::first();

        $this->expectException(QueryException::class);
        Product::create([
            'category_id' => $product->category_id,
            'name' => 'Duplicate Product',
            'slug' => $product->slug,
            'sku' => 'ORG-DUP-001',
            'price' => 1.00,
            'status' => 'active',
        ]);
    }

    public function test_one_review_per_product_per_customer(): void
    {
        $review = Review::first();

        $this->expectException(QueryException::class);
        Review::create([
            'product_id' => $review->product_id,
            'user_id' => $review->user_id,
            'order_id' => $review->order_id,
            'rating' => 5,
            'status' => 'approved',
        ]);
    }

    public function test_foreign_key_restrict_prevents_product_delete_with_orders(): void
    {
        $product = Product::first();

        if ($product->orderItems()->exists()) {
            $this->expectException(QueryException::class);
            $product->forceDelete();
        } else {
            $this->assertTrue(true);
        }
    }

    public function test_model_relationships_resolve(): void
    {
        $user = User::where('role', 'customer')->first();

        $this->assertInstanceOf(Address::class, $user->addresses()->first());
        $this->assertInstanceOf(Cart::class, $user->cart);

        if ($user->orders()->exists()) {
            $order = $user->orders()->first();
            $this->assertInstanceOf(Order::class, $order);
            $this->assertInstanceOf(OrderItem::class, $order->items()->first());
        }

        $product = Product::first();
        $this->assertInstanceOf(Category::class, $product->category);
        $this->assertInstanceOf(ProductImage::class, $product->images()->first());
        $this->assertTrue($product->inventoryTransactions()->count() >= 1);
    }

    public function test_seeded_roles_and_counts(): void
    {
        $this->assertEquals(1, User::where('role', 'admin')->count());
        $this->assertEquals(1, User::where('role', 'staff')->count());
        $this->assertEquals(5, User::where('role', 'customer')->count());
        $this->assertGreaterThanOrEqual(8, Category::count());
        $this->assertEquals(20, Product::count());
        $this->assertGreaterThan(0, Coupon::count());
        $this->assertGreaterThan(0, Setting::count());
        $this->assertGreaterThan(0, Order::count());
        $this->assertGreaterThan(0, Review::count());
    }

    public function test_order_totals_are_reconciled(): void
    {
        foreach (Order::all() as $order) {
            $reconciled = round($order->subtotal + $order->shipping_fee + $order->tax - $order->discount, 2);
            $this->assertEqualsWithDelta($reconciled, $order->total, 0.01, "Order {$order->order_number} total mismatch.");
        }
    }

    public function test_no_negative_stock(): void
    {
        $this->assertSame(0, Product::where('stock_quantity', '<', 0)->count());
    }

    public function test_inventory_ledger_is_consistent_with_stock(): void
    {
        foreach (Product::all() as $product) {
            $last = $product->inventoryTransactions()->orderByDesc('id')->first();
            if ($last) {
                $this->assertEquals($product->stock_quantity, $last->stock_after);
            }
        }
    }

    public function test_reviews_point_to_delivered_orders(): void
    {
        foreach (Review::all() as $review) {
            $this->assertEquals('delivered', $review->order->status);
            $this->assertTrue(
                $review->order->items()->where('product_id', $review->product_id)->exists(),
                'Review references a product that was in the order.'
            );
        }
    }

    public function test_coupon_usage_links_are_valid(): void
    {
        foreach (CouponUsage::all() as $usage) {
            $this->assertTrue($usage->coupon->exists);
            $this->assertTrue($usage->user->exists);
            $this->assertTrue($usage->order->exists);
        }
    }
}
