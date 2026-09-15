<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->whenLoaded('supplier', fn () => new SupplierResource($this->supplier)),
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'shipping_fee' => $this->shipping_fee,
            'total' => $this->total,
            'expected_delivery_date' => $this->expected_delivery_date,
            'ordered_at' => $this->ordered_at,
            'received_at' => $this->received_at,
            'cancelled_at' => $this->cancelled_at,
            'notes' => $this->notes,
            'items' => SupplierOrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}