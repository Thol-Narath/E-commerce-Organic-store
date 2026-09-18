<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A shipping method the store offers at checkout (e.g. Standard / Express).
 *
 * Rates are owned and priced server-side. The legacy flat rate from
 * config('store.shipping_fee') is only used as a last-resort fallback when no
 * shipping method has been set up yet.
 */
class ShippingMethod extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'base_rate',
        'free_over',
        'estimated_days',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'base_rate' => 'decimal:2',
        'free_over' => 'decimal:2',
        'estimated_days' => 'integer',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Whether this method is offered to customers at checkout.
     */
    public function isAvailable(): bool
    {
        return ! $this->trashed() && $this->is_active;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}