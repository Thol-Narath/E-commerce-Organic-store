<?php

namespace App\Enums;

enum AccountStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Banned = 'banned';

    /**
     * Whether the account may authenticate and use protected functionality.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
