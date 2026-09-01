<?php

namespace App\Services;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Customer address book business logic.
 *
 * Ownership is always derived from the authenticated user — services never
 * trust a client-supplied user id. Default-address rules:
 *   - the first address a customer creates automatically becomes default;
 *   - creating/updating with `is_default` promotes that address and clears any
 *     other default;
 *   - deleting the default address promotes the oldest remaining one.
 */
class AddressService
{
    /**
     * The customer's addresses, default first.
     */
    public function list(User $user): Collection
    {
        return $user->addresses()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();
    }

    /**
     * Create an address for the customer.
     */
    public function store(User $user, array $data): Address
    {
        return DB::transaction(function () use ($user, $data) {
            $makeDefault = (bool) ($data['is_default'] ?? false) || $user->addresses()->count() === 0;

            if ($makeDefault) {
                $this->clearDefault($user);
            }

            return $user->addresses()->create(array_merge($data, ['is_default' => $makeDefault]));
        });
    }

    /**
     * Update an address owned by the customer. Returns null when the address
     * does not belong to the user (cross-user access guard).
     */
    public function update(User $user, int $addressId, array $data): ?Address
    {
        return DB::transaction(function () use ($user, $addressId, $data) {
            $address = $user->addresses()->find($addressId);

            if (! $address) {
                return null;
            }

            if ($data['is_default'] ?? false) {
                $this->clearDefault($user);
                $data['is_default'] = true;
            }

            $address->update($data);

            return $address;
        });
    }

    /**
     * Delete an address owned by the customer.
     *
     * @return string 'deleted' | 'in_use' | 'not_found'
     *                - 'not_found': the address does not belong to the user;
     *                - 'in_use': attached to historical orders (restricted FK) —
     *                  never deleted, preserving historical records.
     */
    public function destroy(User $user, int $addressId): string
    {
        return DB::transaction(function () use ($user, $addressId) {
            $address = $user->addresses()->find($addressId);

            if (! $address) {
                return 'not_found';
            }

            if ($address->orders()->exists()) {
                return 'in_use';
            }

            $wasDefault = $address->is_default;
            $address->delete();

            // Promote the oldest remaining address if we just removed the default.
            if ($wasDefault && ! $user->addresses()->where('is_default', true)->exists()) {
                $next = $user->addresses()->orderBy('id')->first();

                if ($next) {
                    $next->update(['is_default' => true]);
                }
            }

            return 'deleted';
        });
    }

    /**
     * Make an address the customer's default. Returns null for cross-user ids.
     */
    public function setDefault(User $user, int $addressId): ?Address
    {
        return DB::transaction(function () use ($user, $addressId) {
            $address = $user->addresses()->find($addressId);

            if (! $address) {
                return null;
            }

            $this->clearDefault($user);
            $address->update(['is_default' => true]);

            return $address;
        });
    }

    private function clearDefault(User $user): void
    {
        $user->addresses()->where('is_default', true)->update(['is_default' => false]);
    }
}