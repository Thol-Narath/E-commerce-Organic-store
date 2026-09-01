<?php

namespace App\Enums;

enum Role: string
{
    case Customer = 'customer';
    case Staff = 'staff';
    case Admin = 'admin';

    /**
     * Determine whether this role has full administrative privileges.
     */
    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    /**
     * Determine whether this role is part of the staff team (staff or admin).
     */
    public function isStaffTeam(): bool
    {
        return $this === self::Staff || $this === self::Admin;
    }
}
