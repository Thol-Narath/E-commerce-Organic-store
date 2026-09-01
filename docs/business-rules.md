# Organic Store E-Commerce System — Business Rules & Constraints

> Phase 9 — Admin Dashboard + Order Management. Version 1.3.0

This document consolidates all business rules, database constraints, and integrity policies that must be enforced by the Laravel backend. It complements the schema in `database-design.md` and the model in `erd.md`.

---

## 1. Roles & Authorization

1. **Three roles only:** `customer`, `staff`, `admin` (stored as a backed ENUM `App\Enums\Role` on `users.role`).
2. **Authorization is always server-side.** Laravel enforces access with middleware (`role:`) and Policies. React is never the authority.
3. **Staff must not have admin capabilities.** Staff are limited to: view products, manage inventory, view orders, update order status, view customers.
4. **A customer may only access their own** cart, wishlist, addresses, orders, reviews, and notifications (Policies/enforced via `user_id` scoping).

### 1.1 Account status (Phase 3)
- `users.status` is a backed ENUM `App\Enums\AccountStatus`: `active`, `inactive`, `banned`.
- Only `active` accounts may log in (403 otherwise) and access protected routes (`active` middleware).
- Middleware aliases registered in `App\Http\Kernel`:
  - `role.admin` → `EnsureUserIsAdmin`
  - `role.staff` → `EnsureUserIsStaff`
  - `role.admin_or_staff` → `EnsureUserIsAdminOrStaff`
  - `active` → `EnsureUserIsActive`

### 1.2 Registration & login (Phase 3)
- Guest registration always creates a **customer**; clients cannot set a role (a `role` field is ignored/rejected).
- Passwords hashed with `Hash::make` (bcrypt) via Eloquent `User` model.
- Login validates credentials, then issues a Sanctum personal access token (`auth-token`).
- Logout revokes the **current** token server-side.
- Login/register are rate-limited (`throttle:6,1`) to mitigate brute force.

### 1.3 Development credentials (seeded by `UserSeeder`)
All seeded passwords are `password`.
| Role     | Email                       |
|----------|-----------------------------|
| Admin    | admin@organicstore.test     |
| Staff    | staff@organicstore.test     |
| Customer | maria@example.com … liza@example.com (5 seeded customers) |

### 1.4 Product & Category management (Phase 4)
- **Manage products = Admin only**; **manage categories = Admin only**. Staff are read-only. Enforced by `role:admin` middleware on all `/api/v1/admin/products*` and `/api/v1/admin/categories*` write routes (staff → 403).
- **Soft delete only.** Products and categories use `deleted_at`; they are never physically removed so historical data stays valid. Deleting a category that still has products returns **409 Conflict** (products must be re-assigned/deleted first).
- **Uniqueness** is enforced on the backend: `categories.slug` and `products.slug`/`products.sku` are unique (soft-deleted rows excluded). A `UniqueSlug` service generates a unique slug (e.g. ordinal suffix) automatically when a duplicate would occur.
- **Product status** is a backed ENUM `active` / `inactive` / `draft`. Only `active` products are shown publicly; the admin index includes inactive/draft.
- **Images** belong to `product_images` (`cascade` on product delete). The first uploaded image is automatically primary; admin can promote another to primary. `public` storage disk (`storage/app/public`) is served at `/storage/...`.
- **Stock** starts at `stock_quantity` and is never allowed to go negative — enforced by `InventoryService` + the immutable ledger (Phase 8, implemented).

---

## 2. Product Stock & Inventory (Phase 8 — implemented)

