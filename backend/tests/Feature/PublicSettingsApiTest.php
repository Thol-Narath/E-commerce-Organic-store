<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_public_settings_exposes_store_and_shipping_display_info(): void
    {
        $response = $this->getJson('/api/v1/settings/public');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.store.name', 'Delicacy Organic')
            ->assertJsonPath('data.shipping.flat_rate', '5.00');
    }

    public function test_public_settings_requires_no_authentication(): void
    {
        $this->getJson('/api/v1/settings/public')->assertStatus(200);
    }

    public function test_public_settings_exposes_logo_height(): void
    {
        $response = $this->getJson('/api/v1/settings/public');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['store' => ['logo_height']]])
            ->assertJsonPath('data.store.logo_height', 42);
    }

    public function test_admin_can_update_store_branding(): void
    {
        $token = $this->loginAs('admin@organicstore.test');

        $response = $this->withToken($token)->putJson('/api/v1/admin/settings/store-branding', [
            'name' => 'Green Basket',
            'tagline' => 'Organic from the farm to your table',
            'logo_height' => 56,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Green Basket')
            ->assertJsonPath('data.logo_height', 56);

        $this->assertDatabaseHas('settings', ['key' => 'store.name', 'value' => 'Green Basket'])
            ->assertDatabaseHas('settings', ['key' => 'store.logo_height', 'value' => '56']);
    }

    public function test_store_branding_requires_admin_role(): void
    {
        $token = $this->loginAs('maria@example.com');

        $this->withToken($token)->putJson('/api/v1/admin/settings/store-branding', [
            'name' => 'Hacker',
            'tagline' => '',
            'logo_height' => 40,
        ])->assertStatus(403);
    }

    public function test_store_branding_validates_logo_height(): void
    {
        $token = $this->loginAs('admin@organicstore.test');

        $this->withToken($token)->putJson('/api/v1/admin/settings/store-branding', [
            'name' => 'Basket',
            'tagline' => '',
            'logo_height' => 9999,
        ])->assertStatus(422);
    }

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