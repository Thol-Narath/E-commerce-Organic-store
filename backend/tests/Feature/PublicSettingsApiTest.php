<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PublicSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_settings_exposes_store_and_shipping_display_info(): void
    {
        Config::set('store.shipping_fee', 3.5);

        $response = $this->getJson('/api/v1/settings/public');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.store.name', config('app.name'))
            ->assertJsonPath('data.shipping.flat_rate', '3.50');
    }

    public function test_public_settings_requires_no_authentication(): void
    {
        $this->getJson('/api/v1/settings/public')->assertStatus(200);
    }
}