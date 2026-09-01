<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressApiTest extends TestCase
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

    public function test_unauthenticated_user_receives_401_for_address_routes(): void
    {
        $this->getJson('/api/v1/addresses')->assertStatus(401);
        $this->postJson('/api/v1/addresses', [])->assertStatus(401);
        $this->getJson('/api/v1/addresses/1')->assertStatus(401);
        $this->patchJson('/api/v1/addresses/1', [])->assertStatus(401);
        $this->deleteJson('/api/v1/addresses/1')->assertStatus(401);
        $this->patchJson('/api/v1/addresses/1/default')->assertStatus(401);
    }

    public function test_customer_can_list_their_addresses(): void
    {
        $token = $this->login($this->customer());
        $own = Address::where('user_id', $this->customer()->id)->count();

        $this->withToken($token)->getJson('/api/v1/addresses')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount($own, 'data')
            ->assertJsonPath('data.0.is_default', true)
            ->assertJsonStructure([
                'success', 'message',
                'data' => [[
                    'id', 'label', 'recipient_name', 'recipient_phone',
                    'address_line1', 'address_line2', 'city', 'state',
                    'postal_code', 'country', 'is_default',
                ]],
            ]);
    }

    public function test_customer_can_retrieve_a_single_own_address(): void
    {
        $token = $this->login($this->customer());
        $address = $this->customer()->addresses()->first();

        $this->withToken($token)->getJson("/api/v1/addresses/{$address->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $address->id)
            ->assertJsonPath('data.recipient_name', $address->recipient_name);
    }

    public function test_customer_cannot_retrieve_another_customers_address(): void
    {
        $token = $this->login($this->customer());
        $otherAddress = Address::where('user_id', $this->otherCustomer()->id)->first();

        $this->withToken($token)->getJson("/api/v1/addresses/{$otherAddress->id}")
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_customer_cannot_modify_another_customers_address(): void
    {
        $token = $this->login($this->customer());
        $otherAddress = Address::where('user_id', $this->otherCustomer()->id)->first();

        $this->withToken($token)->patchJson("/api/v1/addresses/{$otherAddress->id}", [
            'recipient_name' => 'Hijacked',
        ])->assertStatus(404)->assertJsonPath('success', false);

        $this->withToken($token)->deleteJson("/api/v1/addresses/{$otherAddress->id}")
            ->assertStatus(404)->assertJsonPath('success', false);

        $this->assertDatabaseHas('addresses', ['id' => $otherAddress->id]);
    }

    // ------------------------------------------------------------------
    // Create
    // ------------------------------------------------------------------

    public function test_customer_can_create_an_address(): void
    {
        $token = $this->login($this->customer());

        $this->withToken($token)->postJson('/api/v1/addresses', $this->addressPayload())
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.recipient_name', 'Maria Santos')
            ->assertJsonPath('data.country', 'Philippines')
            ->assertJsonPath('data.is_default', false);

        $this->assertDatabaseHas('addresses', [
            'user_id' => $this->customer()->id,
            'recipient_name' => 'Maria Santos',
            'is_default' => false,
        ]);
    }

    public function test_first_address_automatically_becomes_default(): void
    {
        $fresh = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $token = $this->login($fresh);

        $this->withToken($token)->postJson('/api/v1/addresses', $this->addressPayload())
            ->assertStatus(201)
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('addresses', [
            'user_id' => $fresh->id,
            'is_default' => true,
        ]);
    }

    public function test_creating_default_address_promotes_it_and_clears_others(): void
    {
        $token = $this->login($this->customer());
        $previousDefault = $this->customer()->addresses()->where('is_default', true)->firstOrFail();

        $response = $this->withToken($token)->postJson('/api/v1/addresses', $this->addressPayload([
            'label' => 'Work',
            'is_default' => true,
        ]))->assertStatus(201);

        $newId = $response->json('data.id');

        $this->assertDatabaseHas('addresses', ['id' => $previousDefault->id, 'is_default' => false]);
        $this->assertDatabaseHas('addresses', ['id' => $newId, 'is_default' => true]);
        $this->assertEquals(1, Address::where('user_id', $this->customer()->id)->where('is_default', true)->count());
    }

    public function test_create_validates_required_fields(): void
    {
        $token = $this->login($this->customer());

        $this->withToken($token)->postJson('/api/v1/addresses', [
            'label' => 'Home',
        ])->assertStatus(422)->assertJsonValidationErrors([
            'recipient_name', 'address_line1', 'city', 'state', 'country',
        ], 'data');
    }

    // ------------------------------------------------------------------
    // Update / default / delete
    // ------------------------------------------------------------------

    public function test_customer_can_update_their_address(): void
    {
        $token = $this->login($this->customer());
        $address = $this->customer()->addresses()->first();

        $this->withToken($token)->patchJson("/api/v1/addresses/{$address->id}", [
            'recipient_name' => 'Maria D. Santos',
            'address_line1' => '99 New Street',
        ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.recipient_name', 'Maria D. Santos')
            ->assertJsonPath('data.address_line1', '99 New Street');

        $this->assertDatabaseHas('addresses', [
            'id' => $address->id,
            'recipient_name' => 'Maria D. Santos',
            'address_line1' => '99 New Street',
        ]);
    }

    public function test_customer_can_set_a_default_address(): void
    {
        $token = $this->login($this->customer());
        $address = $this->customer()->addresses()->where('is_default', false)->first();

        $this->withToken($token)->patchJson("/api/v1/addresses/{$address->id}/default")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $address->id)
            ->assertJsonPath('data.is_default', true);

        $this->assertEquals(1, Address::where('user_id', $this->customer()->id)->where('is_default', true)->count());
        $this->assertDatabaseHas('addresses', ['id' => $address->id, 'is_default' => true]);
    }

    public function test_customer_cannot_set_another_customers_address_as_default(): void
    {
        $token = $this->login($this->customer());
        $otherAddress = Address::where('user_id', $this->otherCustomer()->id)->first();

        $this->withToken($token)->patchJson("/api/v1/addresses/{$otherAddress->id}/default")
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_customer_can_delete_their_address(): void
    {
        $token = $this->login($this->customer());
        $address = $this->customer()->addresses()->where('is_default', false)->first();

        $this->withToken($token)->deleteJson("/api/v1/addresses/{$address->id}")
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
    }

    public function test_cannot_delete_an_address_attached_to_historical_orders(): void
    {
        $token = $this->login($this->customer());
        $default = $this->customer()->addresses()->where('is_default', true)->firstOrFail();

        // The seeded orders reference Maria's default (Home) address.
        $this->assertTrue($default->orders()->exists());

        $this->withToken($token)->deleteJson("/api/v1/addresses/{$default->id}")
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('addresses', ['id' => $default->id]);
    }

    public function test_deleting_default_address_promotes_remaining_one(): void
    {
        $fresh = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $token = $this->login($fresh);

        $first = $this->withToken($token)->postJson('/api/v1/addresses', $this->addressPayload(['label' => 'Home']))
            ->assertStatus(201)
            ->assertJsonPath('data.is_default', true)
            ->json('data.id');

        $second = $this->withToken($token)->postJson('/api/v1/addresses', $this->addressPayload(['label' => 'Work']))
            ->assertStatus(201)
            ->json('data.id');

        $this->withToken($token)->deleteJson("/api/v1/addresses/{$first}")->assertStatus(200);

        $this->assertDatabaseMissing('addresses', ['id' => $first]);
        $this->assertDatabaseHas('addresses', ['id' => $second, 'is_default' => true]);
    }

    public function test_update_address_rejects_nonexistent_address(): void
    {
        $token = $this->login($this->customer());

        $this->withToken($token)->patchJson('/api/v1/addresses/999999', ['recipient_name' => 'X'])
            ->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    // ------------------------------------------------------------------
    // Helpers (same seeded inventory as other API suites)
    // ------------------------------------------------------------------

    private function addressPayload(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Home',
            'recipient_name' => 'Maria Santos',
            'recipient_phone' => '09171234501',
            'address_line1' => '12 Green Valley St',
            'address_line2' => null,
            'city' => 'Quezon City',
            'state' => 'Metro Manila',
            'postal_code' => '1101',
            'country' => 'Philippines',
            'is_default' => false,
        ], $overrides);
    }

    protected function customer(): User
    {
        return User::where('email', 'maria@example.com')->firstOrFail();
    }

    protected function otherCustomer(): User
    {
        return User::where('email', 'john@example.com')->firstOrFail();
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