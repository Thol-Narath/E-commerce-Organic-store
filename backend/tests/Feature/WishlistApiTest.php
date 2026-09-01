<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Wishlist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistApiTest extends TestCase
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

    public function test_unauthenticated_user_receives_401_for_wishlist(): void
    {
        $this->getJson('/api/v1/wishlist')->assertStatus(401);
        $this->postJson('/api/v1/wishlist/items', ['product_id' => 1])->assertStatus(401);
        $this->deleteJson('/api/v1/wishlist/items/1')->assertStatus(401);
        $this->postJson('/api/v1/wishlist/items/1/move-to-cart')->assertStatus(401);
    }

    public function test_authenticated_customer_can_retrieve_their_wishlist(): void
    {
        $token = $this->login($this->customer());

        $this->withToken($token)->getJson('/api/v1/wishlist')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.total_items', 0)
            ->assertJsonMissingPath('data.user_id');
    }

    public function test_customer_cannot_see_another_customers_wishlist(): void
    {
        $owner = $this->customer();
        $other = $this->otherCustomer();

        $product = $this->product();
        Wishlist::where('user_id', $owner->id)->first()->items()->create(['product_id' => $product->id]);
        $ownerItemId = Wishlist::where('user_id', $owner->id)->first()->items()->first()->id;

        $otherToken = $this->login($other);

        $this->withToken($otherToken)->getJson('/api/v1/wishlist')
            ->assertJsonPath('data.total_items', 0);

        $this->withToken($otherToken)->deleteJson("/api/v1/wishlist/items/{$ownerItemId}")
            ->assertStatus(404)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('wishlist_items', ['id' => $ownerItemId]);
    }

    // ------------------------------------------------------------------
    // Add / duplicate / remove
    // ------------------------------------------------------------------

    public function test_customer_can_add_product_to_wishlist(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product();

        $this->withToken($token)->postJson('/api/v1/wishlist/items', ['product_id' => $product->id])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_items', 1)
            ->assertJsonPath('data.items.0.product.id', $product->id);

        $this->assertDatabaseHas('wishlist_items', ['product_id' => $product->id]);
    }

    public function test_duplicate_wishlist_product_is_prevented(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product();

        $this->withToken($token)->postJson('/api/v1/wishlist/items', ['product_id' => $product->id])
            ->assertStatus(201);

        $this->withToken($token)->postJson('/api/v1/wishlist/items', ['product_id' => $product->id])
            ->assertStatus(201)
            ->assertJsonPath('data.total_items', 1);

        $this->assertDatabaseCount('wishlist_items', 1);
    }

    public function test_cannot_add_inactive_product_to_wishlist(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product(['status' => 'inactive']);

        $this->withToken($token)->postJson('/api/v1/wishlist/items', ['product_id' => $product->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_id'], 'data');

        $this->assertDatabaseMissing('wishlist_items', ['product_id' => $product->id]);
    }

    public function test_cannot_add_nonexistent_product_to_wishlist(): void
    {
        $token = $this->login($this->customer());

        $this->withToken($token)->postJson('/api/v1/wishlist/items', ['product_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_id'], 'data');
    }

    public function test_customer_can_remove_wishlist_item(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product();

        $itemId = $this->withToken($token)->postJson('/api/v1/wishlist/items', ['product_id' => $product->id])
            ->json('data.items.0.id');

        $this->withToken($token)->deleteJson("/api/v1/wishlist/items/{$itemId}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_items', 0);

        $this->assertDatabaseMissing('wishlist_items', ['id' => $itemId]);
    }

    // ------------------------------------------------------------------
    // Move to cart
    // ------------------------------------------------------------------

    public function test_moving_wishlist_item_to_cart_works(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product(['stock_quantity' => 10]);

        $itemId = $this->withToken($token)->postJson('/api/v1/wishlist/items', ['product_id' => $product->id])
            ->json('data.items.0.id');

        $response = $this->withToken($token)->postJson("/api/v1/wishlist/items/{$itemId}/move-to-cart")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $response->assertJsonPath('data.cart.total_items', 1);
        $response->assertJsonPath('data.cart.items.0.quantity', 1);
        $response->assertJsonPath('data.cart.items.0.product.id', $product->id);
        $response->assertJsonPath('data.wishlist.total_items', 0);

        $this->assertDatabaseMissing('wishlist_items', ['id' => $itemId]);
        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 1]);
    }

    public function test_moving_wishlist_item_increments_existing_cart_line(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product(['stock_quantity' => 10]);

        $this->withToken($token)->postJson('/api/v1/cart/items', ['product_id' => $product->id, 'quantity' => 2])
            ->assertStatus(201);

        $itemId = $this->withToken($token)->postJson('/api/v1/wishlist/items', ['product_id' => $product->id])
            ->json('data.items.0.id');

        $this->withToken($token)->postJson("/api/v1/wishlist/items/{$itemId}/move-to-cart")
            ->assertJsonPath('data.cart.items.0.quantity', 3)
            ->assertJsonPath('data.cart.total_items', 3);

        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 3]);
    }

    public function test_cannot_move_inactive_wishlist_product_to_cart(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product(['status' => 'active', 'stock_quantity' => 10]);

        $itemId = $this->withToken($token)->postJson('/api/v1/wishlist/items', ['product_id' => $product->id])
            ->json('data.items.0.id');

        $product->update(['status' => 'inactive']);

        $this->withToken($token)->postJson("/api/v1/wishlist/items/{$itemId}/move-to-cart")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_id'], 'data');

        $this->assertDatabaseHas('wishlist_items', ['id' => $itemId]);
    }

    public function test_cannot_move_another_customers_wishlist_item_to_their_cart(): void
    {
        $owner = $this->customer();
        $other = $this->otherCustomer();

        $product = $this->product(['stock_quantity' => 10]);
        $ownerItemId = Wishlist::where('user_id', $owner->id)->first()->items()->create(['product_id' => $product->id])->id;

        $otherToken = $this->login($other);

        $this->withToken($otherToken)->postJson("/api/v1/wishlist/items/{$ownerItemId}/move-to-cart")
            ->assertStatus(404)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('wishlist_items', ['id' => $ownerItemId]);
    }

    // ------------------------------------------------------------------
    // Resource structure
    // ------------------------------------------------------------------

    public function test_wishlist_resource_returns_expected_structure(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product();

        $this->withToken($token)->postJson('/api/v1/wishlist/items', ['product_id' => $product->id]);

        $this->withToken($token)->getJson('/api/v1/wishlist')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success', 'message',
                'data' => [
                    'id',
                    'items' => [[
                        'id',
                        'product' => ['id', 'name', 'slug', 'price'],
                        'available',
                    ]],
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