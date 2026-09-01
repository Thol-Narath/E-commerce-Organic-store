<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    // ------------------------------------------------------------------
    // Public category endpoints
    // ------------------------------------------------------------------

    public function test_public_category_index_returns_only_active_categories(): void
    {
        $active = Category::factory()->create(['status' => 'active']);
        $inactive = Category::factory()->create(['status' => 'inactive']);

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $ids = collect($response->json('data'))->pluck('id');
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_public_category_index_includes_active_product_count(): void
    {
        $category = Category::factory()->create(['status' => 'active']);
        Product::factory()->active()->count(3)->create(['category_id' => $category->id]);
        // Inactive product must NOT be counted for the public listing.
        Product::factory()->create(['category_id' => $category->id, 'status' => 'inactive']);

        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(200);
        $categoryData = collect($response->json('data'))->firstWhere('id', $category->id);
        $this->assertEquals(3, $categoryData['products_count']);
    }

    public function test_public_category_show_returns_active_category(): void
    {
        $category = Category::factory()->create(['status' => 'active']);

        $this->getJson("/api/v1/categories/{$category->slug}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $category->id)
            ->assertJsonPath('data.slug', $category->slug);
    }

    public function test_public_category_show_hides_inactive_category(): void
    {
        $inactive = Category::factory()->create(['status' => 'inactive']);

        $this->getJson("/api/v1/categories/{$inactive->slug}")
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_public_category_products_returns_active_products_only(): void
    {
        $category = Category::factory()->create(['status' => 'active']);
        $active = Product::factory()->active()->create(['category_id' => $category->id]);
        Product::factory()->create(['category_id' => $category->id, 'status' => 'inactive']);

        $response = $this->getJson("/api/v1/categories/{$category->slug}/products");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $ids = collect($response->json('data.items'))->pluck('id');
        $this->assertContains($active->id, $ids);
        collect($response->json('data.items'))->each(function ($item) {
            $this->assertEquals('active', $item['status']);
        });
    }

    public function test_public_category_products_for_inactive_category_returns_404(): void
    {
        $inactive = Category::factory()->create(['status' => 'inactive']);

        $this->getJson("/api/v1/categories/{$inactive->slug}/products")
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    // ------------------------------------------------------------------
    // Admin category management
    // ------------------------------------------------------------------

    public function test_admin_can_create_category(): void
    {
        $token = $this->login($this->admin());

        $response = $this->withToken($token)->postJson('/api/v1/admin/categories', [
            'name' => 'Nuts',
            'status' => 'active',
            'sort_order' => 10,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Nuts')
            ->assertJsonPath('data.slug', 'nuts');

        $this->assertDatabaseHas('categories', ['slug' => 'nuts']);
    }

    public function test_admin_duplicate_category_slug_is_auto_suffixed(): void
    {
        $token = $this->login($this->admin());
        Category::factory()->create(['name' => 'Nuts', 'slug' => 'nuts']);

        $response = $this->withToken($token)->postJson('/api/v1/admin/categories', [
            'name' => 'Nuts',
            'status' => 'active',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', 'nuts-2');
    }

    public function test_admin_can_update_category(): void
    {
        $token = $this->login($this->admin());
        $category = Category::factory()->create(['name' => 'Old Category']);

        $response = $this->withToken($token)->putJson("/api/v1/admin/categories/{$category->id}", [
            'name' => 'Updated Category',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Updated Category');

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Updated Category']);
    }

    public function test_admin_soft_deletes_category_without_products(): void
    {
        $token = $this->login($this->admin());
        $category = Category::factory()->create();

        $this->withToken($token)->deleteJson("/api/v1/admin/categories/{$category->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    }

    public function test_admin_cannot_delete_category_that_still_has_products(): void
    {
        $token = $this->login($this->admin());
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id]);

        $this->withToken($token)->deleteJson("/api/v1/admin/categories/{$category->id}")
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'deleted_at' => null]);
    }

    public function test_admin_can_upload_category_icon(): void
    {
        Storage::fake('public');
        $token = $this->login($this->admin());

        $create = $this->withToken($token)->post('/api/v1/admin/categories', [
            'name' => 'Icon Category',
            'status' => 'active',
            'icon' => UploadedFile::fake()->image('cat.png'),
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('success', true);

        $categoryId = $create->json('data.id');
        $category = Category::find($categoryId);
        $this->assertNotNull($category->icon);
        Storage::disk('public')->assertExists($category->icon);
    }

    // ------------------------------------------------------------------
    // Role authorization for categories
    // ------------------------------------------------------------------

    public function test_unauthenticated_user_cannot_manage_categories(): void
    {
        $this->getJson('/api/v1/admin/categories')->assertStatus(401);
        $this->postJson('/api/v1/admin/categories', [])->assertStatus(401);
    }

    public function test_customer_cannot_manage_categories(): void
    {
        $token = $this->login($this->customer());

        $this->withToken($token)->getJson('/api/v1/admin/categories')->assertStatus(403);
        $this->withToken($token)->postJson('/api/v1/admin/categories', [])->assertStatus(403);
    }

    public function test_staff_can_view_categories_but_not_manage_them(): void
    {
        $token = $this->login($this->staff());
        $category = Category::factory()->create();

        $this->withToken($token)->getJson('/api/v1/admin/categories')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->withToken($token)->postJson('/api/v1/admin/categories', [])->assertStatus(403);
        $this->withToken($token)->deleteJson("/api/v1/admin/categories/{$category->id}")->assertStatus(403);
        $this->withToken($token)->putJson("/api/v1/admin/categories/{$category->id}", ['name' => 'X'])->assertStatus(403);
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
