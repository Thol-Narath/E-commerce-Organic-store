<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    // ------------------------------------------------------------------
    // Access & authorization
    // ------------------------------------------------------------------

    public function test_unauthenticated_user_receives_401_for_cart(): void
    {
        $this->getJson('/api/v1/cart')->assertStatus(401);
        $this->postJson('/api/v1/cart/items', ['product_id' => 1, 'quantity' => 1])->assertStatus(401);
        $this->deleteJson('/api/v1/cart')->assertStatus(401);
        $this->patchJson('/api/v1/cart/items/1', ['quantity' => 2])->assertStatus(401);
    }

    public function test_authenticated_customer_can_retrieve_their_cart(): void
    {
        $token = $this->login($this->customer());

        $this->withToken($token)->getJson('/api/v1/cart')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.subtotal', '0.00')
            ->assertJsonPath('data.total_items', 0)
            ->assertJsonMissingPath('data.user_id');
    }

    public function test_auth_cannot_see_another_customers_cart_items(): void
    {
        $owner = $this->customer();
        $other = $this->otherCustomer();

        $product = $this->product();
        $this->login($owner);
        $ownerCartItem = Cart::where('user_id', $owner->id)->first()->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $otherToken = $this->login($other);

        $response = $this->withToken($otherToken)->getJson('/api/v1/cart');

        $response->assertStatus(200)->assertJsonPath('data.total_items', 0);
        $this->assertCount(0, $response->json('data.items'));
        $this->assertNotContains($ownerCartItem->id, collect($response->json('data.items'))->pluck('id')->all());
    }

    public function test_user_cannot_modify_another_customers_cart_item(): void
    {
        $owner = $this->customer();
        $other = $this->otherCustomer();

        $product = $this->product();
        $ownerCartItem = Cart::where('user_id', $owner->id)->first()->items()->create([
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $otherToken = $this->login($other);

        $this->withToken($otherToken)
            ->patchJson("/api/v1/cart/items/{$ownerCartItem->id}", ['quantity' => 5])
            ->assertStatus(404)
            ->assertJsonPath('success', false);

        $this->withToken($otherToken)
            ->deleteJson("/api/v1/cart/items/{$ownerCartItem->id}")
            ->assertStatus(404)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('cart_items', ['id' => $ownerCartItem->id, 'quantity' => 2]);
    }

    // ------------------------------------------------------------------
    // Add to cart
    // ------------------------------------------------------------------

    public function test_customer_can_add_product_to_cart(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product(['price' => 4.50, 'stock_quantity' => 10]);

        $this->withToken($token)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_items', 2)
            ->assertJsonPath('data.subtotal', '9.00')
            ->assertJsonPath('data.items.0.product.id', $product->id)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.unit_price', '4.50')
            ->assertJsonPath('data.items.0.line_total', '9.00');

        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 2]);
    }

    public function test_cannot_add_inactive_product(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product(['status' => 'inactive', 'stock_quantity' => 10]);

        $this->withToken($token)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['product_id'], 'data');

        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
    }

    public function test_cannot_add_nonexistent_product(): void
    {
        $token = $this->login($this->customer());

        $this->withToken($token)->postJson('/api/v1/cart/items', [
            'product_id' => 999999,
            'quantity' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors(['product_id'], 'data');
    }

    public function test_quantity_must_be_greater_than_zero(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product();

        $this->withToken($token)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 0,
        ])->assertStatus(422)->assertJsonValidationErrors(['quantity'], 'data');
    }

    public function test_cannot_add_more_than_available_stock(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product(['stock_quantity' => 5]);

        $this->withToken($token)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 6,
        ])->assertStatus(422)->assertJsonValidationErrors(['quantity'], 'data');

        $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
    }

    public function test_adding_same_product_updates_quantity_no_duplicate(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product(['stock_quantity' => 100]);

        $this->withToken($token)->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertStatus(201);

        $this->withToken($token)->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 3])
            ->assertStatus(201)
            ->assertJsonPath('data.total_items', 5)
            ->assertJsonPath('data.items.0.quantity', 5);

        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 5]);
    }

    public function test_adding_same_product_is_capped_by_stock(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product(['stock_quantity' => 3]);

        $this->withToken($token)->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertStatus(201);

        $this->withToken($token)->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity'], 'data');

        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 2]);
    }

    // ------------------------------------------------------------------
    // Update / remove / clear
    // ------------------------------------------------------------------

    public function test_customer_can_update_cart_item_quantity(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product(['stock_quantity' => 10]);

        $itemId = $this->withToken($token)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->json('data.items.0.id');

        $this->withToken($token)->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 3])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_items', 3)
            ->assertJsonPath('data.items.0.quantity', 3);

        $this->assertDatabaseHas('cart_items', ['id' => $itemId, 'quantity' => 3]);
    }

    public function test_update_rejects_quantity_above_stock(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product(['stock_quantity' => 4]);

        $itemId = $this->withToken($token)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->json('data.items.0.id');

        $this->withToken($token)->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 5])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['quantity'], 'data');

        $this->assertDatabaseHas('cart_items', ['id' => $itemId, 'quantity' => 2]);
    }

    public function test_customer_can_remove_cart_item(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product();

        $itemId = $this->withToken($token)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ])->json('data.items.0.id');

        $this->withToken($token)->deleteJson("/api/v1/cart/items/{$itemId}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_items', 0);

        $this->assertDatabaseMissing('cart_items', ['id' => $itemId]);
    }

    public function test_customer_can_clear_cart(): void
    {
        $token = $this->login($this->customer());
        $p1 = $this->product();
        $p2 = $this->product();

        $this->withToken($token)->postJson('/api/v1/cart/items', ['product_id' => $p1->id, 'quantity' => 1]);
        $this->withToken($token)->postJson('/api/v1/cart/items', ['product_id' => $p2->id, 'quantity' => 2]);

        $this->withToken($token)->deleteJson('/api/v1/cart')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_items', 0)
            ->assertJsonPath('data.items', []);

        $this->assertDatabaseCount('cart_items', 0);
    }

    // ------------------------------------------------------------------
    // Totals & prices
    // ------------------------------------------------------------------

    public function test_cart_totals_are_calculated_correctly(): void
    {
        $token = $this->login($this->customer());
        $p1 = $this->product(['price' => 2.00, 'stock_quantity' => 10]);
        $p2 = $this->product(['price' => 1.50, 'stock_quantity' => 10]);

        $this->withToken($token)->postJson('/api/v1/cart/items', ['product_id' => $p1->id, 'quantity' => 2]);
        $this->withToken($token)->postJson('/api/v1/cart/items', ['product_id' => $p2->id, 'quantity' => 3]);

        $this->withToken($token)->getJson('/api/v1/cart')
            ->assertStatus(200)
            ->assertJsonPath('data.total_items', 5)
            ->assertJsonPath('data.subtotal', '8.50')
            ->assertJsonPath('data.items.0.line_total', '4.00')
            ->assertJsonPath('data.items.1.line_total', '4.50');
    }

    public function test_cart_uses_live_product_price_not_a_stored_price(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product(['price' => 5.00, 'stock_quantity' => 10]);

        $itemId = $this->withToken($token)->postJson('/api/v1/cart/items', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->json('data.items.0.id');

        $this->assertDatabaseHas('cart_items', ['id' => $itemId, 'quantity' => 1]);

        $product->update(['price' => 6.50]);

        $this->withToken($token)->getJson('/api/v1/cart')
            ->assertJsonPath('data.items.0.unit_price', '6.50')
            ->assertJsonPath('data.items.0.line_total', '6.50')
            ->assertJsonPath('data.subtotal', '6.50');
    }

    public function test_inactive_product_in_cart_is_marked_unavailable(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product(['stock_quantity' => 10]);

        $itemId = $this->withToken($token)->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertStatus(201)
            ->json('data.items.0.id');

        $product->update(['status' => 'inactive']);

        $this->withToken($token)->getJson('/api/v1/cart')
            ->assertJsonPath('data.items.0.available', false);

        $this->withToken($token)->patchJson("/api/v1/cart/items/{$itemId}", ['quantity' => 2])
            ->assertStatus(422);
    }

    // ------------------------------------------------------------------
    // Resource structure
    // ------------------------------------------------------------------

    public function test_cart_resource_returns_expected_structure(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product(['price' => 3.00, 'stock_quantity' => 10]);

        $this->withToken($token)->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 1]);

        $this->withToken($token)->getJson('/api/v1/cart')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success', 'message',
                'data' => [
                    'id',
                    'items' => [[
                        'id',
                        'quantity',
                        'product' => ['id', 'name', 'slug', 'price'],
                        'unit_price',
                        'line_total',
                        'available',
                    ]],
                    'subtotal',
                    'total_items',
                ],
            ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

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