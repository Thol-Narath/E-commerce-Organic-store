<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Concerns\FormatsMoney;

class CartResource extends JsonResource
{
    use FormatsMoney;

    /**
     * Transform a cart into its API representation.
     *
     * Totals are always computed server-side from the live product prices.
     */
    public function toArray(Request $request): array
    {
        $items = CartItemResource::collection($this->whenLoaded('items'));

        $subtotal = collect($this->whenLoaded('items') ? $this->items : collect())
            ->sum(fn ($item) => $item->quantity * (float) ($item->product?->price ?? 0));

        return [
            'id' => $this->id,
            'items' => $items,
            'subtotal' => $this->money($subtotal),
            'total_items' => (int) collect($this->whenLoaded('items') ? $this->items : collect())
                ->sum(fn ($item) => $item->quantity),
        ];
    }
}