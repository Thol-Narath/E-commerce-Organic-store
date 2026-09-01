<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wishlist\AddToWishlistRequest;
use App\Http\Resources\CartResource;
use App\Http\Resources\WishlistResource;
use App\Services\WishlistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly WishlistService $wishlistService) {}

    /**
     * GET /api/v1/wishlist — the authenticated customer's wishlist.
     */
    public function index(Request $request): JsonResponse
    {
        $wishlist = $this->wishlistService->getWishlist($request->user());

        return $this->success(new WishlistResource($wishlist), 'Wishlist retrieved successfully.');
    }

    /**
     * POST /api/v1/wishlist/items — save an active product (idempotent).
     */
    public function store(AddToWishlistRequest $request): JsonResponse
    {
        $wishlist = $this->wishlistService->addItem($request->user(), $request->integer('product_id'));

        return $this->success(new WishlistResource($wishlist), 'Product added to your wishlist.', 201);
    }

    /**
     * DELETE /api/v1/wishlist/items/{wishlistItem} — remove an item (own wishlist only).
     */
    public function destroy(Request $request, int $wishlistItem): JsonResponse
    {
        $wishlist = $this->wishlistService->removeItem($request->user(), $wishlistItem);

        if (! $wishlist) {
            return $this->error('Wishlist item not found.', null, 404);
        }

        return $this->success(new WishlistResource($wishlist), 'Item removed from your wishlist.');
    }

    /**
     * POST /api/v1/wishlist/items/{wishlistItem}/move-to-cart
     *
     * Adds the product to the cart (quantity 1, respecting stock/availability)
     * and removes it from the wishlist. Returns both updated resources.
     */
    public function moveToCart(Request $request, int $wishlistItem): JsonResponse
    {
        $result = $this->wishlistService->moveToCart($request->user(), $wishlistItem);

        if (! $result) {
            return $this->error('Wishlist item not found.', null, 404);
        }

        return $this->success([
            'cart' => new CartResource($result['cart']),
            'wishlist' => new WishlistResource($result['wishlist']),
        ], 'Product moved to your cart.');
    }
}