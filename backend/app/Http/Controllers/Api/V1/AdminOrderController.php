<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CancelOrderRequest;
use App\Http\Requests\Order\StoreOrderNoteRequest;
use App\Http\Requests\Order\UpdateOrderStatusRequest;
use App\Http\Resources\AdminOrderResource;
use App\Http\Resources\OrderNoteResource;
use App\Models\Order;
use App\Services\AdminOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Admin order management (Phase 9 — Admin Order Dashboard).
 *
 * Controllers stay thin: role gates are middleware, object-level authorization
 * is the OrderPolicy, and all business logic lives in AdminOrderService.
 */
class AdminOrderController extends Controller
{
    use ApiResponse, Paginates;

    public function __construct(private readonly AdminOrderService $orderService) {}

    /**
     * GET /api/v1/admin/orders — paginated list with backend
     * search/filters/sort. Admin + staff (view).
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        $paginator = $this->orderService->list($request->all(), $this->perPage($request, 20));

        return $this->success([
            'orders' => AdminOrderResource::collection($paginator->items()),
            'pagination' => $this->pagination($paginator),
        ], 'Orders retrieved successfully.');
    }

    /**
     * GET /api/v1/admin/orders/{order} — full dashboard detail. Admin + staff.
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        return $this->success(
            new AdminOrderResource($this->orderService->show($order)),
            'Order retrieved successfully.'
        );
    }

    /**
     * GET /api/v1/admin/orders/statistics — dashboard KPIs. Admin only.
     */
    public function statistics(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);

        return $this->success(
            $this->orderService->statistics(),
            'Order statistics retrieved successfully.'
        );
    }

    /**
     * PATCH /api/v1/admin/orders/{order}/status — controlled transition,
     * always recorded in the order timeline. Admin + staff.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $this->authorize('updateStatus', $order);

        try {
            $updated = $this->orderService->updateStatus(
                $order,
                $request->user(),
                $request->input('status'),
                $request->input('note')
            );
        } catch (ValidationException $e) {
            return $this->error('The order status could not be updated.', $e->errors(), 422);
        }

        return $this->success(
            new AdminOrderResource($updated),
            'Order status updated successfully.'
        );
    }

    /**
     * POST /api/v1/admin/orders/{order}/cancel — cancellation action that
     * restores reserved stock and records the timeline event. Admin only.
     *
     * A paid order is never auto-refunded; the response explains that a refund
     * must be processed by the finance team.
     */
    public function cancel(CancelOrderRequest $request, Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);

        try {
            $result = $this->orderService->cancel(
                $order,
                $request->user(),
                $request->input('note')
            );
        } catch (ValidationException $e) {
            return $this->error('The order could not be cancelled.', $e->errors(), 422);
        }

        $message = $result['refund_required']
            ? 'Order cancelled successfully. Refund processing required.'
            : 'Order cancelled successfully.';

        return $this->success(new AdminOrderResource($result['order']), $message);
    }

    /**
     * POST /api/v1/admin/orders/{order}/notes — attach an internal note. Admin
     * only.
     */
    public function addNote(StoreOrderNoteRequest $request, Order $order): JsonResponse
    {
        $this->authorize('manageNotes', $order);

        $note = $this->orderService->addNote($order, $request->user(), $request->input('note'));

        return $this->success(new OrderNoteResource($note), 'Note added successfully.', 201);
    }
}