<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\StoreSupplierOrderRequest;
use App\Http\Requests\Supplier\StoreSupplierRequest;
use App\Http\Requests\Supplier\UpdateSupplierRequest;
use App\Http\Resources\SupplierOrderResource;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Services\SupplierService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSupplierController extends Controller
{
    use ApiResponse, Paginates;

    public function __construct(private readonly SupplierService $supplierService) {}

    /**
     * GET /api/v1/admin/suppliers — paginated supplier list with search + status filter.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Supplier::query()
            ->withCount('orders')
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('contact_person', 'like', "%{$term}%");
            });
        }

        if ($request->filled('status')) {
            if ($request->input('status') === 'active') {
                $query->where('is_active', true);
            } elseif ($request->input('status') === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $paginator = $query->paginate($this->perPage($request, 15))->withQueryString();

        return $this->success([
            'items' => SupplierResource::collection($paginator->items()),
            'pagination' => $this->pagination($paginator),
        ], 'Suppliers retrieved successfully.');
    }

    /**
     * GET /api/v1/admin/suppliers/options — lightweight list for product/supplier
     * pickers (no pagination, active suppliers only).
     */
    public function options(): JsonResponse
    {
        $suppliers = Supplier::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone', 'email']);

        return $this->success($suppliers, 'Supplier options retrieved successfully.');
    }

    /**
     * GET /api/v1/admin/suppliers/{supplier} — single supplier.
     */
    public function show(Supplier $supplier): JsonResponse
    {
        $supplier->loadCount('orders');

        return $this->success(
            new SupplierResource($supplier),
            'Supplier retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/admin/suppliers — create a supplier.
     */
    public function store(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = Supplier::create($request->validated());

        return $this->success(
            new SupplierResource($supplier),
            'Supplier created successfully.',
            201
        );
    }

    /**
     * PUT /api/v1/admin/suppliers/{supplier} — update a supplier.
     */
    public function update(UpdateSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($request->validated());

        return $this->success(
            new SupplierResource($supplier->fresh()),
            'Supplier updated successfully.'
        );
    }

    /**
     * DELETE /api/v1/admin/suppliers/{supplier} — soft-delete a supplier.
     */
    public function destroy(Supplier $supplier): JsonResponse
    {
        $supplier->delete();

        return $this->success(null, 'Supplier deleted successfully.');
    }

    /**
     * PATCH /api/v1/admin/suppliers/{supplier}/toggle — quick active/inactive toggle.
     */
    public function toggle(Supplier $supplier): JsonResponse
    {
        $supplier->update(['is_active' => ! $supplier->is_active]);

        return $this->success(
            new SupplierResource($supplier->fresh()),
            'Supplier status updated successfully.'
        );
    }

    /**
     * POST /api/v1/admin/suppliers/orders — create a supplier (purchase) order
     * to restock products. Totals are computed server-side.
     */
    public function createOrder(StoreSupplierOrderRequest $request): JsonResponse
    {
        $order = $this->supplierService->createOrder($request->user(), $request->validated());

        return $this->success(
            new SupplierOrderResource($order),
            'Supplier order created successfully.',
            201
        );
    }

    /**
     * GET /api/v1/admin/supplier-orders?supplier_id=&status= — list purchase orders.
     */
    public function orders(Request $request): JsonResponse
    {
        $query = SupplierOrder::query()
            ->with(['supplier', 'items'])
            ->orderByDesc('id');

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', (int) $request->input('supplier_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $paginator = $query->paginate($this->perPage($request, 15))->withQueryString();

        return $this->success([
            'items' => SupplierOrderResource::collection($paginator->items()),
            'pagination' => $this->pagination($paginator),
        ], 'Supplier orders retrieved successfully.');
    }

    /**
     * GET /api/v1/admin/supplier-orders/{order} — single purchase order with lines.
     */
    public function showOrder(SupplierOrder $order): JsonResponse
    {
        $order->load(['supplier', 'user:id,name,email', 'items.product' => fn ($q) => $q->withTrashed()]);

        return $this->success(
            new SupplierOrderResource($order),
            'Supplier order retrieved successfully.'
        );
    }

    /**
     * POST /api/v1/admin/supplier-orders/{order}/place — move a draft to 'ordered'.
     */
    public function placeOrder(Request $request, SupplierOrder $order): JsonResponse
    {
        $request->validate([
            'expected_delivery_date' => ['nullable', 'date'],
        ]);

        $order = $this->supplierService->placeOrder($order, $request->input('expected_delivery_date'));

        return $this->success(
            new SupplierOrderResource($order),
            'Supplier order placed successfully.'
        );
    }

    /**
     * POST /api/v1/admin/supplier-orders/{order}/receive — mark an ordered
     * purchase order as received. Adds the (optionally partial) quantities to
     * product stock through the inventory ledger.
     */
    public function receiveOrder(Request $request, SupplierOrder $order): JsonResponse
    {
        $request->validate([
            'received' => ['nullable', 'array'],
            'received.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $order = $this->supplierService->receiveOrder(
            $request->user(),
            $order,
            $request->input('received', [])
        );

        return $this->success(
            new SupplierOrderResource($order),
            'Supplier order received successfully. Stock has been updated.'
        );
    }

    /**
     * POST /api/v1/admin/supplier-orders/{order}/cancel — cancel draft/ordered order.
     */
    public function cancelOrder(Request $request, SupplierOrder $order): JsonResponse
    {
        $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $order = $this->supplierService->cancelOrder($order, $request->input('notes'));

        return $this->success(
            new SupplierOrderResource($order),
            'Supplier order cancelled successfully.'
        );
    }
}