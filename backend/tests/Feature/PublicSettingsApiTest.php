<?php

namespace Tests\Feature;

use App\Mail\TestMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        config(['mail.mailers.smtp.password' => 'test-app-password']);
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

    public function test_admin_can_send_test_email_to_any_recipient(): void
    {
        Mail::fake();

        $token = $this->loginAs('admin@organicstore.test');

        $response = $this->withToken($token)->postJson('/api/v1/admin/settings/test-email', [
            'email' => 'shop.owner@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.email', 'shop.owner@example.com');

        Mail::assertSent(TestMail::class, function (TestMail $mail) {
            return $mail->hasTo('shop.owner@example.com');
        });
    }

    public function test_test_email_defaults_to_from_address(): void
    {
        Mail::fake();

        $token = $this->loginAs('admin@organicstore.test');

        $this->withToken($token)->postJson('/api/v1/admin/settings/test-email', [])
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_test_email_requires_admin_role(): void
    {
        $token = $this->loginAs('maria@example.com');

        $this->withToken($token)->postJson('/api/v1/admin/settings/test-email', [
            'email' => 'shop.owner@example.com',
        ])->assertStatus(403);
    }

    public function test_test_email_validates_recipient(): void
    {
        $token = $this->loginAs('admin@organicstore.test');

        $this->withToken($token)->postJson('/api/v1/admin/settings/test-email', [
            'email' => 'not-an-email',
        ])->assertStatus(422)
            ->assertJsonPath('data.email.0', 'The email field must be a valid email address.');
    }

    public function test_test_email_explains_unset_placeholder_password(): void
    {
        config(['mail.mailers.smtp.password' => 'REPLACE_WITH_YOUR_GMAIL_APP_PASSWORD']);

        $token = $this->loginAs('admin@organicstore.test');

        $this->withToken($token)->postJson('/api/v1/admin/settings/test-email', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data', null);
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