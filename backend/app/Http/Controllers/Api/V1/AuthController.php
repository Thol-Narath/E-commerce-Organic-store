<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AccountStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GoogleLoginRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => Role::Customer->value,
            'status' => AccountStatus::Active->value,
            'email_verified_at' => now(),
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Registration successful.', 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return $this->error('The provided credentials are incorrect.', null, 401);
        }

        if (! $user->isActive()) {
            return $this->error('Your account is inactive.', null, 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Authenticated successfully.');
    }

    public function google(GoogleLoginRequest $request): JsonResponse
    {
        $payload = $this->verifyGoogleToken($request->access_token);

        if ($payload === null) {
            return $this->error('Invalid Google token.', null, 401);
        }


        $googleId = $payload['sub'];
        $email = $payload['email'] ?? null;
        $name = $payload['name'] ?? 'Google User';
        $avatar = $payload['picture'] ?? null;

        if ($email === null) {
            return $this->error('Google account has no email address.', null, 422);
        }

        // Match an existing account first, then fall back to the Google id.
        $user = User::where('email', $email)->first()
            ?? User::where('google_id', $googleId)->first();

        if ($user === null) {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'google_id' => $googleId,
                'password' => null,
                'phone' => null,
                'avatar' => $avatar,
                'role' => Role::Customer->value,
                'status' => AccountStatus::Active->value,
                'email_verified_at' => now(),
            ]);
        } else {
            $user->forceFill([
                'google_id' => $googleId,
                'name' => $user->name ?? $name,
                'avatar' => $avatar ?? $user->avatar,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();
        }

        if (! $user->isActive()) {
            return $this->error('Your account is inactive.', null, 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->success([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Google authentication successful.');
    }

    /**
     * Verify a Google ID token and return its payload, or null if invalid.
     */
    private function verifyGoogleToken(string $token): ?array
    {
        try {
            // Force IPv4: on some networks PHP cURL tries IPv6 first and hangs.
            $options = [
                'curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ];

            $caBundle = config('services.google.ca_bundle');
            if ($caBundle) {
                // Resolve relative paths (e.g. storage/certs/cacert.pem) against the backend
                // base path, so it works regardless of the process working directory.
                $caBundle = str_starts_with($caBundle, DIRECTORY_SEPARATOR)
                    || preg_match('#^[A-Za-z]:[\\\\/]#', $caBundle)
                    ? $caBundle
                    : base_path($caBundle);

                if (is_file($caBundle)) {
                    $options['verify'] = $caBundle;
                }
            }

            $proxy = config('services.google.proxy');
            if ($proxy) {
                $options['proxy'] = $proxy;

                $username = config('services.google.proxy_username');
                $password = config('services.google.proxy_password');
                if ($username !== null && $password !== null) {
                    $options['proxy'] = [
                        'http' => $proxy,
                        'https' => $proxy,
                        'auth' => [$username, $password],
                    ];
                }
            }

            $request = Http::timeout(10)
                ->asForm()
                ->withOptions($options);

            $response = $request->post('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $token,
            ]);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $payload = $response->json();

        // Only accept tokens issued for our own Google OAuth client.
        $expectedAudience = config('services.google.client_id');
        if ($expectedAudience !== null && ($payload['aud'] ?? null) !== $expectedAudience) {
            return null;
        }

        return $payload;
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(['user' => new UserResource($request->user())], 'Authenticated user retrieved.');
    }
}
