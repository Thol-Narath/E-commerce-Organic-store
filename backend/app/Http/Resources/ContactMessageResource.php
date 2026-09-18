<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A customer contact/feedback message with its staff reply thread.
 */
class ContactMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'subject' => $this->subject,
            'message' => $this->message,
            'reply' => $this->reply,
            'is_read' => $this->isRead(),
            'is_replied' => $this->isReplied(),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null),
            'replied_by' => $this->whenLoaded('repliedBy', fn () => $this->repliedBy ? [
                'id' => $this->repliedBy->id,
                'name' => $this->repliedBy->name,
                'email' => $this->repliedBy->email,
            ] : null),
            'read_at' => $this->read_at?->toISOString(),
            'replied_at' => $this->replied_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}