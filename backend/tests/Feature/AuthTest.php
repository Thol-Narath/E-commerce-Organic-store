<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    // ------------------------------------------------------------------
    // Register
    // ------------------------------------------------------------------

    public function test_customer_can_register(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'New Customer',
            'email' => 'new@example.com',
            'phone' => '09170000009',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['user', 'token', 'token_type'],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'role' => 'customer',
            'status' => 'active',
        ]);

        $user = User::where('email', 'new@example.com')->first();
        $this->assertNotEquals('secret123', $user->password);
        $this->assertTrue(Hash::check('secret123', $user->password));

        // Password must never appear in the response.
        $response->assertDontSee('password');
        $response->assertJsonMissing(['password' => 'secret123']);
    }

    public function test_registration_validation_works(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['name', 'email', 'password'], 'data');
    }

    public function test_registration_without_role_selection_always_becomes_customer(): void
    {
        // Attempt to pass role=admin; it must be ignored/forced to customer.
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'admin',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'sneaky@example.com',
            'role' => 'customer',
        ]);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Duplicate',
            'email' => 'admin@organicstore.test',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'], 'data');
    }

    // ------------------------------------------------------------------
    // Login
    // ------------------------------------------------------------------

    public function test_customer_can_login(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'maria@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data' => ['user', 'token', 'token_type']])
            ->assertJsonPath('data.user.role', 'customer');
    }

    public function test_invalid_password_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'maria@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::where('email', 'maria@example.com')->first();
        $user->update(['status' => 'inactive']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'maria@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    // ------------------------------------------------------------------
    // Authenticated endpoints
    // ------------------------------------------------------------------

    public function test_authenticated_user_can_access_me(): void
    {
        $token = $this->login($this->customer());

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'maria@example.com')
            ->assertJsonMissingPath('data.user.password');
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $this->getJson('/api/v1/profile')
            ->assertStatus(401);
    }

    public function test_user_can_logout(): void
    {
        $token = $this->login($this->customer());

        $this->withToken($token)->postJson('/api/v1/auth/logout')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        // Token record is removed from the database.
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Reset the auth guard (a test-only artifact) so the revoked token is re-validated.
        $this->app['auth']->forgetGuards();

        // The revoked token can no longer access protected routes.
        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    public function test_inactive_user_blocked_from_protected_routes(): void
    {
        $user = $this->customer();
        $token = $this->login($user);

        $user->update(['status' => 'inactive']);

        $this->withToken($token)->getJson('/api/v1/auth/me')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    // ------------------------------------------------------------------
    // Role authorization
    // ------------------------------------------------------------------

    public function test_customer_cannot_access_admin_routes(): void
    {
        $token = $this->login($this->customer());

        $this->withToken($token)->getJson('/api/v1/admin/me')
            ->assertStatus(403);
    }

    public function test_customer_cannot_access_staff_routes(): void
    {
        $token = $this->login($this->customer());

        $this->withToken($token)->getJson('/api/v1/staff/me')
            ->assertStatus(403);
    }

    public function test_staff_cannot_access_admin_only_routes(): void
    {
        $token = $this->login($this->staff());

        $this->withToken($token)->getJson('/api/v1/admin/me')
            ->assertStatus(403);
    }

    public function test_staff_can_access_staff_routes(): void
    {
        $token = $this->login($this->staff());

        $this->withToken($token)->getJson('/api/v1/staff/me')
            ->assertStatus(200)
            ->assertJsonPath('data.user.role', 'staff');
    }

    public function test_admin_can_access_admin_protected_routes(): void
    {
        $token = $this->login($this->admin());

        $this->withToken($token)->getJson('/api/v1/admin/me')
            ->assertStatus(200)
            ->assertJsonPath('data.user.role', 'admin');
    }

    // ------------------------------------------------------------------
    // Profile
    // ------------------------------------------------------------------

    public function test_user_can_update_own_profile(): void
    {
        $token = $this->login($this->customer());

        $response = $this->withToken($token)->putJson('/api/v1/profile', [
            'name' => 'Maria Updated',
            'email' => 'maria@example.com',
            'phone' => '09170001111',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.name', 'Maria Updated')
            ->assertJsonPath('data.user.phone', '09170001111');
    }

    public function test_user_cannot_modify_another_users_profile(): void
    {
        $token = $this->login($this->customer());

        // The profile endpoint is self-scoped; attempting to take over another
        // user's email must be rejected by the unique-rule (excluding self).
        $response = $this->withToken($token)->putJson('/api/v1/profile', [
            'name' => 'Maria',
            'email' => 'admin@organicstore.test',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'], 'data');
    }

    public function test_profile_update_requires_auth(): void
    {
        $this->putJson('/api/v1/profile', ['name' => 'X'])
            ->assertStatus(401);
    }

    // ------------------------------------------------------------------
    // Password
    // ------------------------------------------------------------------

    public function test_password_update_requires_current_password(): void
    {
        $token = $this->login($this->customer());

        $response = $this->withToken($token)->putJson('/api/v1/profile/password', [
            'current_password' => 'wrong-current',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password'], 'data');
    }

    public function test_password_can_be_changed_with_correct_current_password(): void
    {
        $user = $this->customer();
        $token = $this->login($user);

        $response = $this->withToken($token)->putJson('/api/v1/profile/password', [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        // Old password no longer works; new one does.
        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertStatus(401);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'newpassword123',
        ])->assertStatus(200);
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