1. **Never allow** `ordered_quantity > available_stock` — re-checked under `SELECT … FOR UPDATE` inside the checkout transaction so concurrent checkouts cannot over-sell.
2. **Never allow negative stock** — `products.stock_quantity >= 0` always. `InventoryService::changeStock` rejects any change that would drive stock below zero and writes nothing (validation error).
3. **Every stock change creates an `inventory_transactions` record** (`InventoryService`) recording `stock_before`, `stock_after`, signed `quantity_change`, type, actor, and optional reference. The ledger is immutable (never updated/deleted).
4. **Stock operations use DB transactions** with product row locks (order placement, manual adjustments).
5. **Sales decrement stock at order placement** within the atomic checkout transaction — `InventoryService::sell` writes a `sale` entry referencing the order; a failed checkout rolls the decrement back with the whole order. Returns/refunds increment stock via `InventoryService::returnStock` (also logged, `return`).
6. `low_stock_threshold` triggers a `low_stock` notification to every active admin when stock crosses **at/below** the threshold — only on the crossing, never on every movement.
7. **Manual adjustments** (admin/staff) go through `POST …/inventory/adjust` with `type = adjustment | purchase` (`purchase` must be positive; negative only as `adjustment`), are performed by `InventoryService::adjust`, and are always logged with the acting user.

### 2b. Inventory Management (Phase 10 — implemented)

1. **Stock lives on the product** (`products.stock_quantity` + `products.low_stock_threshold`/reorder level). There is **no reservation layer**: available stock `= stock_quantity`, decremented once at order placement. That placement deduction also acts as the (soft) reservation for the unpaid order window.
2. **Stock status is derived, never stored** (so it cannot drift): `out_of_stock` when `stock_quantity == 0`, `low_stock` when `0 < stock_quantity <= reorder_level`, `in_stock` otherwise — computed in SQL by `InventoryService::statistics()` and exposed via `InventoryItemResource.stock_status` (admin) and `ProductResource.availability` (customer, coarse only — exact levels are never exposed publicly).
3. **Reorder level is configuration, not a movement**: updating it (`PATCH …/reorder-level`) never writes a ledger row or changes stock.
4. **Per-product operations** (all behind `InventoryPolicy`, coarse middleware `role.admin_or_staff`):
   - `POST /inventory/{product}/add` → `purchase` ledger entry (reason optional, `quantity ≥ 1`).
   - `POST /inventory/{product}/remove` → `adjustment` ledger entry with a **mandatory reason** (`quantity ≥ 1`, capped by available stock).
   - `POST /inventory/{product}/adjust` **sets an absolute target**; the delta is computed server-side under a row lock and recorded (never the raw target).
   - The **ledger** (global `GET /inventory/transactions` and per-product `GET /inventory/{product}/transactions`) is **admin-only**; the ledger is read-only and historical rows are never mutated or deleted.
5. **The client never sends prices, totals, deltas or new stock values as truth.** For `add`/`remove` it sends a quantity; for `adjust` an absolute target; Laravel derives, validates and records everything with the acting user attached.
6. **Idempotency across the order/payment lifecycle** (guarded inside atomic transitions, so no double movement is possible):
   - Placement decrements stock once (`sale`); confirming payment never touches stock again — a duplicate webhook/confirmation is harmless.
   - Payment failure/expiry keeps stock deducted (`pending` order the customer can retry — the reservation); a cancelled payment attempt never restores stock by itself.
   - Cancellation (admin) restores stock exactly once per order (`return`); a second cancellation attempt is rejected and cannot double-restore.
7. **Statistics** count every product including soft-deleted ones (they physically hold stock — ledger rows stay valid even after a product is deleted).
8. Soft-deleted products still appear in the admin inventory overview/detail (via `withTrashed`) so any remaining stock remains accountable, but are hidden from the customer catalog.

---

## 3. Cart Rules

1. A customer has **one active cart** (`carts.user_id` unique).
2. **Cannot add** products that are:
   - `inactive`/`draft` (status), or
   - out of stock (`stock_quantity == 0`), or
   - exceeding available stock.
3. **Cannot set quantity** such that `quantity > stock_quantity`, or below `min_order_qty`.
4. One line per product per cart (`UNIQUE(cart_id, product_id)`); adding an existing product increments quantity (capped by stock).
5. Prices are **not** stored in the cart; they are fetched from the live product and re-validated at checkout.
6. When an order is placed, the cart is marked `converted` (and emptied).
7. **Implemented in Phase 6** — cart/wishlist endpoints enforce these rules server-side via `CartService`/`WishlistService` (validation errors use field messages, e.g. `["quantity"]`).

