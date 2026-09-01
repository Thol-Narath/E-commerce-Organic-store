<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cart\AddToCartRequest;
use App\Http\Requests\Cart\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly CartService $cartService) {}

    /**
     * GET /api/v1/cart — the authenticated customer's cart with totals.
     */
    public function index(Request $request): JsonResponse
    {
        $cart = $this->cartService->getCart($request->user());

        return $this->success(new CartResource($cart), 'Cart retrieved successfully.');
    }

    /**
     * POST /api/v1/cart/items — add a product (increments quantity if present).
     */
    public function store(AddToCartRequest $request): JsonResponse
    {
        $cart = $this->cartService->addItem(
            $request->user(),
            $request->integer('product_id'),
            $request->integer('quantity')
        );

        return $this->success(new CartResource($cart), 'Product added to your cart.', 201);
    }

    /**
     * PATCH /api/v1/cart/items/{cartItem} — set an item quantity.
     * The item is scoped to the authenticated user's own cart (404 otherwise).
     */
    public function update(UpdateCartItemRequest $request, int $cartItem): JsonResponse
    {
        $cart = $this->cartService->updateItem(
            $request->user(),
            $cartItem,
            $request->integer('quantity')
        );

        if (! $cart) {
            return $this->error('Cart item not found.', null, 404);
        }

        return $this->success(new CartResource($cart), 'Cart updated successfully.');
    }

    /**
     * DELETE /api/v1/cart/items/{cartItem} — remove one item (own cart only).
     */
    public function destroy(Request $request, int $cartItem): JsonResponse
    {
        $cart = $this->cartService->removeItem($request->user(), $cartItem);

        if (! $cart) {
            return $this->error('Cart item not found.', null, 404);
        }

        return $this->success(new CartResource($cart), 'Item removed from your cart.');
    }

    /**
     * DELETE /api/v1/cart — remove every item from the user's active cart.
     */
    public function clear(Request $request): JsonResponse
    {
        $cart = $this->cartService->clear($request->user());

        return $this->success(new CartResource($cart), 'Cart cleared successfully.');
    }
}