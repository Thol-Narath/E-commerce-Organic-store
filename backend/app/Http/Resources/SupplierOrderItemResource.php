<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierOrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'sku' => $this->sku,
            'quantity' => (int) $this->quantity,
            'quantity_received' => (int) $this->quantity_received,
            'unit_cost' => $this->unit_cost,
            'line_total' => $this->line_total,
            'product' => $this->whenLoaded('product', fn () => new AdminProductResource($this->product)),
            'created_at' => $this->created_at,
        ];
    }
}