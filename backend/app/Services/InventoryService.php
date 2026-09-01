<?php

namespace App\Services;

use App\Models\InventoryTransaction;
use App\Models\Notification;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Central stock ledger service (business-rules.md §2).
 *
 *   - Every stock change runs inside a database transaction, locks the product
 *     row, and writes an InventoryTransaction record (stock_before/stock_after);
 *   - Stock can never go negative;
 *   - Orders decrement available stock at placement ('sale');
 *   - Returns/refunds restore stock ('return'), manual changes are recorded as
 *     'adjustment' or 'purchase';
 *   - When stock crosses at/below the low-stock threshold all active admins
 *     receive a notification (only on the crossing, never on every movement).
 */
class InventoryService
{
    /**
     * Ledger transaction types used by the application (business-rules §2,
     * inventory-management.md §Transaction types).
     */
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_SALE = 'sale';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_RETURN = 'return';
    public const TYPE_INITIAL = 'initial';

    /**
     * Three-state stock status computed from quantity + reorder level. Never
     * stored — always derived so it cannot drift from the real values.
     */
    public const STATUS_IN_STOCK = 'in_stock';
    public const STATUS_LOW_STOCK = 'low_stock';
    public const STATUS_OUT_OF_STOCK = 'out_of_stock';

    /**
     * The stock status of a product: OUT OF STOCK when empty, LOW STOCK when
     * at/below the reorder level (but still positive), IN STOCK otherwise.
     */
    public function stockStatus(Product $product): string
    {
        return self::stockStatusFor($product->stock_quantity, $product->low_stock_threshold);
    }

    /**
     * Pure status calculation (also used for efficient SQL aggregation).
     */
    public static function stockStatusFor(int $quantity, int $reorderLevel): string
    {
        if ($quantity <= 0) {
            return self::STATUS_OUT_OF_STOCK;
        }

        if ($quantity <= $reorderLevel) {
            return self::STATUS_LOW_STOCK;
        }

        return self::STATUS_IN_STOCK;
    }

    /**
     * Available stock. The store has no separate reservation layer: stock is
     * decremented once at order placement, so available = current quantity.
     */
    public function availableStock(Product $product): int
    {
        return (int) $product->stock_quantity;
    }

    /**
     * Add incoming stock (e.g. a purchase). Creates a `purchase` ledger entry.
     */
    public function addStock(?User $actor, Product $product, int $quantity, ?string $reason = null): InventoryTransaction
    {
        return $this->changeStock(
            $actor,
            $product,
            self::TYPE_PURCHASE,
            $quantity,
            null,
            null,
            $reason ?: 'Stock added'
        );
    }

    /**
     * Remove stock (e.g. damage, loss, expired). Recorded as an `adjustment`
     * ledger entry with a mandatory reason; can never drive stock below zero.
     *
     * @throws ValidationException when the quantity exceeds available stock
     */
    public function removeStock(?User $actor, Product $product, int $quantity, string $reason): InventoryTransaction
    {
        return $this->changeStock(
            $actor,
            $product,
            self::TYPE_ADJUSTMENT,
            -$quantity,
            null,
            null,
            $reason
        );
    }

    /**
     * Set stock to an absolute target (physical-count style adjustment). The
     * delta — not the target — is recorded, so before + delta == after always.
     */
    public function adjustStock(?User $actor, Product $product, int $newQuantity, string $reason): InventoryTransaction
    {
        return DB::transaction(function () use ($actor, $product, $newQuantity, $reason) {
            $locked = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $current = (int) $locked->stock_quantity;

            return $this->changeStock(
                $actor,
                $locked,
                self::TYPE_ADJUSTMENT,
                $newQuantity - $current,
                null,
                null,
                $reason
            );
        });
    }

    /**
     * Update a product's low-stock / reorder threshold. Pure configuration —
     * does not touch the ledger (no stock changed). Validated upstream.
     */
    public function updateReorderLevel(Product $product, int $reorderLevel): Product
    {
        $product->update(['low_stock_threshold' => $reorderLevel]);

        return $product->refresh();
    }

