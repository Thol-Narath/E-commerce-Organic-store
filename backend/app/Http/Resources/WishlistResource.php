<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WishlistResource extends JsonResource
{
    /**
     * Transform a wishlist into its API representation.
     */
    public function toArray(Request $request): array
    {
        $items = WishlistItemResource::collection($this->whenLoaded('items'));

        return [
            'id' => $this->id,
            'items' => $items,
            'total_items' => $this->whenLoaded('items') ? $this->items->count() : 0,
        ];
    }
}