<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderNote;
use App\Models\OrderStatusHistory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin + staff order management (Phase 9 — Admin Order Dashboard).
 *
 * Responsibilities:
 *   - paginated listing with backend search / filters / sort;
 *   - order detail with all snapshot data, payments, status audit trail and
 *     admin notes — always eager-loaded so resources never trigger N+1;
 *   - controlled status transitions recorded in `order_status_histories`;
 *   - cancellation via a dedicated action that restores reserved stock
 *     (InventoryService::returnStock) and never auto-refunds;
 *   - order statistics computed with COUNT/SUM/GROUP BY aggregates.
 *
 * Business rules (docs/business-rules.md §4, §9):
 *   - historical item prices and the shipping address come from snapshots;
 *   - delivered/cancelled/refunded orders are terminal states;
 *   - cancelling releases stock and writes ledger `return` entries;
 *   - cancelling a paid order never touches payment_status (refund handled by
 *     the finance team — a human step, not an automatic transaction).
 */
class AdminOrderService
{
    /**
     * All order statuses (kept in sync with the orders.status column).
     */
    public const STATUSES = [
        'pending', 'confirmed', 'processing', 'packed', 'shipped', 'out_for_delivery', 'delivered', 'cancelled', 'refunded',
    ];

    /**
     * Allowed forward transitions. Cancellation is intentionally NOT here —
     * it is a dedicated endpoint (AdminOrderService::cancel).
     */
    private const TRANSITIONS = [
        'pending' => ['confirmed'],
        'confirmed' => ['processing'],
        'processing' => ['packed'],
        'packed' => ['shipped'],
        'shipped' => ['out_for_delivery'],
        'out_for_delivery' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
        'refunded' => [],
    ];

    /**
     * Whitelisted sort keys mapped to safe orderBy clauses (default newest).
     */
    private const SORT_WHITELIST = [
        'newest' => ['placed_at', 'desc'],
        'oldest' => ['placed_at', 'asc'],
        'total_high' => ['total', 'desc'],
        'total_low' => ['total', 'asc'],
    ];

    /**
     * Shorthand date periods (applied to the `placed_at` column).
     */
    private const DATE_PERIODS = [
        'today' => ['days' => 0, 'fullDay' => true],
        'yesterday' => ['days' => 1, 'fullDay' => true],
        'last_7_days' => ['days' => 7, 'fullDay' => false],
        'last_30_days' => ['days' => 30, 'fullDay' => false],
        'this_month' => ['month' => true],
    ];

    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * Eager loads shared by listing and detail so resources never N+1.
     * Items + payments are history/catalog-safe (products loaded with trashed).
     */
    private function eagerLoads(): array
    {
        return [
            'items' => fn ($q) => $q->orderBy('id'),
            'items.product' => fn ($q) => $q->withTrashed(),
            'items.product.primaryImage',
            'payments' => fn ($q) => $q->orderByDesc('id'),
            'user:id,name,email,phone',
            'statusHistories' => fn ($q) => $q->orderBy('id'),
            'statusHistories.admin:id,name,email',
            'adminNotes' => fn ($q) => $q->orderBy('id'),
            'adminNotes.admin:id,name,email',
        ];
    }

    /**
     * Paginated admin order listing with backend search/filter/sort.
     *
     * Supported filters:
     *   - search          order number, customer name / email / phone (LIKE)
     *   - status          orders.status
     *   - payment_status  orders.payment_status (aggregate: is the order paid)
     *   - payment_method  payments.payment_method (an attempt used this method)
     *   - date_period    today | yesterday | last_7_days | last_30_days | this_month
     *   - date_from/to    explicit placed_at range (overrides date_period)
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters, int $perPage): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        $query = Order::with($this->eagerLoads());

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['payment_status'])
            && in_array($filters['payment_status'], ['unpaid', 'paid', 'refunded', 'failed'], true)) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (! empty($filters['payment_method'])) {
            $query->whereHas('payments', function ($q) use ($filters) {
                $q->where('payment_method', $filters['payment_method']);
            });
        }

        $this->applyDateFilter($query, $filters);

        $sort = $filters['sort'] ?? 'newest';
        if (isset(self::SORT_WHITELIST[$sort])) {
            [$column, $dir] = self::SORT_WHITELIST[$sort];
            $query->orderBy($column, $dir)->orderBy('id', $dir);
        } else {
            $query->orderByDesc('placed_at')->orderByDesc('id');
        }

        return $query->paginate(max(1, min(50, $perPage)))->withQueryString();
    }

    /**
     * A single order with every dashboard relation loaded.
     */
    public function show(Order $order): Order
    {
        return $order->load($this->eagerLoads());
    }

    /**
     * Order dashboard KPIs — pure aggregates, never loads all orders.
     *
     * total_revenue is the sum of orders.total where payment_status = 'paid'.
     * An order is only 'paid' after the PayWay gateway confirms the payment
     * (see PaymentService::confirmPayment), so failed / expired / cancelled
     * attempts can never inflate revenue.
     */
    public function statistics(): array
    {
        $ordersByStatus = array_map('intval', Order::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all());

        $ordersByPaymentStatus = array_map('intval', Order::query()
            ->selectRaw('payment_status, count(*) as total')
            ->groupBy('payment_status')
            ->pluck('total', 'payment_status')
            ->all());

        $paidRevenue = (float) Order::where('payment_status', 'paid')->sum('total');

        return [
            'total_orders' => (int) Order::count(),
            'orders_by_status' => $ordersByStatus + array_fill_keys(self::STATUSES, 0),
            'orders_by_payment_status' => $ordersByPaymentStatus
                + array_fill_keys(['unpaid', 'paid', 'refunded', 'failed'], 0),
            'paid_orders_count' => (int) ($ordersByPaymentStatus['paid'] ?? 0),
            'total_revenue' => number_format($paidRevenue, 2, '.', ''),
            'currency' => strtoupper((string) config('payway.currency', 'USD')),
        ];
    }

