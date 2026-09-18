<?php

namespace Tests\Feature;

use App\Models\ShippingMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingMethodApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    // ------------------------------------------------------------------
    // Public endpoint
    // ------------------------------------------------------------------

    public function test_public_shipping_methods_lists_only_active_methods(): void
    {
        $response = $this->getJson('/api/v1/shipping-methods')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $names = collect($response->json('data.methods'))->pluck('name')->all();

        $this->assertContains('Standard Shipping', $names);
        $this->assertContains('In-Store Pickup', $names);

        // Inactive methods never leak to the storefront.
        ShippingMethod::where('code', 'express')->update(['is_active' => false]);
        $this->flushHeaders();
        $response = $this->getJson('/api/v1/shipping-methods')
            ->assertStatus(200);

        $this->assertNotContains(
            'Express Shipping',
            collect($response->json('data.methods'))->pluck('name')->all()
        );
    }

    public function test_public_shipping_methods_requires_no_auth(): void
    {
        $this->getJson('/api/v1/shipping-methods')->assertStatus(200);
    }

    // ------------------------------------------------------------------
    // Admin CRUD
    // ------------------------------------------------------------------

    public function test_admin_can_list_shipping_methods(): void
    {
        $token = $this->loginAs('admin@organicstore.test');

        $this->withToken($token)->getJson('/api/v1/admin/shipping-methods')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success', 'message',
                'data' => [
                    'items' => [[
                        'id', 'name', 'code', 'description',
                        'base_rate', 'free_over', 'estimated_days',
                        'is_active', 'is_default', 'sort_order',
                    ]],
                    'pagination' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
            ]);
    }

    public function test_admin_can_create_a_shipping_method(): void
    {
        $token = $this->loginAs('admin@organicstore.test');

        $this->withToken($token)->postJson('/api/v1/admin/shipping-methods', [
            'name' => 'Drone Delivery',
            'code' => 'drone',
            'description' => 'Same-day drone drop-off.',
            'base_rate' => 12.50,
            'free_over' => 200,
            'estimated_days' => 1,
            'is_active' => true,
            'sort_order' => 9,
        ])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Drone Delivery')
            ->assertJsonPath('data.base_rate', '12.50');

        $this->assertDatabaseHas('shipping_methods', ['code' => 'drone', 'base_rate' => '12.50']);
    }

    public function test_admin_can_update_a_shipping_method(): void
    {
        $token = $this->loginAs('admin@organicstore.test');
        $method = ShippingMethod::where('code', 'express')->firstOrFail();

        $this->withToken($token)->putJson("/api/v1/admin/shipping-methods/{$method->id}", [
            'base_rate' => 9.90,
            'free_over' => null,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.base_rate', '9.90')
            ->assertJsonPath('data.free_over', null);
    }

    public function test_admin_can_toggle_and_set_default_shipping_method(): void
    {
        $token = $this->loginAs('admin@organicstore.test');
        $method = ShippingMethod::where('code', 'express')->firstOrFail();

        $this->withToken($token)->patchJson("/api/v1/admin/shipping-methods/{$method->id}/toggle")
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        $this->withToken($token)->patchJson("/api/v1/admin/shipping-methods/{$method->id}/default")
            ->assertStatus(200)
            ->assertJsonPath('data.is_default', true);

        // Only one default may exist at a time.
        $this->assertSame(1, ShippingMethod::where('is_default', true)->count());
    }

    public function test_admin_can_delete_a_shipping_method(): void
    {
        $token = $this->loginAs('admin@organicstore.test');
        $method = ShippingMethod::where('code', 'express')->firstOrFail();

        $this->withToken($token)->deleteJson("/api/v1/admin/shipping-methods/{$method->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('shipping_methods', ['id' => $method->id]);
    }

    public function test_shipping_method_crud_requires_admin_role(): void
    {
        $customerToken = $this->loginAs('maria@example.com');

        $this->withToken($customerToken)->getJson('/api/v1/admin/shipping-methods')->assertStatus(403);
        $this->withToken($customerToken)->postJson('/api/v1/admin/shipping-methods', [
            'name' => 'Hack',
            'code' => 'hack',
            'base_rate' => 0,
        ])->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function loginAs(string $email): string
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password',
        ]);

        $response->assertStatus(200);

        return $response->json('data.token');
    }
}