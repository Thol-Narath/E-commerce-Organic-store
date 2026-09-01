# Inventory Management — Phase 10

## Purpose

Central, auditable inventory control for the admin dashboard: live stock levels
per product, add / remove / adjust operations, a configurable reorder level, and
an immutable movement ledger across the whole order lifecycle. Also exposes a
coarse availability status on the customer storefront.

## Design decisions

- **Stock lives on the product.** `products.stock_quantity` (unsigned) +
  `products.low_stock_threshold` (reorder level). No separate inventory table
  and no reservation layer — `available = stock_quantity`.
- **Deduction at placement.** Stock is decremented once when an order is placed
  (ledger type `sale`) inside the checkout transaction. That deduction doubles
  as the reservation while the order is unpaid.
- **Confirming payment never moves stock** — it only flips the order/payment
  status. A duplicate webhook confirmation is therefore harmless.
- **Payment failure/expiry keeps the stock deducted** (`pending` order, customer
  retries). A cancelled payment attempt never restores stock on its own.
- **Cancellation restores exactly once.** Admin cancel returns every line's
  quantity (`return` ledger entries); a second cancel attempt is rejected, so a
  double restore is impossible.
- **Manual changes are absolute or delta, server-side.** `add` and `remove` take
  a quantity; `adjust` takes an absolute target. Laravel locks the row, computes
  the delta and writes `stock_before`/`stock_after` — prices/totals/new-stock
  values from the client are never trusted.

## Stock status (derived, never stored)

| Status | Condition |
|---|---|
| `out_of_stock` | `stock_quantity == 0` |
| `low_stock` | `0 < stock_quantity <= reorder_level` |
| `in_stock` | `stock_quantity > reorder_level` |

Computed server-side (`InventoryService::stockStatusFor`), aggregated in SQL by
`InventoryService::statistics`, and mirrored on the frontend in
`frontend/src/utils/inventory.js`. Crossing at/below the reorder level still
fires the existing `low_stock` admin notification (only on the crossing).

## Ledger (`inventory_transactions`)

Immutable rows; never updated or deleted. Every movement records:

- `type` — `initial` | `purchase` | `sale` | `adjustment` | `return`
- signed `quantity_change` and `stock_before` / `stock_after`
- `user_id` (acting admin/staff — null for system/low-stock crosses)
- `reference_type`/`reference_id` (morph to `Order` for sales/returns)

| Operation | Type | Reference |
|---|---|---|
| Order placement | `sale` | the order |
| Order cancellation | `return` | the order |
| `add` endpoint | `purchase` | — |
| `remove` endpoint | `adjustment` | — |
| `adjust` endpoint | `adjustment` | — |
| Seeded opening levels | `initial` | — |

Indexes: `(reference_type, reference_id)` (existing) plus `type`, `created_at`
and `(product_id, type)` (Phase 10 migration `2026_08_31_000001`).

## API surface (Phase 10)

All under `/api/v1`, `auth:sanctum`, coarse middleware `role.admin_or_staff`;
authoritative checks in `InventoryPolicy` (`Product` + `InventoryTransaction`).

| Endpoint | Roles | Notes |
|---|---|---|
| `GET    /admin/inventory` | admin, staff | search (name/SKU/id), stock filter, category filter, whitelisted sorts, pagination (default 20) |
| `GET    /admin/inventory/statistics` | admin, staff | `total_products`, `in_stock`, `low_stock`, `out_of_stock`, `total_units` |
| `GET    /admin/inventory/{product}` | admin, staff | detail incl. category, image, status |
| `POST   /admin/inventory/{product}/add` | admin, staff | `purchase`; `quantity ≥ 1`, reason optional |
| `POST   /admin/inventory/{product}/remove` | admin, staff | `adjustment`; `quantity ≥ 1`, reason required |
| `POST   /admin/inventory/{product}/adjust` | admin, staff | absolute target `quantity ≥ 0`, reason required |
| `PATCH  /admin/inventory/{product}/reorder-level` | admin, staff | `reorder_level ≥ 0` |
| `GET    /admin/inventory/{product}/transactions` | admin only | per-product ledger, `type` filter |
| `GET    /admin/inventory/transactions` | admin only | global ledger, product/type/search/date filters |

Legacy `POST /admin/inventory/adjust` and the `staff/inventory` prefix remain for
backwards compatibility; all new capabilities use the per-product endpoints.
`{product}` is `whereNumber`, so the literal `statistics`/`transactions` routes
are never shadowed.

Mutation responses return both the refreshed product and the new ledger row so
the UI can update its summary and history in one round trip.

## Frontend (React)

- `services/adminInventoryService.js` — API client.
- `components/inventory/InventoryStatusBadge.jsx` — stock badge + labels
  (used in admin screens and on the storefront).
- `components/inventory/StockActionModal.jsx` — shared add/remove/adjust modal
  with live stock preview and mandatory reasons for destructive actions.
- `pages/admin/AdminInventory.jsx` — statistics strip (clickable status filters),
  search / category / stock filters, whitelisted sorts, responsive table ↔ cards,
  pagination, quick add/remove actions.
- `pages/admin/AdminInventoryDetail.jsx` — product summary, reorder-level form,
  add/remove/adjust actions and the per-product movement ledger.
- `pages/admin/AdminDashboard.jsx` — inventory health card.
- `pages/admin/AdminProducts.jsx` — stock quantity + status badge, "Stock" link.
- Customer storefront: `ProductCard` / `ProductDetailsPage` show the derived
  availability badge and disable Add-to-Cart for `out_of_stock` products.

## Security & permissions

- Staff: view inventory and manage stock (add/remove/adjust/reorder) — per
  AGENTS.md "manage inventory". Staff cannot view any ledger.
- Admin: everything, including global and per-product ledgers.
- Customers/guests: `403`/`401`; customers only ever see the coarse customer
  availability status, never exact levels.

## Tests

`backend/tests/Feature/InventoryApiTest.php` (37 tests) covers: access control for
every new endpoint and role; add/remove/adjust accounting (`stock_before` /
`stock_after` / actor / reason); negative-stock rejection writing no ledger row;
reorder-level changes without touching stock; statistics matching the database;
detail shape and 404s; ledger pagination + type filters; search by name, SKU and
ID; stock-status filters; sorting; pagination; and order/payment lifecycle
idempotency (payment confirmation and duplicate cancellation never double-move
stock).