    /**
     * Dashboard statistics computed in SQL — never loads every product into
     * memory. Status buckets live in the database aggregation:
     *
     *   in_stock     quantity >  reorder_level
     *   low_stock    0 < quantity <= reorder_level
     *   out_of_stock quantity == 0
     *
     * Soft-deleted products still count (they physically hold stock); they are
     * consistent with the admin inventory overview which uses withTrashed().
     */
    public function statistics(): array
    {
        $rows = Product::query()
            ->withTrashed()
            ->selectRaw("
                CASE
                    WHEN stock_quantity = 0 THEN ?
                    WHEN stock_quantity <= low_stock_threshold THEN ?
                    ELSE ?
                END AS stock_state,
                COUNT(*) AS total,
                COALESCE(SUM(stock_quantity), 0) AS units
            ", [self::STATUS_OUT_OF_STOCK, self::STATUS_LOW_STOCK, self::STATUS_IN_STOCK])
            ->groupBy('stock_state')
            ->get()
            ->keyBy('stock_state');

        $totalProducts = (int) $rows->sum('total');
        $counts = [
            self::STATUS_IN_STOCK => (int) ($rows[self::STATUS_IN_STOCK]->total ?? 0),
            self::STATUS_LOW_STOCK => (int) ($rows[self::STATUS_LOW_STOCK]->total ?? 0),
            self::STATUS_OUT_OF_STOCK => (int) ($rows[self::STATUS_OUT_OF_STOCK]->total ?? 0),
        ];

        return [
            'total_products' => $totalProducts,
            'in_stock' => $counts[self::STATUS_IN_STOCK],
            'low_stock' => $counts[self::STATUS_LOW_STOCK],
            'out_of_stock' => $counts[self::STATUS_OUT_OF_STOCK],
            'total_units' => (int) $rows->sum('units'),
        ];
    }

    /**
     * Apply a stock change with a full audit trail.
     *
     * @throws ValidationException when the change would make stock negative
     */
    public function changeStock(
        ?User $actor,
        Product $product,
        string $type,
        int $quantityChange,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null
    ): InventoryTransaction {
        return DB::transaction(function () use ($actor, $product, $type, $quantityChange, $referenceType, $referenceId, $notes) {
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            $before = (int) $product->stock_quantity;
            $after = $before + $quantityChange;

            if ($after < 0) {
                throw ValidationException::withMessages([
                    'quantity_change' => "Insufficient stock. Only {$before} unit(s) of {$product->name} are available.",
                ]);
            }

            $product->update(['stock_quantity' => $after]);

            $transaction = InventoryTransaction::create([
                'product_id' => $product->id,
                'user_id' => $actor?->id,
                'type' => $type,
                'quantity_change' => $quantityChange,
                'stock_before' => $before,
                'stock_after' => $after,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
            ]);

            if ($before > $product->low_stock_threshold && $after <= $product->low_stock_threshold) {
                $this->notifyLowStock($product, $after);
            }

            return $transaction;
        });
    }

    /**
     * Sale: decrement stock when an order is placed.
     */
    public function sell(?User $actor, Product $product, int $quantity, Order $order): InventoryTransaction
    {
        return $this->changeStock(
            $actor,
            $product,
            'sale',
            -$quantity,
            Order::class,
            $order->id,
            'Order '.$order->order_number
        );
    }

    /**
     * Manual stock movement recorded by an admin/staff member.
     */
    public function adjust(?User $actor, Product $product, int $quantityChange, string $type = 'adjustment', ?string $reason = null): InventoryTransaction
    {
        return $this->changeStock($actor, $product, $type, $quantityChange, null, null, $reason);
    }

    /**
     * Return/restock, used by refunds and cancelled orders, so the ledger stays
     * consistent with the matching sale transaction.
     */
    public function returnStock(?User $actor, Product $product, int $quantity, Order $order, string $notes = 'Restocked'): InventoryTransaction
    {
        return $this->changeStock(
            $actor,
            $product,
            'return',
            $quantity,
            Order::class,
            $order->id,
            $notes
        );
    }

    /**
     * Notify every active admin that a product is at/below its low-stock threshold.
     */
    private function notifyLowStock(Product $product, int $stockAfter): void
    {
        $admins = User::where('role', 'admin')
            ->where('status', 'active')
            ->get();

        foreach ($admins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'type' => 'low_stock',
                'title' => "Low stock: {$product->name}",
                'message' => "Only {$stockAfter} unit(s) of {$product->name} remain (threshold: {$product->low_stock_threshold}).",
                'data' => [
                    'product_id' => $product->id,
                    'stock_quantity' => $stockAfter,
                ],
            ]);
        }
    }
}