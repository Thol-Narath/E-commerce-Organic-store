<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Supplier and supplier-purchase-order business logic.
 *
 *   - Supplier purchase orders snapshot product name/SKU/unit cost at order
 *     time so historical orders stay valid when a product changes.
 *   - "Ordering from supplier" (placeOrder) creates a draft supplier order;
 *     the admin can later mark it 'ordered' and eventually 'received'.
 *   - Receiving an order adds the ordered quantities to product stock through
 *     InventoryService (ledger type: purchase), recording the supplier order
 *     as the reference so the movement is traceable end-to-end.
 */
class SupplierService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * Create a draft purchase order for a supplier. Totals are always
     * calculated server-side from the submitted line costs.
     */
    public function createOrder(User $user, array $validated): SupplierOrder
    {
        return DB::transaction(function () use ($user, $validated) {
            $items = $validated['items'];
            $shippingFee = round((float) ($validated['shipping_fee'] ?? 0), 2);
            $subtotal = 0.0;

            $products = Product::query()
                ->whereIn('id', array_column($items, 'product_id'))
                ->withTrashed()
                ->get()
                ->keyBy('id');

            foreach ($items as $line) {
                $product = $products[$line['product_id']] ?? null;

                if (! $product) {
                    throw ValidationException::withMessages([
                        'items' => 'One of the selected products no longer exists.',
                    ]);
                }

                $subtotal += (float) $line['unit_cost'] * (int) $line['quantity'];
            }

            $subtotal = round($subtotal, 2);
            $total = round($subtotal + $shippingFee, 2);

            $order = SupplierOrder::create([
                'order_number' => 'SPO-' . now()->format('Ymd') . '-' . bin2hex(random_bytes(2)),
                'supplier_id' => $validated['supplier_id'],
                'user_id' => $user->id,
                'status' => SupplierOrder::STATUS_DRAFT,
                'subtotal' => $subtotal,
                'shipping_fee' => $shippingFee,
                'total' => $total,
                'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($items as $line) {
                $product = $products[$line['product_id']] ?? null;
                $quantity = (int) $line['quantity'];
                $unitCost = (float) $line['unit_cost'];

                SupplierOrderItem::create([
                    'supplier_order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $quantity,
                    'quantity_received' => 0,
                    'unit_cost' => $unitCost,
                    'line_total' => round($unitCost * $quantity, 2),
                ]);
            }

            return $order->load(['supplier', 'items.product' => fn ($q) => $q->withTrashed()]);
        });
    }

    /**
     * Move a draft purchase order to 'ordered'. Only drafts can be ordered;
     * an ordered/placed order cannot be re-ordered.
     */
    public function placeOrder(SupplierOrder $order, ?string $expectedDeliveryDate = null): SupplierOrder
    {
        if ($order->status !== SupplierOrder::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only draft orders can be placed. This order is already ' . $order->status . '.',
            ]);
        }

        $order->update([
            'status' => SupplierOrder::STATUS_ORDERED,
            'ordered_at' => now(),
            'expected_delivery_date' => $expectedDeliveryDate ?? $order->expected_delivery_date,
        ]);

        return $order->fresh(['supplier', 'items.product' => fn ($q) => $q->withTrashed()]);
    }

    /**
     * Cancel a draft or ordered purchase order. Cancelling never touches stock.
     */
    public function cancelOrder(SupplierOrder $order, ?string $note = null): SupplierOrder
    {
        if ($order->status === SupplierOrder::STATUS_RECEIVED) {
            throw ValidationException::withMessages([
                'status' => 'A received order cannot be cancelled.',
            ]);
        }

        $order->update([
            'status' => SupplierOrder::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'notes' => $note ?? $order->notes,
        ]);

        return $order->fresh(['supplier', 'items.product' => fn ($q) => $q->withTrashed()]);
    }

    /**
     * Mark a placed order as received. Adds the received quantities to product
     * stock via the inventory ledger (type: purchase, reference: this order)
     * inside the same transaction so stock and ledger stay in sync.
     */
    public function receiveOrder(User $user, SupplierOrder $order, array $received = []): SupplierOrder
    {
        if ($order->status !== SupplierOrder::STATUS_ORDERED) {
            throw ValidationException::withMessages([
                'status' => 'Only placed orders can be marked as received.',
            ]);
        }

        return DB::transaction(function () use ($user, $order, $received) {
            $order = SupplierOrder::query()->whereKey($order->id)->lockForUpdate()->first();

            foreach ($order->items()->get() as $item) {
                $product = Product::whereKey($item->product_id)->first();

                if (! $product) {
                    continue;
                }

                // Default to the full ordered quantity unless a partial
                // receive is provided for this line.
                $receiveQty = isset($received[$item->id])
                    ? (int) $received[$item->id]
                    : (int) $item->quantity;

                if ($receiveQty <= 0) {
                    continue;
                }

                // Record the inbound stock with a purchase ledger entry that
                // references this supplier order so the movement is traceable
                // end-to-end in the inventory audit trail.
                $this->inventory->changeStock(
                    $user,
                    $product,
                    InventoryService::TYPE_PURCHASE,
                    $receiveQty,
                    SupplierOrder::class,
                    $order->id,
                    'Supplier order ' . $order->order_number
                );

                $item->update([
                    'quantity_received' => $item->quantity_received + $receiveQty,
                ]);
            }

            $order->update([
                'status' => SupplierOrder::STATUS_RECEIVED,
                'received_at' => now(),
            ]);

            return $order->fresh(['supplier', 'items.product' => fn ($q) => $q->withTrashed()]);
        });
    }
}