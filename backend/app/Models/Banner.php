<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'subtitle',
        'discount_percent',
        'discount_label',
        'image_url',
        'link_url',
        'bg_color',
        'cta_text',
        'cta_link',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'discount_percent' => 'integer',
    ];

    /**
     * Scope to active banners, ordered by sort_order then title.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title');
    }

    /**
     * Return the best available label for the discount badge.
     * Prefer the free-form discount_label; fall back to computed "X% OFF".
     */
    public function getDiscountBadgeAttribute(): ?string
    {
        if ($this->discount_label) {
            return $this->discount_label;
        }
        if ($this->discount_percent) {
            return $this->discount_percent . '% OFF';
        }
        return null;
    }

    /**
     * Determine the CTA link target — prefer the dedicated cta_link, fall back
     * to link_url, then to /shop.
     */
    public function getTargetUrlAttribute(): string
    {
        return $this->cta_link ?: $this->link_url ?: '/shop';
    }

    /**
     * Determine the CTA button label.
     */
    public function getCtaLabelAttribute(): string
    {
        return $this->cta_text ?: 'Shop Now';
    }
}
