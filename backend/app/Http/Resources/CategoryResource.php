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
    public     function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->when($this->description !== null, $this->description),
            'icon' => $this->icon,
            'icon_url' => $this->icon ? $this->iconUrl($this->icon) : null,
            'status' => $this->when($this->status !== null, $this->status),
            'sort_order' => $this->sort_order,
            'products_count' => $this->when(isset($this->products_count), $this->products_count),
        ];
    }

    /**
     * Resolve a category icon to a publicly accessible, absolute URL.
     *
     * The frontend runs on a separate origin (Vite dev server) from the Laravel
     * API, so icons must be returned as full absolute URLs pointing at the
     * Laravel host (APP_URL). Relative paths would resolve against the React
     * origin and 404.
     *
     * Icons stored as web-root paths (e.g. category-icons/citrus.svg) are
     * served directly from the public directory. Icons uploaded through the
     * admin and stored on the public storage disk fall back to the storage
     * URL (e.g. /storage/images/categories/...).
     */
    protected function iconUrl(?string $icon): string
    {
        if (file_exists(public_path($icon))) {
            return url($icon);
        }

        return url(Storage::disk('public')->url($icon));
    }
}