### 3b. Wishlist Rules (Phase 6)

1. A customer has **one wishlist** (`wishlists.user_id` unique).
2. **Cannot add** products that are `inactive`/`draft`/trashed (availability check).
3. Each product appears **at most once** (`UNIQUE(wishlist_id, product_id)`); duplicate adds are idempotent.
4. Ownership always derives from the authenticated user; accessing another user's wishlist item returns `404`.
5. **Move to cart** adds the product with quantity 1 (merging into an existing cart line via the cart rules) and removes it from the wishlist; it fails with `422` if the product is no longer active or in stock.

### 3c. Address Rules (Phase 7)

1. A customer **owns their addresses** — any access to another customer's address returns `404` (enforced via `user_id` scoping in `AddressService`).
2. The **first address** a customer saves automatically becomes their default.
3. Promoting an address to default **clears** the others (`is_default` toggle inside a transaction).
4. Deleting the default address promotes the **oldest remaining** address.
5. An address that is referenced by historical orders (via `orders.address_id`) **cannot be deleted** — the backend returns `409 Conflict` to preserve audit integrity.
6. `orders.shipping_address_snapshot` keeps the shipping details valid even if the source address is later edited or deleted.

### 3d. Checkout Rules (Phase 7)

1. **The client only submits `address_id`.** Prices, quantities, subtotal, discount, shipping fee, tax and total are computed by `OrderService::placeOrder()` from lively-validated data; any client-supplied totals/status are ignored.
2. Checkout requires an **active, non-empty cart** and a **valid owned address** (empty cart → `422 errors.cart`; another customer's address → `404`).
3. Every line must be an **active, in-stock product** and the quantity must not exceed `stock_quantity`; otherwise checkout fails atomically with `422 errors.cart_item` (no partial order).
4. Order + `order_items` (with `product_name`, `product_sku`, `unit_price` snapshots) + `shipping_address_snapshot` + **stock reservation** (`InventoryService::sell`, one `sale` ledger entry per line, re-validated under a row lock) are created inside a **single DB transaction**, then the cart is cleared. Any failure rolls back the order, the ledger and the stock decrement together.
5. `order_number` is generated server-side (unique). New orders start at `status = pending`, `payment_status = unpaid`; `placed_at` records placement time.
6. Shipping fee is `config('store.shipping_fee')` (`STORE_SHIPPING_FEE`). Exposed read-only to the customer for display via `GET /api/v1/settings/public`.
7. **Duplicate submission** after a successful order is rejected (the cart is already empty).

---

1. **Order totals are always computed by Laravel** — never trusted from the React client. Laravel computes:
   - line `unit_price` (from live product or validated snapshot)
   - `subtotal` (Σ line totals)
   - `discount` (validated coupon)
   - `shipping_fee` (from settings / rule)
   - `tax`
   - `total = subtotal + shipping_fee + tax − discount`
2. **`order_items` store snapshots** of `product_name`, `product_sku`, and `unit_price` so historical orders stay valid if the product changes or is disabled.
3. `order_number` is a unique human-readable reference.
4. Orders are **immutable audit records** — the application never hard-deletes them.
5. `status` lifecycle: `pending → processing → shipped → delivered`, with `cancelled` and `refunded` terminal states.
6. `payment_status` lifecycle: `unpaid → paid / failed`, with `refunded`.
7. Placing an order may only be cancelled while `pending`; once `processing` it cannot be cancelled by the customer. *(Customer cancel/track endpoints remain open for a later phase; `InventoryService::returnStock` is ready to release reserved stock when they land.)*
8. The shipping address is snapshotted (`shipping_address_snapshot`) so edits to the user's address do not alter historical orders.

### 4b. Admin Order Management (Phase 9 — implemented)

1. **Admin/staff status changes are strictly sequential**: `pending → confirmed → processing → shipped → delivered`. No skips and no backward moves. `delivered`, `cancelled` and `refunded` are terminal — no further transitions.
2. **Cancellation is a dedicated endpoint**, not a status patch. Allowed only from `pending`, `confirmed` or `processing`. On cancel, `AdminOrderService`:
   - restores every line's reserved stock (`InventoryService::returnStock`, `return` ledger entries),
   - retires any pending payment attempts (`payment_status = cancelled`),
   - **never** sets `payment_status` for already-paid orders — refunds remain a manual finance action outside the system (no auto-refund).
3. `paid` orders cancelled in error are not refundable through the API and hold at `payment_status = paid`; `total_revenue` counts only genuinely `paid` orders.
4. Every status change that reaches Laravel creates an `order_status_histories` row (old_status, new_status, optional note ≤ 500 chars, acting admin/staff). The customer-facing order detail never exposes admin notes.
5. **Internal notes** (`order_notes`, admin only) are free-form up to 1,000 characters and never shown on the storefront.
6. Historical information (product snapshots, shipping snapshot, prices) is immutable audit data; admin order listing/detail reads only.
7. Authorization: order listing/detail are available to **admin and staff**. Status updates are available to **admin and staff** (narrow transition set). Cancellation and notes are **admin-only** (enforced by `OrderPolicy` + route middleware).

---

## 5. Payment Rules (Phase 8 — ABA PayWay)

1. **The client only submits `payment_method`** (`aba_pay` / `khqr` / `card`). The
   `payments.amount` and `currency` ALWAYS come from the stored `orders.total` (server-side);
   client-supplied amounts, currencies or statuses are ignored.
2. Only methods enabled in `config/payway.php` (`payway.methods.*`) and returned by
   `GET /api/v1/payment-methods` may be used; anything else is rejected `422`.
3. Payment gateway credentials **never reach React** and are **never stored** in the DB or
   code — they live in `.env` (`payway.merchant_id`, `payway.sandbox`, etc.).
4. An order can have **many attempts but only ONE success settles it**: the first attempt the
   gateway confirms (verified webhook `status=0`, or check-transaction `APPROVED` with
   matching amount/currency) is marked `paid` and the order flips to `payment_status=paid`,
   `status=confirmed`; all other pending siblings are cancelled inside the same DB transaction.
5. **Webhook is authoritative and idempotent.** Only the HMAC-SHA512 signature in the
   `X-PayWay-Hmac-SHA512` header is trusted; a missing/forged signature is rejected `400`
   BEFORE any state change, and duplicate successes are no-ops.
6. When the gateway reports an amount/currency (check-transaction), they are verified against
   the stored order total; a mismatch is rejected `422` and the payment is NOT marked paid.
7. `payment_status` lifecycle on the attempt: `pending → paid | failed | expired | cancelled | refunded`.
   Only `paid` (and later `refunded`) touches the order. Failed/expired/cancelled attempts never
   alter order status, which stays `pending`/`unpaid` for a retry.
8. Pending attempts **expire automatically** (`expires_at`, `payway.lifetime`) — checked by the
   `CheckPendingPaymentsJob` on the 1-minute scheduler — and can also be rejected on a
   gateway "transaction not found" (code 6) at refresh time.
9. Hosted card checkout is embedded via a **signed, expiring URL**; card data is entered on the
   bank's page and this store never sees or stores it (no card/CVV/PIN/OTP fields exist).
10. Payments are **immutable audit records** — never hard-deleted; the legacy ENUM constraint
    (`pending/paid/failed/refunded`) was widened to a `VARCHAR` with the new lifecycle values.

---

## 6. Review Rules

1. **A customer can review a product only if they purchased it.**
   - Verified via `reviews.order_id` pointing to a `delivered` order containing the product.
2. **One review per product per customer** (`UNIQUE(user_id, product_id)`).
3. `rating` is constrained to **1–5**.
4. Reviews are moderated: created as `pending`; admin approves or rejects before public display.
5. Only `approved` reviews are exposed via public product endpoints.

---

## 7. Coupon Rules

1. **Coupons are always validated on the backend** (never trust client). Validation checks:
   - exists and `status = active`
   - within `starts_at`/`expires_at`
   - `subtotal >= min_order_amount`
   - `used_count < usage_limit`
   - per-user count (`coupon_usages`) `< per_user_limit`
   - not already used on this order (`UNIQUE(coupon_id, order_id)`)
2. `type = percentage` applies `value%` capped by `max_discount`; `type = fixed` applies a flat `value`.
3. Each use inserts a row into `coupon_usages` and increments `used_count`.
4. A coupon cannot be applied to an order twice.
5. A coupon with `expires_at` in the past, or that has reached its limits, is rejected with a meaningful message.

---

## 8. Data Integrity / Constraints

### 8.1 Global
- Every table has a `BIGINT UNSIGNED` auto-increment primary key `id`.
- Every foreign key has an index and a defined delete rule (see `erd.md`).
- Timestamps (`created_at`, `updated_at`) on all tables.

### 8.2 Unique Constraints
| Table           | Column(s)                | Reason |
|-----------------|--------------------------|--------|
| users           | email                    | one account per email |
| categories      | slug                     | URL uniqueness |
| products        | slug, sku                | URL + SKU uniqueness |
| carts           | user_id                  | one active cart per user |
| wishlists       | user_id                  | one wishlist per user |
| cart_items      | (cart_id, product_id)    | one line per product/cart |
| wishlist_items  | (wishlist_id, product_id)| one save per product/wishlist |
| orders          | order_number             | order reference uniqueness |
| reviews         | (user_id, product_id)    | one review per product/customer |
| coupons         | code                     | coupon code uniqueness |
| coupon_usages   | (coupon_id, order_id)    | no double application |
| settings        | key                      | config key uniqueness |

### 8.3 Check / Range Constraints (enforced in Laravel validation and/or DB)
- `reviews.rating` ∈ {1,2,3,4,5}
- `stock_quantity >= 0`
- `cart_items.quantity >= 1` and `>= min_order_qty` (server)
- `quantity <= stock_quantity`
- monetary columns `>= 0`
- ENUM columns restricted to defined values

### 8.4 Not-Null
- All PKs, FKs to required parents, names, slugs, prices, statuses, and totals are NOT NULL (see `database-design.md` per column).

---

## 9. Historical Integrity Policy

1. **Orders, order_items, coupon_usages, inventory_transactions are immutable** — the application never updates or deletes them (no soft-delete column; they simply are not deletable by the app).
2. **products, categories, users, coupons are soft-deleted** (`deleted_at`), never physically removed, so linked historical data remains valid.
3. **Snapshots** (product name/SKU/price in `order_items`, full `shipping_address_snapshot` in `orders`) guarantee historical accuracy regardless of later edits.
4. **FK delete rules** are chosen so that destroying a parent never corrupts or silently destroys important historical rows (`RESTRICT`/`SET NULL` for history-bearing FKs; `CASCADE` only for subordinate detail tables).

---

## 10. Validation & Trigger Summary

| Operation                          | Backend validation (Laravel)                                   |
|------------------------------------|----------------------------------------------------------------|
| Register / login                   | email format, uniqueness, password strength, rate-limited      |
| Add to cart                        | product active, in stock, quantity ≤ stock, ≥ min_order_qty    |
| Add to wishlist                    | product exists & active                                        |
| Place order                        | cart non-empty, stock, address exists, coupon valid, totals server-side |
| Apply coupon                       | active window, min order, usage limits                         |
| Submit review                      | purchased (delivered order contains product), rating 1–5, one per user/product |
| Inventory adjust                   | new stock ≥ 0, positive or negative change logged              |
| Order status change                | valid lifecycle transition, authorized role                    |
| Password change                    | current password verification, new password policy             |
| Profile / address update           | ownership (Policy), field rules                                |
