<?php

namespace App\Services;

use App\Models\Wishlist;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Handles all wishlist business logic.
 *
 * Like the cart, ownership always derives from the authenticated user and
 * the wishlist is stored server-side. Duplicate products are prevented both
 * by the (wishlist_id, product_id) unique index and by the service.
 */
class WishlistService
{
    public function getWishlist(User $user): Wishlist
    {
        $wishlist = $user->wishlist()->firstOrCreate([]);

        return $wishlist->load(['items.product.category:id,name,slug', 'items.product.primaryImage']);
    }

    /**
     * Add an active product to the wishlist (idempotent — never duplicates).
     *
     * @throws ValidationException when the product is unavailable
     */
    public function addItem(User $user, int $productId): Wishlist
    {
        $product = $this->assertProductWishlistable($productId);

        $wishlist = $user->wishlist()->firstOrCreate([]);
        $wishlist->items()->firstOrCreate(['product_id' => $product->id]);

        return $this->getWishlist($user);
    }

    /**
     * Remove a wishlist item. Returns null when the item does not belong to the
     * authenticated user.
     */
    public function removeItem(User $user, int $wishlistItemId): ?Wishlist
    {
        $wishlist = $user->wishlist()->firstOrCreate([]);
        $item = $wishlist->items()->find($wishlistItemId);

        if (! $item) {
            return null;
        }

        $item->delete();

        return $this->getWishlist($user);
    }

    /**
     * Move a wishlist item into the cart (quantity 1) and remove it from the
     * wishlist. Returns null when the wishlist item is not the user's own.
     *
     * @return array{cart: \App\Models\Cart, wishlist: Wishlist}
     */
    public function moveToCart(User $user, int $wishlistItemId): ?array
    {
        return DB::transaction(function () use ($user, $wishlistItemId) {
            $wishlist = $user->wishlist()->firstOrCreate([]);
            $item = $wishlist->items()->find($wishlistItemId);

            if (! $item) {
                return null;
            }

            app(CartService::class)->addItem($user, $item->product_id, 1);
            $item->delete();

            return [
                'cart' => app(CartService::class)->getCart($user),
                'wishlist' => $this->getWishlist($user),
            ];
        });
    }

    /**
     * @throws ValidationException when the product is missing or unavailable
     */
    protected function assertProductWishlistable(int $productId): \App\Models\Product
    {
        $product = \App\Models\Product::withTrashed()->find($productId);

        if (! $product || $product->trashed() || ! $product->isAvailable()) {
            throw ValidationException::withMessages(['product_id' => 'This product is no longer available.']);
        }

        return $product;
    }
}