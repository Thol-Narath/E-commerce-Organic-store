<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A free-form admin note on an order (Phase 9).
 */
class OrderNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'note' => $this->note,
            'admin' => $this->whenLoaded('admin', fn () => [
                'id' => $this->admin->id,
                'name' => $this->admin->name,
                'email' => $this->admin->email,
            ]),
            'created_at' => $this->when($this->created_at !== null, $this->created_at->toISOString()),
        ];
    }
}