<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Order authorization policy (Phase 9 — Admin Order Dashboard).
 *
 * Staff can view orders and update their status (business-rules §1.3), but
 * cancellation, notes and statistics are admin-only operations.
 */
class OrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isStaff();
    }

    public function view(User $user, Order $order): bool
    {
        return $this->viewAny($user);
    }

    public function updateStatus(User $user, Order $order): bool
    {
        return $this->viewAny($user);
    }

    public function cancel(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }

    public function manageNotes(User $user, Order $order): bool
    {
        return $user->isAdmin();
    }
}