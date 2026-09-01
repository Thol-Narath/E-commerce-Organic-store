<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CategoryResource extends JsonResource
{
    /**
     * Transform a category into its API representation.
     *
     * Uses a cached relation lookup so the nested category on a product does not
     * trigger additional queries when the collection is loaded.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->when($this->description !== null, $this->description),
            'icon' => $this->icon,
            'icon_url' => $this->icon ? Storage::disk('public')->url($this->icon) : null,
            'status' => $this->when($this->status !== null, $this->status),
            'sort_order' => $this->sort_order,
            'products_count' => $this->when(isset($this->products_count), $this->products_count),
        ];
    }
}
