<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the user into a safe API representation.
     *
     * Sensitive fields (password, password hash, tokens, secrets) are never exposed.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
            'status' => $this->status,
            'avatar' => $this->avatar,
            'email_verified_at' => $this->when($this->email_verified_at, $this->email_verified_at),
            'created_at' => $this->created_at,
            'updated_at' => $this->when($this->updated_at, $this->updated_at),
        ];
    }
}
