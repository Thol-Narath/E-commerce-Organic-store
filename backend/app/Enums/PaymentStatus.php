<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    /**
     * Whether this state is terminal — the payment can never become paid.
     */
    public function isTerminal(): bool
    {
        return ! in_array($this, [self::Pending]);
    }
}