<?php

namespace App\Http\Resources;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryTransactionResource extends JsonResource
{
    /**
     * A single inventory ledger entry with the affected product, actor and
     * (when present) the origin record such as an Order.
     */
    public function toArray(Request $request): array
    {
        $reference = $this->whenLoaded('reference');

        return [
            'id' => $this->id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),
            'actor' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'type' => $this->type,
            'quantity_change' => $this->quantity_change,
            'stock_before' => $this->stock_before,
            'stock_after' => $this->stock_after,
            'reference_type' => $this->reference_type !== null ? class_basename($this->reference_type) : null,
            'reference_id' => $this->reference_id,
            'reference_number' => $reference instanceof Order ? $reference->order_number : null,
            'notes' => $this->notes,
            'created_at' => $this->created_at,
        ];
    }
}