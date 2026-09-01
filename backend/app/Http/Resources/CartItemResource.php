<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Concerns\FormatsMoney;

class CartItemResource extends JsonResource
{
    use FormatsMoney;
    /**
     * Transform a cart line item.
     *
     * Price always comes from the live product record — never from the client.
     * `available` lets the UI flag items whose product has since been disabled
     * or run out of stock (Phase 6: shown as unavailable, removable).
     */
    public function toArray(Request $request): array
    {
        $product = $this->product;

        $unitPrice = $product ? $product->price : 0;
        $lineTotal = $this->quantity * (float) $unitPrice;

        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'product' => $product ? new ProductResource($product) : null,
            'unit_price' => $this->money($unitPrice),
            'line_total' => $this->money($lineTotal),
            'available' => $product !== null && $product->isAvailable() && $product->isInStock(),
            'created_at' => $this->when($this->created_at !== null, $this->created_at),
        ];
    }
}