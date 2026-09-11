<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case AbaPay = 'aba_pay';
    case Khqr = 'khqr';
    case Card = 'card';
    case Bakong = 'bakong';
    case Cod = 'cod';
    case BankTransfer = 'bank_transfer';
    case Online = 'online';

    /**
     * Whether this method resolves to the ABA PayWay gateway.
     */
    public function isPayway(): bool
    {
        return in_array($this, [self::AbaPay, self::Khqr, self::Card]);
    }

    /**
     * Whether this method resolves to the Bakong Open API gateway.
     */
    public function isBakong(): bool
    {
        return $this === self::Bakong;
    }

    /**
     * Human-friendly label for the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::AbaPay => 'ABA Pay',
            self::Khqr => 'KHQR (Scan to Pay)',
            self::Card => 'Card Payment',
            self::Bakong => 'Bakong KHQR',
            self::Cod => 'Cash on Delivery',
            self::BankTransfer => 'Bank Transfer',
            self::Online => 'Online Payment',
        };
    }

    /**
     * The PayWay purchase `payment_option` used when calling the gateway.
     * aba_pay and khqr both resolve to the combined deeplink flow which
     * returns a JS object (QR string + ABA deeplink); card uses the hosted
     * HTML checkout page.
     */
    public function paywayOption(): string
    {
        return match ($this) {
            self::AbaPay, self::Khqr => 'abapay_khqr_deeplink',
            self::Card => 'cards',
            default => throw new \LogicException('Method is not a PayWay method.'),
        };
    }
}