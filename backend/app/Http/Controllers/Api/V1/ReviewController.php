<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\StoreReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    use ApiResponse;

    /**
     * POST /api/v1/reviews — rate a product a customer has purchased.
     *
     * Business rule: a rating is only allowed once the customer owns a
     * delivered order containing the product. The rating is upserted per
     * customer + product (the table enforces a unique constraint) and is
     * immediately approved so the storefront average stays fresh.
     */
    public function store(StoreReviewRequest $request): JsonResponse
    {
        $user = $request->user();
        $productId = (int) $request->validated('product_id');

        $order = $user->orders()
            ->where('status', 'delivered')
            ->whereHas('items', fn ($q) => $q->where('product_id', $productId))
            ->latest('placed_at')
            ->first();

        if (! $order) {
            return $this->error(
                'You can only rate products you purchased after the order is delivered.',
                null,
                422
            );
        }

        $review = Review::updateOrCreate(
            ['user_id' => $user->id, 'product_id' => $productId],
            [
                'order_id' => $order->id,
                'rating' => (int) $request->validated('rating'),
                'title' => $request->validated('title'),
                'comment' => $request->validated('comment'),
                'status' => 'approved',
            ]
        );

        return $this->success(new ReviewResource($review), 'Thank you for rating this product!', 201);
    }
}