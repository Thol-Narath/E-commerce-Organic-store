<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WishlistItemResource extends JsonResource
{
    /**
     * Transform a wishlist item. `available` reflects the live product state
     * so unavailable products can be displayed as such and removed.
     */
    public function toArray(Request $request): array
    {
        $product = $this->product;

        return [
            'id' => $this->id,
            'product' => $product ? new ProductResource($product) : null,
            'available' => $product !== null && $product->isAvailable() && $product->isInStock(),
            'created_at' => $this->when($this->created_at !== null, $this->created_at),
        ];
    }
}