<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'discount_percent' => $this->discount_percent,
            'discount_label' => $this->discount_label,
            'discount_badge' => $this->discount_badge,
            'image_url' => $this->image_url ? Storage::disk('public')->url($this->image_url) : null,
            'link_url' => $this->link_url,
            'bg_color' => $this->bg_color,
            'cta_text' => $this->cta_text,
            'cta_link' => $this->cta_link,
            'target_url' => $this->target_url,
            'cta_label' => $this->cta_label,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
