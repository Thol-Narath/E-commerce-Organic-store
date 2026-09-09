<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdatePasswordRequest;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    use ApiResponse;

    public function show(Request $request): JsonResponse
    {
        return $this->success(['user' => new UserResource($request->user())], 'Profile retrieved.');
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update($request->only(['name', 'email', 'phone']));

        return $this->success(['user' => new UserResource($user->fresh())], 'Profile updated successfully.');
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Invalidate other active tokens so a stolen session cannot persist.
        $user->tokens()->where('id', '!=', $request->user()->currentAccessToken()->id)->delete();

        return $this->success(null, 'Password updated successfully.');
    }

    /**
     * POST /api/v1/profile/avatar — upload a profile photo.
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ]);

        $user = $request->user();

        // Delete old avatar if it exists
        if ($user->avatar && Storage::disk('public')->exists(str_replace('/storage/', '', $user->avatar))) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $user->avatar));
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $url = Storage::disk('public')->url($path);

        $user->update(['avatar' => $url]);

        return $this->success(['user' => new UserResource($user->fresh())], 'Avatar uploaded successfully.');
    }
}
