<?php

namespace App\Policies;

use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * Inventory authorization policy (Phase 10 — Inventory Management).
 *
 * Staff can view and manage stock (AGENTS.md roles: staff manage inventory),
 * but the full ledger/audit trail is reserved for admins. Customers can never
 * reach these abilities — the API routes are role-gated as a coarse gate and
 * this policy is the authoritative secondary check.
 */
class InventoryPolicy
{
    use HandlesAuthorization;

    /**
     * View the inventory overview + statistics (admin or staff).
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isStaff();
    }

    /**
     * View a single product's inventory detail (admin or staff).
     */
    public function view(User $user, Product $product): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Add inbound stock (admin or staff).
     */
    public function addStock(User $user, Product $product): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Remove stock (admin or staff).
     */
    public function removeStock(User $user, Product $product): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Set an absolute stock target (admin or staff).
     */
    public function adjustStock(User $user, Product $product): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Configure the reorder/low-stock threshold (admin or staff).
     */
    public function updateReorderLevel(User $user, Product $product): bool
    {
        return $this->viewAny($user);
    }

    /**
     * View the inventory transaction ledger (admin only).
     */
    public function viewLedger(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Policy fallback: a ledger row is only visible to admins.
     */
    public function viewTransaction(User $user, InventoryTransaction $transaction): bool
    {
        return $user->isAdmin();
    }
}