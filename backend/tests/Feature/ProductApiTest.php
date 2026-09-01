<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    // ------------------------------------------------------------------
    // Public product listing / filtering / sorting / pagination
    // ------------------------------------------------------------------

    public function test_public_product_list_returns_only_active_products(): void
    {
        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success', 'message',
                'data' => ['items', 'pagination'],
            ]);

        collect($response->json('data.items'))->each(function ($item) {
            $this->assertEquals('active', $item['status']);
        });
    }

    public function test_public_list_never_exposes_inactive_even_with_status_filter(): void
    {
        $inactive = $this->product(['status' => 'inactive']);

        $response = $this->getJson('/api/v1/products?status=inactive');

        $response->assertStatus(200);
        $ids = collect($response->json('data.items'))->pluck('id');
        $this->assertNotContains($inactive->id, $ids);

        $this->assertDatabaseHas('products', ['id' => $inactive->id]);
    }

    public function test_public_list_can_search_products(): void
    {
        $this->product(['name' => 'Golden Zucchini Special']);

        $response = $this->getJson('/api/v1/products?search=zucchini');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $names = collect($response->json('data.items'))->pluck('name');
        $this->assertTrue($names->contains(fn ($n) => str_contains(strtolower($n), 'zucchini')));
    }

    public function test_public_list_can_filter_by_category(): void
    {
        $category = $this->category();
        $this->product(['category_id' => $category->id]);
        $this->product(['category_id' => $category->id]);
        $this->product(['category_id' => $category->id]);
        $this->product();

        $response = $this->getJson("/api/v1/products?category_id={$category->id}");

        $response->assertStatus(200);
        $ids = collect($response->json('data.items'))->pluck('id');

        $this->assertCount(3, $ids);
        foreach ($category->products()->active()->pluck('id') as $id) {
            $this->assertContains($id, $ids);
        }
    }

    public function test_public_list_can_filter_by_price_range(): void
    {
        $this->product(['price' => 1.50]);
        $this->product(['price' => 3.00]);
        $this->product(['price' => 25.00]);

        $response = $this->getJson('/api/v1/products?min_price=1.00&max_price=5.00');

        $response->assertStatus(200);
        foreach ($response->json('data.items') as $item) {
            $this->assertGreaterThanOrEqual(1.00, (float) $item['price']);
            $this->assertLessThanOrEqual(5.00, (float) $item['price']);
        }
    }

    public function test_public_list_returns_featured_only(): void
    {
        $this->product(['is_featured' => true]);
        $this->product(['is_featured' => false]);

        $response = $this->getJson('/api/v1/products?featured=1');

        $response->assertStatus(200);
        collect($response->json('data.items'))->each(function ($item) {
            $this->assertTrue($item['is_featured']);
        });
    }

    public function test_public_list_can_sort_by_price_ascending(): void
    {
        $this->product(['price' => 20.00]);
        $this->product(['price' => 2.00]);
        $this->product(['price' => 10.00]);

        $response = $this->getJson('/api/v1/products?sort=price_low');

        $response->assertStatus(200);
        $prices = collect($response->json('data.items'))->pluck('price')->map(fn ($p) => (float) $p);
        $this->assertEquals($prices->sort()->values()->all(), $prices->values()->all());
    }

    public function test_public_list_paginates(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->product();
        }

        $response = $this->getJson('/api/v1/products?per_page=10');

        $response->assertStatus(200);
        $pagination = $response->json('data.pagination');
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertCount(10, $response->json('data.items'));
    }

    public function test_featured_endpoint_returns_only_featured(): void
    {
        $featured = $this->product(['is_featured' => true]);
        $this->product(['is_featured' => false]);

        $response = $this->getJson('/api/v1/products/featured');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $responseIds = collect($response->json('data.items'))->pluck('id');
        $this->assertContains($featured->id, $responseIds);
        collect($response->json('data.items'))->each(function ($item) {
            $this->assertTrue($item['is_featured']);
        });
    }

    // ------------------------------------------------------------------
    // Public product detail
    // ------------------------------------------------------------------

    public function test_public_product_detail_returns_active_product(): void
    {
        $product = $this->product();

        $this->getJson("/api/v1/products/{$product->slug}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.slug', $product->slug)
            ->assertJsonMissingPath('data.cost_price')
            ->assertJsonMissingPath('data.barcode')
            ->assertJsonMissingPath('data.stock_quantity');
    }

    public function test_public_product_detail_hides_inactive_product(): void
    {
        $inactive = $this->product(['status' => 'inactive']);

        $this->getJson("/api/v1/products/{$inactive->slug}")
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_public_product_detail_returns_404_for_unknown_slug(): void
    {
        $this->getJson('/api/v1/products/does-not-exist')
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    // ------------------------------------------------------------------
    // Admin product management
    // ------------------------------------------------------------------

    public function test_admin_can_create_product(): void
    {
        $token = $this->login($this->admin());
        $category = $this->category();

        $response = $this->withToken($token)->postJson('/api/v1/admin/products', [
            'category_id' => $category->id,
            'name' => 'Organic Kale',
            'sku' => 'KALE-ORG-001',
            'price' => 3.99,
            'stock_quantity' => 50,
            'status' => 'active',
            'unit' => 'kg',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Organic Kale')
            ->assertJsonPath('data.sku', 'KALE-ORG-001');

        $this->assertDatabaseHas('products', ['sku' => 'KALE-ORG-001']);
    }

    public function test_admin_product_creation_validates_required_fields(): void
    {
        $token = $this->login($this->admin());

        $response = $this->withToken($token)->postJson('/api/v1/admin/products', ['name' => '']);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['name', 'sku', 'price', 'category_id', 'status'], 'data');
    }

    public function test_admin_cannot_create_duplicate_sku(): void
    {
        $token = $this->login($this->admin());
        $category = $this->category();
        $this->product(['sku' => 'DUP-SKU-001']);

        $response = $this->withToken($token)->postJson('/api/v1/admin/products', [
            'category_id' => $category->id,
            'name' => 'Duplicate SKU Product',
            'sku' => 'DUP-SKU-001',
            'price' => 5.00,
            'stock_quantity' => 10,
            'status' => 'active',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['sku'], 'data');
    }

    public function test_admin_can_update_product(): void
    {
        $token = $this->login($this->admin());
        $product = $this->product(['name' => 'Old Name']);

        $response = $this->withToken($token)->putJson("/api/v1/admin/products/{$product->id}", [
            'name' => 'New Name',
            'price' => 12.50,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.price', '12.50');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'New Name']);
    }

    public function test_admin_soft_deletes_product(): void
    {
        $token = $this->login($this->admin());
        $product = $this->product();

        $this->withToken($token)->deleteJson("/api/v1/admin/products/{$product->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->getJson("/api/v1/products/{$product->slug}")->assertStatus(404);
    }

    public function test_admin_can_update_product_status(): void
    {
        $token = $this->login($this->admin());
        $product = $this->product();

        $this->withToken($token)->patchJson("/api/v1/admin/products/{$product->id}/status", [
            'status' => 'inactive',
        ])->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'inactive');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'status' => 'inactive']);
    }

    public function test_admin_can_update_product_featured(): void
    {
        $token = $this->login($this->admin());
        $product = $this->product(['is_featured' => false]);

        $this->withToken($token)->patchJson("/api/v1/admin/products/{$product->id}/featured", [
            'is_featured' => true,
        ])->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_featured', true);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_featured' => true]);
    }

    public function test_admin_index_includes_inactive_products(): void
    {
        $token = $this->login($this->admin());
        $inactive = $this->product(['status' => 'inactive']);

        $response = $this->withToken($token)->getJson('/api/v1/admin/products?status=inactive');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $ids = collect($response->json('data.items'))->pluck('id');
        $this->assertContains($inactive->id, $ids);
    }

    public function test_admin_can_upload_set_primary_and_delete_image(): void
    {
        Storage::fake('public');

        $token = $this->login($this->admin());
        $product = $this->product();

        $upload = $this->withToken($token)->post("/api/v1/admin/products/{$product->id}/images", [
            'image' => UploadedFile::fake()->image('apple.png'),
            'alt_text' => 'Fresh apple',
        ]);

        $upload->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_primary', true)
            ->assertJsonPath('data.alt_text', 'Fresh apple');

        $imageId = $upload->json('data.id');
        $this->assertDatabaseHas('product_images', ['id' => $imageId]);

        $upload2 = $this->withToken($token)->post("/api/v1/admin/products/{$product->id}/images", [
            'image' => UploadedFile::fake()->image('pear.png'),
        ]);
        $upload2->assertStatus(201)->assertJsonPath('data.is_primary', false);
        $image2Id = $upload2->json('data.id');

        $this->withToken($token)->post("/api/v1/admin/products/{$product->id}/images/{$image2Id}/primary")
            ->assertStatus(200)
            ->assertJsonPath('data.is_primary', true);

        $this->assertDatabaseHas('product_images', ['id' => $imageId, 'is_primary' => false]);
        $this->assertDatabaseHas('product_images', ['id' => $image2Id, 'is_primary' => true]);

        $this->withToken($token)->deleteJson("/api/v1/admin/products/{$product->id}/images/{$image2Id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('product_images', ['id' => $image2Id]);
    }

    // ------------------------------------------------------------------
    // Role authorization
    // ------------------------------------------------------------------

    public function test_unauthenticated_user_cannot_manage_products(): void
    {
        $this->getJson('/api/v1/admin/products')->assertStatus(401);
        $this->postJson('/api/v1/admin/products', [])->assertStatus(401);
    }

    public function test_customer_cannot_access_admin_products(): void
    {
        $token = $this->login($this->customer());
        $product = $this->product();

        $this->withToken($token)->getJson('/api/v1/admin/products')->assertStatus(403);
        $this->withToken($token)->postJson('/api/v1/admin/products', [])->assertStatus(403);
        $this->withToken($token)->patchJson("/api/v1/admin/products/{$product->id}/status", ['status' => 'inactive'])->assertStatus(403);
    }

    public function test_staff_can_view_products_but_cannot_manage_them(): void
    {
        $token = $this->login($this->staff());
        $product = $this->product();

        $this->withToken($token)->getJson('/api/v1/admin/products')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->withToken($token)->postJson('/api/v1/admin/products', [])->assertStatus(403);
        $this->withToken($token)->patchJson("/api/v1/admin/products/{$product->id}/status", ['status' => 'inactive'])->assertStatus(403);
        $this->withToken($token)->deleteJson("/api/v1/admin/products/{$product->id}")->assertStatus(403);
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

    protected function category(): Category
    {
        return Category::factory()->create();
    }

    protected function product(array $attributes = []): Product
    {
        $attributes['category_id'] = $attributes['category_id'] ?? $this->category()->id;

        return Product::factory()->active()->create($attributes);
    }

    protected function login(User $user): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200);

        return $response->json('data.token');
    }
}
