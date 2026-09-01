<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Handles all cart business logic.
 *
 * The authenticated user is the sole source of truth for cart ownership —
 * controllers/services never trust a client-supplied user/cart id.
 *
 * Availability rules (business-rules.md §3):
 *   - a product must be active and non-trashed to be added;
 *   - quantity must be >= `min_order_qty`;
 *   - the resulting line quantity may never exceed `stock_quantity`.
 */
class CartService
{
    /**
     * The user's active cart (created on demand), with items eager loaded so
     * resources do not trigger N+1 queries.
     */
    public function getCart(User $user): Cart
    {
        $cart = $this->activeCart($user);

        return $cart->load(['items.product.category:id,name,slug', 'items.product.primaryImage']);
    }

    /**
     * Add a product to the user's cart. If the product is already present the
     * quantity is incremented (no duplicate lines, enforced by the database).
     */
    public function addItem(User $user, int $productId, int $quantity): Cart
    {
        return DB::transaction(function () use ($user, $productId, $quantity) {
            $product = $this->loadProduct($productId);
            $cart = $this->activeCart($user);

            $line = $cart->items()->firstOrNew(['product_id' => $product->id]);
            $currentQuantity = $line->exists ? $line->quantity : 0;

            $this->assertAddAllowed($product, $quantity, $currentQuantity);

            $line->quantity = $currentQuantity + $quantity;
            $cart->items()->save($line);

            return $this->getCart($user);
        });
    }

    /**
     * Set a cart item quantity. Returns null when the item does not belong to
     * the authenticated user's active cart (also guards cross-user access).
     */
    public function updateItem(User $user, int $cartItemId, int $quantity): ?Cart
    {
        $cart = $this->activeCart($user);
        $line = $cart->items()->find($cartItemId);

        if (! $line) {
            return null;
        }

        $product = $this->loadProduct($line->product_id);
        $this->assertSetAllowed($product, $quantity);

        $line->update(['quantity' => $quantity]);

        return $this->getCart($user);
    }

    /**
     * Remove a cart item. Returns null when the item is not in the user's cart.
     */
    public function removeItem(User $user, int $cartItemId): ?Cart
    {
        $cart = $this->activeCart($user);
        $line = $cart->items()->find($cartItemId);

        if (! $line) {
            return null;
        }

        $line->delete();

        return $this->getCart($user);
    }

    /**
     * Remove every item from the user's active cart (the cart row itself is kept).
     */
    public function clear(User $user): Cart
    {
        $cart = $this->activeCart($user);
        $cart->items()->delete();

        return $this->getCart($user);
    }

    /**
     * The user's single active cart record (one per user, per the DB design).
     */
    public function activeCart(User $user): Cart
    {
        $cart = $user->cart()->where('status', 'active')->first();

        return $cart ?? $user->cart()->create(['status' => 'active']);
    }

    /**
     * Validate that adding `$quantityToAdd` on top of `$currentQuantity` is
     * allowed for the product on the current business rules.
     *
     * @throws ValidationException
     */
    public function assertAddAllowed(Product $product, int $quantityToAdd, int $currentQuantity = 0): void
    {
        $this->assertProductAvailable($product);

        $total = $currentQuantity + $quantityToAdd;
        $this->assertQuantityInRange($product, $quantityToAdd);

        if ($total > $product->stock_quantity) {
            $remaining = max(0, $product->stock_quantity - $currentQuantity);
            $message = $remaining === 0
                ? 'This product is out of stock.'
                : "Only {$remaining} more unit(s) are available.";

            throw ValidationException::withMessages(['quantity' => $message]);
        }
    }

    /**
     * Validate that directly setting a quantity is allowed (PATCH endpoints).
     *
     * @throws ValidationException
     */
    public function assertSetAllowed(Product $product, int $quantity): void
    {
        $this->assertProductAvailable($product);
        $this->assertQuantityInRange($product, $quantity);

        if ($quantity > $product->stock_quantity) {
            throw ValidationException::withMessages([
                'quantity' => "Only {$product->stock_quantity} unit(s) are available.",
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function assertProductAvailable(?Product $product): void
    {
        if (! $product || $product->trashed() || ! $product->isAvailable()) {
            throw ValidationException::withMessages(['product_id' => 'This product is no longer available.']);
        }

        if ($product->stock_quantity <= 0) {
            throw ValidationException::withMessages(['quantity' => 'This product is out of stock.']);
        }
    }

    /**
     * @throws ValidationException
     */
    protected function assertQuantityInRange(Product $product, int $quantity): void
    {
        if ($quantity < $product->min_order_qty) {
            throw ValidationException::withMessages([
                'quantity' => "The minimum order quantity is {$product->min_order_qty}.",
            ]);
        }
    }

    protected function loadProduct(int $productId): Product
    {
        $product = Product::withTrashed()->find($productId);

        if (! $product) {
            throw ValidationException::withMessages(['product_id' => 'The selected product does not exist.']);
        }

        return $product;
    }
}