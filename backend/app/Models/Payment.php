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
     * Bakong has no webhook, so the paid state can only be learned by asking
     * Bakong; the attempt must still be pending (not expired) and the QR md5
     * must be known to look it up. PayWay attempts are reconciled from the
     * payment page too (as a safety net when the webhook is missed), but only
     * when backend transaction verification is enabled.
     */
    public function shouldReconcileOnStatus(): bool
    {
        if ($this->payment_status !== PaymentStatus::Pending->value || empty($this->gateway_transaction_id)) {
            return false;
        }

        if ($this->gateway === 'bakong') {
            return $this->expires_at !== null && $this->expires_at->isFuture();
        }

        if ($this->gateway === 'payway') {
            return (bool) config('payway.verify_transaction', false);
        }

        return false;
    }
}