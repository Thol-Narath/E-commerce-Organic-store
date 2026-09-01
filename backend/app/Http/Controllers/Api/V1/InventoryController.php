<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AddStockRequest;
use App\Http\Requests\Inventory\AdjustStockRequest;
use App\Http\Requests\Inventory\AdjustInventoryRequest;
use App\Http\Requests\Inventory\RemoveStockRequest;
use App\Http\Requests\Inventory\UpdateReorderLevelRequest;
use App\Http\Resources\InventoryItemResource;
use App\Http\Resources\InventoryTransactionResource;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Services\InventoryService;
use App\Services\ProductService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inventory management (Phase 10). All authorization goes through
 * InventoryPolicy (registered for Product + InventoryTransaction); route
 * middleware is the coarse gate, the policy is the authoritative check.
 * The legacy global `POST admin|staff/inventory/adjust` endpoint is kept for
 * backwards compatibility — all new actions are per-product.
 */
class InventoryController extends Controller
{
    use ApiResponse, Paginates;

    /**
     * Whitelisted inventory listing sorts (default: recently updated).
     */
    private const SORT_WHITELIST = [
        'recently_updated' => ['updated_at', 'desc'],
        'oldest_updated' => ['updated_at', 'asc'],
        'stock_high' => ['stock_quantity', 'desc'],
        'stock_low' => ['stock_quantity', 'asc'],
        'name_asc' => ['name', 'asc'],
        'name_desc' => ['name', 'desc'],
    ];

    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly ProductService $productService
    ) {}

    /**
     * Admin/staff stock overview: every product with stock levels, stock status
     * and the latest movement timestamp. Supports backend search (name, SKU,
     * product id), category + stock-status filters and whitelisted sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $filters = $request->only(['search', 'category_id', 'category_slug', 'status']);

        $query = $this->productService->adminQuery($filters)
            ->reorder()
            ->withTrashed()
            ->with('lastInventoryTransaction');

        $this->applyStockFilter($query, $request);

        $sort = $request->input('sort', 'recently_updated');
        if (isset(self::SORT_WHITELIST[$sort])) {
            [$column, $dir] = self::SORT_WHITELIST[$sort];
            $query->orderBy($column, $dir)->orderBy('id', 'desc');
        } else {
            $query->orderByDesc('updated_at')->orderByDesc('id');
        }

        $paginator = $query->paginate($this->perPage($request, 20))
            ->withQueryString();

        return $this->success([
            'items' => InventoryItemResource::collection($paginator->items()),
            'pagination' => $this->pagination($paginator),
        ], 'Inventory retrieved successfully.');
    }

    /**
     * Dashboard statistics computed in SQL (AdminOrderService.statistics-style):
     * product counts per stock status plus total units on hand.
     */
    public function statistics(): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        return $this->success(
            $this->inventoryService->statistics(),
            'Inventory statistics retrieved successfully.'
        );
    }

    /**
     * A single product's inventory detail (image, pricing, stock + status).
     */
    public function show(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $product->load(['category:id,name,slug', 'primaryImage', 'lastInventoryTransaction']);

        return $this->success(new InventoryItemResource($product), 'Inventory detail retrieved successfully.');
    }

    /**
     * Add inbound stock to a product (ledger type: purchase).
     */
    public function addStock(Product $product, AddStockRequest $request): JsonResponse
    {
        $this->authorize('addStock', $product);

        $transaction = $this->inventoryService->addStock(
            $request->user(),
            $product,
            $request->integer('quantity'),
            $request->input('reason')
        );

        return $this->stockChangedResponse($product, $transaction, 'Stock added successfully.');
    }

    /**
     * Remove stock from a product (ledger type: adjustment, reason required).
     */
    public function removeStock(Product $product, RemoveStockRequest $request): JsonResponse
    {
        $this->authorize('removeStock', $product);

        $transaction = $this->inventoryService->removeStock(
            $request->user(),
            $product,
            $request->integer('quantity'),
            $request->input('reason')
        );

        return $this->stockChangedResponse($product, $transaction, 'Stock removed successfully.');
    }

    /**
     * Set a product's stock to an absolute target (physical-count adjustment).
     * The delta is derived server-side and recorded in the ledger.
     */
    public function adjustStock(Product $product, AdjustStockRequest $request): JsonResponse
    {
        $this->authorize('adjustStock', $product);

        $transaction = $this->inventoryService->adjustStock(
            $request->user(),
            $product,
            $request->integer('quantity'),
            $request->input('reason')
        );

        return $this->stockChangedResponse($product, $transaction, 'Stock adjusted successfully.');
    }

    /**
     * Configure a product's low-stock / reorder threshold.
     */
    public function updateReorderLevel(Product $product, UpdateReorderLevelRequest $request): JsonResponse
    {
        $this->authorize('updateReorderLevel', $product);

        $product = $this->inventoryService->updateReorderLevel(
            $product,
            $request->integer('reorder_level')
        );

        $product->load(['category:id,name,slug', 'primaryImage', 'lastInventoryTransaction']);

        return $this->success(new InventoryItemResource($product), 'Reorder level updated successfully.');
    }

    /**
     * Per-product inventory ledger (admin only, like the global ledger).
     */
    public function productTransactions(Product $product, Request $request): JsonResponse
    {
        $this->authorize('viewLedger', InventoryTransaction::class);

        $query = $product->inventoryTransactions()
            ->with(['user:id,name,email', 'reference'])
            ->orderByDesc('id');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $paginator = $query->paginate($this->perPage($request, 20))->withQueryString();

        return $this->success([
            'items' => InventoryTransactionResource::collection($paginator->items()),
            'pagination' => $this->pagination($paginator),
        ], 'Product inventory transactions retrieved successfully.');
    }

    /**
     * Admin-only audit trail of every stock movement with search/filters.
     */
    public function transactions(Request $request): JsonResponse
    {
        $this->authorize('viewLedger', InventoryTransaction::class);

        $query = InventoryTransaction::query()
            ->with(['product:id,name,sku', 'user:id,name,email', 'reference'])
            ->orderByDesc('id');

        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->input('product_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->whereHas('product', function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $paginator = $query->paginate($this->perPage($request, 20))->withQueryString();

        return $this->success([
            'items' => InventoryTransactionResource::collection($paginator->items()),
            'pagination' => $this->pagination($paginator),
        ], 'Inventory transactions retrieved successfully.');
    }

    /**
     * Legacy global stock adjustment (admin/staff). Kept for backwards
     * compatibility — new actions go through the per-product endpoints.
     */
    public function adjust(AdjustInventoryRequest $request): JsonResponse
    {
        $product = Product::findOrFail($request->validated('product_id'));
        $this->authorize('adjustStock', $product);

        $transaction = $this->inventoryService->adjust(
            $request->user(),
            $product,
            (int) $request->integer('quantity_change'),
            (string) $request->input('type', 'adjustment'),
            $request->input('reason')
        );

        return $this->stockChangedResponse($product, $transaction, 'Inventory adjusted successfully.');
    }

    /**
     * Shared response after any stock mutation: refreshed product + the new
     * ledger entry so the UI can update its summary and history in one call.
     */
    private function stockChangedResponse(Product $product, InventoryTransaction $transaction, string $message): JsonResponse
    {
        $transaction->load(['product:id,name,sku', 'user:id,name,email', 'reference']);

        return $this->success([
            'product' => new InventoryItemResource($product->refresh(['category:id,name,slug', 'primaryImage', 'lastInventoryTransaction'])),
            'transaction' => new InventoryTransactionResource($transaction),
        ], $message);
    }

    /**
     * Apply the derived stock-status filter. Keeps the legacy `low_stock=true`
     * flag working (stock <= threshold, including zero) for API compatibility.
     */
    private function applyStockFilter(Builder $query, Request $request): void
    {
        $stock = $request->input('stock');

        if (in_array($stock, ['in_stock', 'low_stock', 'out_of_stock'], true)) {
            if ($stock === 'out_of_stock') {
                $query->where('stock_quantity', 0);
            } elseif ($stock === 'low_stock') {
                $query->where('stock_quantity', '>', 0)
                    ->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
            } else {
                $query->whereColumn('stock_quantity', '>', 'low_stock_threshold');
            }

            return;
        }

        if (in_array($request->input('low_stock'), ['1', 'true', 'yes'], true)) {
            $query->whereColumn('stock_quantity', '<=', 'low_stock_threshold');
        }
    }
}