<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'payment_number',
        'payment_method',
        'gateway',
        'transaction_id',
        'gateway_transaction_id',
        'gateway_reference',
        'amount',
        'currency',
        'payment_status',
        'qr_string',
        'deeplink',
        'gateway_response',
        'expires_at',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Whether a status poll should re-verify this attempt against its gateway.
     *
     * Only pull-based gateways need this: Bakong has no webhook, so the paid
     * state can only be learned by asking Bakong. The attempt must still be
     * pending (not expired), and the QR md5 must be known to look it up.
     */
    public function shouldReconcileOnStatus(): bool
    {
        return $this->gateway === 'bakong'
            && $this->payment_status === PaymentStatus::Pending->value
            && ! empty($this->gateway_transaction_id)
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}