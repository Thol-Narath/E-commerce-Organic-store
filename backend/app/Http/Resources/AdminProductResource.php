<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminProductResource extends JsonResource
{
    /**
     * Transform a product into its admin/staff API representation.
     *
     * Exposes the full catalog fields (including cost and inventory) that are
     * never shown to customers.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => new CategoryResource($this->category)),
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->when($this->short_description !== null, $this->short_description),
            'description' => $this->when($this->description !== null, $this->description),
            'sku' => $this->sku,
            'barcode' => $this->when($this->barcode !== null, $this->barcode),
            'price' => $this->price,
            'compare_at_price' => $this->when($this->compare_at_price !== null, $this->compare_at_price),
            'cost_price' => $this->when($this->cost_price !== null, $this->cost_price),
            'stock_quantity' => $this->stock_quantity,
            'low_stock_threshold' => $this->low_stock_threshold,
            'unit' => $this->when($this->unit !== null, $this->unit),
            'weight' => $this->when($this->weight !== null, $this->weight),
            'min_order_qty' => $this->min_order_qty,
            'is_featured' => $this->is_featured,
            'is_best_seller' => (bool) $this->is_best_seller,
            'status' => $this->status,
            'primary_image' => $this->whenLoaded('primaryImage', fn () => new ProductImageResource($this->primaryImage)),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'created_at' => $this->created_at,
            'updated_at' => $this->when($this->updated_at !== null, $this->updated_at),
            'deleted_at' => $this->when($this->deleted_at !== null, $this->deleted_at),
        ];
    }
}
