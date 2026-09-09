<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Checkout\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly OrderService $orderService) {}

    /**
     * POST /api/v1/checkout — place an order from the current cart.
     * Only `address_id` is accepted; all financial figures are computed
     * server-side by the OrderService.
     */
    public function checkout(CheckoutRequest $request): JsonResponse
    {
        $order = $this->orderService->placeOrder($request->user(), $request->integer('address_id'));

        if (! $order) {
            return $this->error('Address not found.', null, 404);
        }

        return $this->success(new OrderResource($order), 'Order placed successfully.', 201);
    }

    /**
     * GET /api/v1/orders — the authenticated customer's order history.
     */
    public function index(Request $request): JsonResponse
    {
        $orders = $this->orderService->list($request->user(), (int) $request->integer('per_page', 15));

        return $this->success([
            'orders' => OrderResource::collection($orders),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'last_page' => $orders->lastPage(),
            ],
        ], 'Orders retrieved successfully.');
    }

    /**
     * GET /api/v1/orders/{orderNumber} — a single order by order number
     * (scoped to the authenticated customer, 404 for anything else).
     */
    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $order = $this->orderService->findByNumber($request->user(), $orderNumber);

        if (! $order) {
            return $this->error('Order not found.', null, 404);
        }

        return $this->success(new OrderResource($order), 'Order retrieved successfully.');
    }

    /**
     * POST /api/v1/orders/{orderNumber}/cancel — cancel a customer's own order.
     */
    public function cancel(Request $request, string $orderNumber): JsonResponse
    {
        try {
            $result = $this->orderService->cancel($request->user(), $orderNumber);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error('The order could not be cancelled.', $e->errors(), 422);
        }

        if (! $result) {
            return $this->error('Order not found.', null, 404);
        }

        $message = $result['refund_required']
            ? 'Order cancelled successfully. Refund processing required.'
            : 'Order cancelled successfully.';

        return $this->success(new OrderResource($result['order']), $message);
    }
}