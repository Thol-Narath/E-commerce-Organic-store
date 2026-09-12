<?php

namespace App\Http\Resources;

use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform a product into its customer-facing API representation.
     *
     * Internal fields (cost price, barcode, exact stock levels) are intentionally
     * omitted from the public product payload. Only a coarse availability status
     * (in stock / low stock / out of stock) is exposed for the storefront.
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
            'price' => $this->price,
            'compare_at_price' => $this->when($this->compare_at_price !== null, $this->compare_at_price),
            'sku' => $this->sku,
            'unit' => $this->when($this->unit !== null, $this->unit),
            'availability' => InventoryService::stockStatusFor((int) $this->stock_quantity, (int) $this->low_stock_threshold),
            'avg_rating' => $this->avg_rating !== null ? round((float) $this->avg_rating, 1) : null,
            'reviews_count' => (int) ($this->reviews_count ?? 0),
            'is_featured' => $this->is_featured,
            'is_best_seller' => (bool) $this->is_best_seller,
            'status' => $this->when($this->status !== null, $this->status),
            'primary_image' => $this->whenLoaded('primaryImage', fn () => new ProductImageResource($this->primaryImage)),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'created_at' => $this->when($this->created_at !== null, $this->created_at),
        ];
    }
}
