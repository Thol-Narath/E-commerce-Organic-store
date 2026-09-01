<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A free-form note an admin attaches to an order (Phase 9). These are
 * standalone collaboration notes, kept separate from the status audit trail.
 */
class OrderNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'admin_id',
        'note',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}