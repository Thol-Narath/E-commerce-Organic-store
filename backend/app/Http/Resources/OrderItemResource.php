<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Concerns\FormatsMoney;

class OrderItemResource extends JsonResource
{
    use FormatsMoney;

    /**
     * Transform an order line. All product information comes from the stored
     * snapshot so historical orders never change with the live catalog; the
     * live `product` reference is included only as a convenience link when the
     * relationship was eager-loaded.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product_name,
            'product_sku' => $this->product_sku,
            'quantity' => $this->quantity,
            'unit_price' => $this->money($this->unit_price),
            'line_total' => $this->money($this->line_total),
            'product' => $this->whenLoaded('product', fn () => $this->product ? new ProductResource($this->product) : null),
        ];
    }
}