    /**
     * Apply a controlled forward status transition.
     *
     * @throws ValidationException when the transition is not allowed or the
     *                             status is unchanged
     */
    public function updateStatus(Order $order, User $actor, string $newStatus, ?string $note = null): Order
    {
        if (! in_array($newStatus, self::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'The requested status is not valid.',
            ]);
        }

        if ($newStatus === $order->status) {
            throw ValidationException::withMessages([
                'status' => "This order is already {$order->status}.",
            ]);
        }

        $allowed = self::TRANSITIONS[$order->status] ?? [];
        if (! in_array($newStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "Order status cannot change from {$order->status} to {$newStatus}.",
            ]);
        }

        return DB::transaction(function () use ($order, $actor, $newStatus, $note) {
            $oldStatus = $order->status;
            $order->update(['status' => $newStatus]);
            $this->recordTransition($order, $actor, $oldStatus, $newStatus, $note);

            return $order->fresh($this->eagerLoads());
        });
    }

    /**
     * Cancel an order.
     *
     * Allowed only while pending / confirmed / processing. The reserved stock
     * is restored (ledger `return` entries) and any pending payment attempts
     * are retired. A paid order is never auto-refunded — the caller surfaces
     * the "refund processing required" message.
     *
     * @return array{order: Order, refund_required: bool}
     *
     * @throws ValidationException when the order cannot be cancelled
     */
    public function cancel(Order $order, User $actor, ?string $note = null): array
    {
        if (! in_array($order->status, ['pending', 'confirmed', 'processing'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Only pending, confirmed or processing orders can be cancelled.',
            ]);
        }

        return DB::transaction(function () use ($order, $actor, $note) {
            $refundRequired = $order->payment_status === 'paid';

            $this->restock($order, $actor);

            $oldStatus = $order->status;
            $order->update(['status' => 'cancelled']);
            $this->recordTransition(
                $order,
                $actor,
                $oldStatus,
                'cancelled',
                $note ?: 'Cancelled by '.$actor->name
            );

            // Retire any pending attempts so the gateway can never settle a
            // cancelled order later.
            Payment::where('order_id', $order->id)
                ->where('payment_status', 'pending')
                ->update(['payment_status' => 'cancelled']);

            return [
                'order' => $order->fresh($this->eagerLoads()),
                'refund_required' => $refundRequired,
            ];
        });
    }

    /**
     * Attach a free-form admin note to an order.
     */
    public function addNote(Order $order, User $actor, string $note): OrderNote
    {
        $noteModel = OrderNote::create([
            'order_id' => $order->id,
            'admin_id' => $actor->id,
            'note' => $note,
        ]);

        return $noteModel->load('admin:id,name,email');
    }

    /**
     * Append an audit row for a status transition. Rows are immutable once
     * written — the timeline is built purely from these records.
     */
    private function recordTransition(
        Order $order,
        ?User $actor,
        string $oldStatus,
        string $newStatus,
        ?string $note
    ): OrderStatusHistory {
        return OrderStatusHistory::create([
            'order_id' => $order->id,
            'admin_id' => $actor?->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note' => $note,
        ]);
    }

    /**
     * Restore reserved stock for every order line when an order is cancelled,
     * writing a `return` ledger entry per line (matches the original `sale`).
     * Soft-deleted products are restocked too (their stock column is live);
     * only a missing row is skipped.
     */
    private function restock(Order $order, User $actor): void
    {
        foreach ($order->items()->get() as $item) {
            if (! $item->product_id) {
                continue;
            }

            $product = Product::withTrashed()->whereKey($item->product_id)->first();

            if (! $product) {
                continue;
            }

            $this->inventory->returnStock(
                $actor,
                $product,
                (int) $item->quantity,
                $order,
                'Restocked after order cancellation'
            );
        }
    }

    /**
     * Apply the placed_at date filter. Explicit date_from/date_to win over the
     * shorthand date_period.
     *
     * @param  array<string, mixed>  $filters
     */
    private function applyDateFilter($query, array $filters): void
    {
        $from = $filters['date_from'] ?? null;
        $to = $filters['date_to'] ?? null;

        if ($from || $to) {
            if ($from) {
                $query->where('placed_at', '>=', Carbon::parse($from)->startOfDay());
            }
            if ($to) {
                $query->where('placed_at', '<=', Carbon::parse($to)->endOfDay());
            }

            return;
        }

        $period = $filters['date_period'] ?? null;
        if (! isset(self::DATE_PERIODS[$period])) {
            return;
        }

        $now = now();
        if (isset(self::DATE_PERIODS[$period]['month'])) {
            $query->where('placed_at', '>=', $now->copy()->startOfMonth());
        } else {
            $days = self::DATE_PERIODS[$period]['days'];
            $fullDay = self::DATE_PERIODS[$period]['fullDay'];

            if ($fullDay) {
                $day = $now->copy()->subDays($days);
                $query->whereBetween('placed_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()]);
            } else {
                $query->where('placed_at', '>=', $now->copy()->subDays($days)->startOfDay());
            }
        }
    }
}