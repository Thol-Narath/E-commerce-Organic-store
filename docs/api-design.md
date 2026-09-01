# Organic Store E-Commerce System — REST API Design

> Phase 4 — Catalog (Products & Categories). Version 1.0.0

This document defines the versioned REST API structure and endpoints for the Laravel 10 backend. It is a **design document** — routes/controllers are implemented in later phases.

---

## 1. Conventions

### 1.1 Base Path & Versioning
- Base path: `/api/v1`
- All endpoints are under this prefix.
- Versioned via the route file so future breaking changes can live under `/api/v2`.

### 1.2 Response Envelope
Every endpoint returns a consistent JSON envelope.

Success:
```json
{
  "success": true,
  "message": "Success",
  "data": { }
}
```

Error:
```json
{
  "success": false,
  "message": "Something went wrong",
  "data": null
}
```

Validation error (failure with field errors included in `data`):
```json
{
  "success": false,
  "message": "The given data was invalid",
  "data": {
    "email": ["The email field is required."]
  }
}
```

### 1.3 HTTP Status Codes
| Code | Meaning                                   | Used for                                  |
|------|-------------------------------------------|-------------------------------------------|
| 200  | OK                                        | Successful reads / updates                |
| 201  | Created                                   | Successful resource creation (orders, etc.) |
| 204  | No Content                                | Successful deletion                       |
| 400  | Bad Request                               | Malformed request, business rule violation |
| 401  | Unauthorized                              | Missing/invalid token                     |
| 403  | Forbidden                                 | Authenticated but not permitted           |
| 404  | Not Found                                 | Resource does not exist                   |
| 409  | Conflict                                  | Stock/conflict business errors            |
| 422  | Unprocessable Entity                      | Validation failures                       |
| 429  | Too Many Requests                         | Rate limiting                             |
| 500  | Internal Server Error                     | Unexpected error                          |

### 1.4 Authentication
- Bearer token issued by **Laravel Sanctum**.
- Protected routes require `Authorization: Bearer <token>`.
- A shared Axios interceptor on the frontend attaches the token and unwraps the envelope.
- Token name used by the backend: `auth-token`. Logout revokes the current token server-side.

---

## 2. Public / Guest Endpoints (no auth)

| Method | Endpoint                     | Description                        |
|--------|------------------------------|------------------------------------|
| POST   | /api/v1/auth/register        | Customer registration              |
| POST   | /api/v1/auth/login           | Login (customer/admin/staff)       |
| GET    | /api/v1/categories           | List active categories (tree)      |
| GET    | /api/v1/categories/{slug}    | Category detail                    |
| GET    | /api/v1/products             | List/search/filter products        |
| GET    | /api/v1/products/{slug}      | Product detail                     |
| GET    | /api/v1/products/{slug}/reviews | Approved reviews for a product  |
| GET    | /api/v1/payment-methods  | Enabled payment methods (Phase 8) |
| GET    | /api/v1/settings/public      | Public settings (store info)       |

**Query params (products):** `search`, `category`, `min_price`, `max_price`, `sort`, `page`, `per_page`, `featured`.

---

## 3. Authenticated Endpoints (Phase 3 — implemented)

Require `auth:sanctum` + `active` status. Guest-only, customer, admin, and staff scopes are enforced by middleware (`role.admin`, `role.staff`, `role.admin_or_staff`).

### 3.1 Auth & Profile (implemented)
| Method | Endpoint                    | Middleware          | Description                             |
|--------|-----------------------------|---------------------|-----------------------------------------|
| POST   | /api/v1/auth/register       | throttle:6,1        | Customer registration (role always customer) |
| POST   | /api/v1/auth/login          | throttle:6,1        | Login → returns `token` + `user`        |
| POST   | /api/v1/auth/logout         | auth:sanctum, active| Revoke current token                    |
| GET    | /api/v1/auth/me             | auth:sanctum, active| Current user profile (`data.user`)      |
| GET    | /api/v1/profile             | auth:sanctum, active| Current user profile (`data.user`)      |
| PUT    | /api/v1/profile             | auth:sanctum, active| Update name/email/phone                 |
| PUT    | /api/v1/profile/password    | auth:sanctum, active| Change password (revokes other tokens)  |
| GET    | /api/v1/admin/me            | role.admin          | Current admin profile                   |
| GET    | /api/v1/staff/me            | role.admin_or_staff | Current staff/admin profile             |

The `user` resource exposes: `id, name, email, phone, role, status, avatar, email_verified_at, created_at, updated_at`.

> Phase 3 implements authentication, roles, and profile. **Phase 4 implements the catalog**: public products + categories (section 2) and admin product + category management (section 4 below). **Phase 6 implements cart + wishlist** (sections 3.3–3.4). **Phase 7 implements addresses + checkout + orders** (sections 3.2 and 3.5). Review/payment endpoints in sections 3.6–3.8 remain **design-only** and will be built in later phases.

### 3.1a Catalog — Phase 4 (implemented)

Public catalog and admin catalog-management endpoints below are implemented. Required middleware is noted per group.

**Public products (no auth):**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/products | List/search/filter/sort/paginate active products. Query params: `search`, `category` (slug), `category_id`, `min_price`, `max_price`, `sort` (`price_asc`, `price_desc`, `newest`, `name_asc`, `name_desc`, `featured`, `popular`), `featured` (true/false), `page`, `per_page`. Only `active` products are returned. |
| GET | /api/v1/products/featured | Featured active products (limited 8). |
| GET | /api/v1/products/{slug} | Public detail of an active product (images, category). 404 if unknown or inactive. |

**Public categories (no auth):**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/categories | List active categories with `products_count`. |
| GET | /api/v1/categories/{slug} | Category detail including its active products. |
| GET | /api/v1/categories/{slug}/products | Active products belonging to a category (paginated). |

**Admin products (`auth:sanctum` + `role:admin`):**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/admin/products | List all products (incl. inactive/draft, soft-deleted excluded). Supports same search/filter/sort/pagination plus `status`/`is_featured` filters. |
| POST | /api/v1/admin/products | Create product (returns `ProductResource`). |
| GET | /api/v1/admin/products/{id} | Admin detail (incl. inactive/draft) with images. |
| PUT | /api/v1/admin/products/{id} | Update product. |
| DELETE | /api/v1/admin/products/{id} | Soft-delete product. |
| POST | /api/v1/admin/products/{id}/images | Upload a product image (multipart `image`). First upload is auto-set primary. |
| POST | /api/v1/admin/products/{id}/images/{imageId}/primary | Set an image as primary. |
| DELETE | /api/v1/admin/products/{id}/images/{imageId} | Delete a product image. |
| PUT | /api/v1/admin/products/{id}/status | Toggle `status` (`active`/`inactive`/`draft`). |
| PUT | /api/v1/admin/products/{id}/featured | Toggle `is_featured`. |

**Admin categories (`auth:sanctum` + `role:admin`):**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/admin/categories | List all categories (incl. inactive) with `products_count`. |
| POST | /api/v1/admin/categories | Create category (multipart `image`/`icon` optional). |
| GET | /api/v1/admin/categories/{id} | Category detail. |
| PUT | /api/v1/admin/categories/{id} | Update category (multipart icon optional). |
| DELETE | /api/v1/admin/categories/{id} | Soft-delete category. Returns 409 if the category still has products. |

> **Staff** may read public products/categories and (read-only) admin products. Product/category **create/update/delete** are restricted to `admin` via `role:admin` middleware — staff receives 403 on mutations.

### 3.2 Addresses — Phase 7 (implemented)

All address endpoints require `auth:sanctum` (customer) and are always scoped to the
authenticated user; `{address}` is the `addresses.id` and cross-user access returns `404`.

| Method | Endpoint                     | Description                        |
|--------|------------------------------|------------------------------------|
| GET    | /api/v1/addresses            | List my addresses (default first)  |
| POST   | /api/v1/addresses            | Create address                     |
| GET    | /api/v1/addresses/{address}  | Get one own address                |
| PATCH  | /api/v1/addresses/{address}  | Update my address                  |
| DELETE | /api/v1/addresses/{address}  | Delete my address (409 if referenced by historical orders) |
| PATCH  | /api/v1/addresses/{address}/default | Promote to default            |

**Rules:** the first address a customer saves automatically becomes their default; promoting
one clears the rest; deleting the default promotes the oldest remaining one.

**Address resource shape:**

```jsonc
{
  "id": 1,
  "label": "Home",
  "recipient_name": "Jane Doe",
  "recipient_phone": "+1 555 0100",
  "address_line1": "12 Maple Street",
  "address_line2": null,
  "city": "Springfield",
  "state": "IL",
  "postal_code": "62704",
  "country": "USA",
  "is_default": true,
  "created_at": "...",
  "updated_at": "..."
}
```

### 3.3 Cart — Phase 6 (implemented)

All cart endpoints require `auth:sanctum` (customer). Cart ownership always derives
from the authenticated user; client-supplied `user_id`/`cart_id` values are never trusted.
`{cartItem}` is the `cart_items.id` — it must belong to the caller's active cart or a
`404` is returned.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET    | /api/v1/cart | View the caller's cart with server-computed sums. |
| POST   | /api/v1/cart/items | Add a product (`product_id`, `quantity`). Adding twice increments the same line (unique `(cart_id, product_id)`), capped by stock. |
| PATCH  | /api/v1/cart/items/{cartItem} | Set a line quantity. |
| DELETE | /api/v1/cart/items/{cartItem} | Remove a single line. |
| DELETE | /api/v1/cart | Clear the cart (the `carts` row is kept). |

**Validation / business rules (see business-rules.md §3):** product must exist, be `active`
and non-trashed; `quantity >= min_order_qty`; resulting line quantity `<= stock_quantity`;
never negative stock. Prices/totals are always read from the live product by the backend.
Inactive/out-of-stock items already in the cart are flagged `available: false` and can be
removed, but not updated.

**Cart resource shape (`GET /api/v1/cart`, and after every mutation):**

```jsonc
{
  "id": 1,
  "items": [
    {
      "id": 12,                  // cart_items.id
      "quantity": 2,
      "product": { "id": 5, "name": "Organic Avocado", "slug": "...", "price": "3.75",
                   "primary_image": { ... }, "category": { "id": 2, "name": "Produce", "slug": "produce" } },
      "unit_price": "3.75",
      "line_total": "7.50",
      "available": true,
      "created_at": "..." }
  ],
  "subtotal": "7.50",            // server-computed from live prices
  "total_items": 2               // sum of quantities
}
```

### 3.4 Wishlist — Phase 6 (implemented)

All wishlist endpoints require `auth:sanctum` (customer). `{wishlistItem}` is the
`wishlist_items.id` (a design-time draft suggested `productId`; the implementation uses the
wishlist item id so ownership can be enforced and the item removed/moved precisely).
Each product can appear at most once (unique `(wishlist_id, product_id)`); adds are idempotent.

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET    | /api/v1/wishlist | List the caller's wishlist items. |
| POST   | /api/v1/wishlist/items | Add a product (`product_id`). Idempotent — never duplicates. |
| DELETE | /api/v1/wishlist/items/{wishlistItem} | Remove a wishlist line (404 if not owned). |
| POST   | /api/v1/wishlist/items/{wishlistItem}/move-to-cart | Move the item into the cart (quantity 1, merges into an existing line) and remove it from the wishlist. Returns `{ cart, wishlist }`; 422 if the product is no longer active/in stock. |

**Wishlist resource shape (`GET /api/v1/wishlist`):**

```jsonc
{
  "id": 1,
  "items": [
    {
      "id": 7,                   // wishlist_items.id
      "product": { "id": 5, "name": "Organic Avocado", "slug": "...", "price": "3.75",
                   "primary_image": { ... }, "category": { ... } },
      "available": true,
      "created_at": "..." }
  ],
  "total_items": 1               // number of unique products
}
```

### 3.5 Orders & Checkout — Phase 7 (implemented)

All order endpoints require `auth:sanctum` (customer) and are scoped to the authenticated
user; another customer's order number returns `404`.

| Method | Endpoint                     | Description                        |
|--------|------------------------------|------------------------------------|
| POST   | /api/v1/checkout             | Place order from the current cart (body: `address_id`) |
| GET    | /api/v1/orders               | List my orders (paginated, newest first) |
| GET    | /api/v1/orders/{orderNumber} | Order detail + items + shipping snapshot |
| POST   | /api/v1/orders/{order_number}/cancel | Cancel pending order (Phase 8) |
| GET    | /api/v1/orders/{order_number}/track | Track order status (Phase 8)  |

**Checkout (`POST /api/v1/checkout`):** accepts only `address_id`. Every price, subtotal,
discount, shipping fee and total is computed by `OrderService::placeOrder()` server-side; any
client-supplied totals are ignored. The order and its lines are created inside a DB transaction
with a `shipping_address_snapshot`, then the cart is cleared. Empty cart, inactive product, or a
quantity exceeding stock cause a `422` validation error (`errors.cart` / `errors.cart_item`);
an address that does not belong to the user returns `404`.

**Order list response shape:**

```jsonc
{
  "orders": [ { /* OrderResource, no items */ } ],
  "pagination": { "current_page": 1, "per_page": 15, "total": 42, "last_page": 3 }
}
```

**Order resource shape (detail includes `items`):**

```jsonc
{
  "id": 1,
  "order_number": "ORD-20250601-0001",
  "status": "pending",
  "payment_status": "unpaid",
  "subtotal": "7.50",
  "discount": "0.00",
  "shipping_fee": "2.00",
  "tax": "0.00",
  "total": "9.50",
  "shipping_address": { "...": "snapshot of the address at placement time" },
  "notes": null,
  "placed_at": "...",
  "items": [
    {
      "id": 1,
      "product_id": 5,
      "product_name": "Organic Avocado",  // snapshot, immune to later edits
      "product_sku": "AVO",
      "quantity": 2,
      "unit_price": "3.75",
      "line_total": "7.50",
      "product": { /* live product when eager-loaded, null otherwise */ }
    }
  ]
}
```

Money fields are formatted as two-decimal strings (`FormatsMoney`). The shipping fee charged is
`config('store.shipping_fee')` (`STORE_SHIPPING_FEE`); `GET /api/v1/settings/public` exposes the
same value so the customer can preview it.

### 3.6 Reviews
| Method | Endpoint                     | Description                        |
|--------|------------------------------|------------------------------------|
| GET    | /api/v1/reviews/mine         | My reviews                         |
| POST   | /api/v1/reviews              | Submit review for a purchased product |
| PUT    | /api/v1/reviews/{id}         | Edit my review                     |
| DELETE | /api/v1/reviews/{id}         | Delete my review                   |

### 3.7 Notifications
| Method | Endpoint                     | Description                        |
|--------|------------------------------|------------------------------------|
| GET    | /api/v1/notifications        | List my notifications              |
| PUT    | /api/v1/notifications/{id}/read | Mark one as read                 |
| PUT    | /api/v1/notifications/read-all | Mark all as read                 |

### 3.8 Payments — Phase 8 (implemented)

Online (ABA PayWay) payment for an order. All customer endpoints require `auth:sanctum`,
are scoped to the authenticated user, and use the human-readable `order_number`.

| Method | Endpoint                     | Description                        |
|--------|------------------------------|------------------------------------|
| GET    | /api/v1/payment-methods      | Enabled payment methods (public, no auth) |
| POST   | /api/v1/orders/{orderNumber}/payments | Start a payment attempt (`body: { "payment_method": "aba_pay" \| "khqr" \| "card" }`) → `201` PaymentResource |
| GET    | /api/v1/orders/{orderNumber}/payment-status | Order + latest attempt status (cheap DB read, safe to poll) |
| POST   | /api/v1/orders/{orderNumber}/payments/{payment}/refresh | Re-check a pending attempt against the gateway (check-transaction-2) |
| POST   | /api/v1/payments/payway/webhook | ABA PayWay callback (HMAC-SHA512 header, no auth) |
| GET    | /api/v1/payments/payway/checkout/{payment} | Hosted card checkout page (signed URL) |

**Business rules (business-rules.md §Payment):** the amount/currency ALWAYS come from the
stored `orders.total`; React never sends them. `aba_pay`, `khqr` and `card` map to the PayWay
`abapay_khqr_deeplink` / `cards` options. An order can have many attempts but only ONE success
settles it (siblings are cancelled). A payment is `paid` only after the gateway confirms it
(signature-verified webhook `status=0`, or check-transaction `APPROVED`) and the reported
amount/currency match the stored order total; this flips the order to `payment_status=paid` /
`status=confirmed`. Failed/expired/cancelled attempts never alter the order. Webhook is
idempotent (duplicate successes are no-ops). Client-originated statuses are never trusted.

HTTP statuses used here: `201` created, `401` unauthenticated, `404` unknown order/foreign
payment/tran id, `409` already paid or cancelled order, `422` invalid/disabled method or
amount/currency mismatch, `403` invalid signed checkout URL, `400` invalid webhook signature,
`502` gateway unavailable (attempt stays `pending` for retry).

**Payment resource shape:**

```jsonc
{
  "id": 1,
  "payment_number": "PAY-20250830-000002",
  "order_number": "ORD-20250830-000002",
  "payment_method": "aba_pay",
  "payment_method_label": "ABA Pay",
  "gateway": "payway",
  "payment_status": "pending",      // pending | paid | failed | expired | cancelled | refunded
  "amount": "9.50",
  "currency": "USD",
  "qr_string": "...",               // only while pending (aba_pay / khqr)
  "deeplink": "https://...",        // only while pending (aba_pay)
  "checkout_url": "...",            // signed, only while pending (card); HTML stays server-side
  "expires_at": "...",
  "paid_at": null,
  "created_at": "...",
  "gateway_reference": null,
  "gateway_transaction_id": "..."
}
```

**Payment status resource shape (`GET payment-status`):**

```jsonc
{
  "order_number": "ORD-...",
  "order_status": "pending",
  "order_payment_status": "unpaid",
  "payment": { /* PaymentResource or null when no attempt exists */ }
}
```

---

## 4. Admin Endpoints

Require `auth:sanctum` with role `admin`.

| Method | Endpoint                          | Description                    |
|--------|-----------------------------------|--------------------------------|
| GET    | /api/v1/admin/dashboard           | Dashboard KPIs & charts        |
| GET    | /api/v1/admin/me                  | Current admin profile (implemented) |
| POST   | /api/v1/admin/auth/login          | Admin login (design; Phase 3 uses POST /auth/login + role gate) |
| GET    | /api/v1/admin/products            | List all products (incl. inactive) |
| POST   | /api/v1/admin/products            | Create product                 |
| GET    | /api/v1/admin/products/{id}       | Product detail                 |
| PUT    | /api/v1/admin/products/{id}       | Update product                 |
| DELETE | /api/v1/admin/products/{id}       | Soft-delete product            |
| POST   | /api/v1/admin/products/{id}/images| Upload product image           |
| DELETE | /api/v1/admin/products/{id}/images/{imageId} | Remove image        |
| GET    | /api/v1/admin/categories          | List all categories            |
| POST   | /api/v1/admin/categories          | Create category                |
| PUT    | /api/v1/admin/categories/{id}     | Update category                |
| DELETE | /api/v1/admin/categories/{id}     | Soft-delete category           |
| GET    | /api/v1/admin/users               | List customers                   |
| GET    | /api/v1/admin/users/{id}          | Customer detail                  |
| PUT    | /api/v1/admin/users/{id}/status   | Update account status            |
| GET    | /api/v1/admin/orders              | List all orders (search/filter/sort/paginate; admin + staff) (implemented) |
| GET    | /api/v1/admin/orders/{id}         | Order detail with items/payments/history/notes (admin + staff) (implemented) |
| GET    | /api/v1/admin/orders/statistics   | Order & payment KPIs for the dashboard (admin + staff) (implemented) |
| PATCH  | /api/v1/admin/orders/{id}/status  | Advance order status (validated transition; admin + staff) (implemented) |
| POST   | /api/v1/admin/orders/{id}/cancel  | Cancel order: restore stock, retire pending payments, never auto-refund (admin only) (implemented) |
| POST   | /api/v1/admin/orders/{id}/notes   | Add an internal admin note (admin only) (implemented) |
| GET    | /api/v1/admin/payments            | List payments                    |
| PUT    | /api/v1/admin/payments/{id}/status| Update payment status            |
| GET    | /api/v1/admin/inventory           | Inventory overview / stock list (implemented) |
| POST   | /api/v1/admin/inventory/adjust    | Manual stock adjustment, ledger-kept (implemented) |
| GET    | /api/v1/admin/inventory/transactions | Inventory audit ledger, admin only (implemented) |
| GET    | /api/v1/admin/reviews             | List all reviews                 |
| PUT    | /api/v1/admin/reviews/{id}/status | Approve/reject review            |
| GET    | /api/v1/admin/coupons             | List coupons                     |
| POST   | /api/v1/admin/coupons             | Create coupon                    |
| PUT    | /api/v1/admin/coupons/{id}        | Update coupon                    |
| DELETE | /api/v1/admin/coupons/{id}        | Soft-delete coupon               |
| GET    | /api/v1/admin/staff               | List staff accounts              |
| POST   | /api/v1/admin/staff               | Create staff account             |
| PUT    | /api/v1/admin/staff/{id}          | Update staff role/status         |
| DELETE | /api/v1/admin/staff/{id}          | Deactivate staff                 |
| GET    | /api/v1/admin/reports/sales       | Sales report                     |
| GET    | /api/v1/admin/reports/inventory   | Inventory report                 |
| GET    | /api/v1/admin/reports/customers   | Customer report                  |
| GET    | /api/v1/admin/settings            | List settings                    |
| PUT    | /api/v1/admin/settings            | Update settings                  |

### 4.1 Inventory — Phase 8 (implemented)

Stock management is split between `admin` and `staff`:

| Role  | Endpoint                              | Capability                          |
|-------|---------------------------------------|-------------------------------------|
| Admin + Staff | GET /api/v1/admin/inventory    | Stock overview (all products, filters) |
| Admin + Staff | POST /api/v1/admin/inventory/adjust | Manual adjustment (ledger-kept)  |
| Staff | GET /api/v1/staff/inventory / POST /api/v1/staff/inventory/adjust | Same via the staff prefix |
| Admin only | GET /api/v1/admin/inventory/transactions | Full audit trail           |

All inventory routes require `auth:sanctum` + the `role.admin_or_staff` (admin/staff) or
`role.admin` (ledger) middleware; customers → `403`, guests → `401`.

**Adjust request body:**

```jsonc
{
  "product_id": 12,          // required, exists:products,id
  "quantity_change": -3,     // required, integer, never 0
  "type": "adjustment",      // optional: adjustment | purchase (purchase must be positive)
  "reason": "Damage write-off" // optional, max 255
}
```

HTTP statuses: `200` adjusted, `401` unauthenticated, `403` forbidden role, `404` unknown
product, `422` validation failure (e.g. change would make stock negative).

**Overview item (`GET …/inventory` per item, extends AdminProductResource):**

```jsonc
{
  "id": 12, "name": "Organic Apples", "sku": "ORG-0001",
  "stock_quantity": 4, "low_stock_threshold": 5, "reorder_level": 5,
  "is_low_stock": true, "available": false, "status": "active",
  "stock_status": "low_stock",            // in_stock | low_stock | out_of_stock
  "last_inventory_transaction_at": "2026-08-30T…"
}
```

Filters: `search` (name, SKU or numeric product ID), `category_id`, `category_slug`,
`status`, `stock` (`in_stock | low_stock | out_of_stock`), legacy `low_stock` (`true` →
`stock_quantity <= low_stock_threshold`). Sort: `recently_updated` (default) |
`oldest_updated` | `stock_high` | `stock_low` | `name_asc` | `name_desc`. Paginated with
`pagination` metadata (default 20, max 50).

**Ledger item (`GET …/inventory/transactions` per item):**

```jsonc
{
  "id": 901,
  "product": { "id": 12, "name": "Organic Apples", "sku": "ORG-0001" },
  "actor": { "id": 2, "name": "Store Admin", "email": "admin@organicstore.test" },
  "type": "sale",                      // initial | purchase | sale | adjustment | return
  "quantity_change": -3,
  "stock_before": 7,
  "stock_after": 4,
  "reference_type": "Order",
  "reference_id": 55,
  "reference_number": "ORD-20260830-000055",
  "notes": "Order ORD-20260830-000055",
  "created_at": "2026-08-30T…"
}
```

Filters: `product_id`, `type`, `search` (product name/sku), `date_from`, `date_to`. Newest
first, paginated.

### 4.1b Inventory Management — Phase 10 (implemented)

Per-product stock management. All routes are guarded by `auth:sanctum` + middleware, with the
authoritative check in `InventoryPolicy` (registered for `Product` and
`InventoryTransaction`). Rules: stock can never go negative; every movement writes a
ledger row (`stock_before`/`stock_after`); generated values are never accepted from the
client — only the actor, reason and quantity/target are.

| Method | Endpoint | Role | Description |
|--------|----------|------|-------------|
| GET    | /api/v1/admin/inventory/statistics | Admin + Staff | Aggregate counts by stock status + total units |
| GET    | /api/v1/admin/inventory/{product} | Admin + Staff | Single-product inventory detail (withTrashed) |
| POST   | /api/v1/admin/inventory/{product}/add | Admin + Staff | Add inbound stock (`purchase`), reason optional |
| POST   | /api/v1/admin/inventory/{product}/remove | Admin + Staff | Remove stock (`adjustment`), reason required |
| POST   | /api/v1/admin/inventory/{product}/adjust | Admin + Staff | Set stock to an absolute target (delta recorded) |
| PATCH  | /api/v1/admin/inventory/{product}/reorder-level | Admin + Staff | Update low-stock / reorder threshold |
| GET    | /api/v1/admin/inventory/{product}/transactions | Admin only | Per-product movement ledger |

`{product}` is a numeric ID (`whereNumber`), so the literal `inventory/transactions` and
`inventory/statistics` routes can never be shadowed.

**Statistics response (`GET …/inventory/statistics`):**

```jsonc
{ "total_products": 42, "in_stock": 30, "low_stock": 8, "out_of_stock": 4, "total_units": 512 }
```

**Add / remove request bodies:**

```jsonc
// add   — POST  /admin/inventory/{product}/add
{ "quantity": 20, "reason": "Received from supplier." }   // reason optional, max 255
// remove — POST /admin/inventory/{product}/remove
{ "quantity": 3, "reason": "damage" }                     // reason required
// adjust — POST /admin/inventory/{product}/adjust  (absolute target, never a delta)
{ "quantity": 15, "reason": "Physical inventory count." } // reason required
// reorder — PATCH /admin/inventory/{product}/reorder-level
{ "reorder_level": 5 }
```

**Stock-mutation response (all three endpoints):**

```jsonc
{
  "product": { "…": "refreshed InventoryItemResource — stock_quantity + stock_status" },
  "transaction": { "…": "new InventoryTransactionResource row" }
}
```

Status codes: `200`, `401` unauthenticated, `403` forbidden role, `404` unknown product,
`422` validation failure (`quantity` invalid, missing `reason`, or removal/adjustment that
would drop stock below zero — such a request writes no ledger row).

---

## 5. Staff Endpoints

Require `auth:sanctum` with role `staff`. Limited scope — **no** product/category/coupon/management beyond inventory and order status.

| Method | Endpoint                          | Description                    |
|--------|-----------------------------------|--------------------------------|
| GET    | /api/v1/staff/me                  | Current staff profile (implemented) |
| GET    | /api/v1/staff/auth/login          | Staff login (design; Phase 3 uses POST /auth/login + role gate) |
| GET    | /api/v1/admin/products            | View products (read-only)      |
| GET    | /api/v1/staff/inventory           | View inventory (implemented)     |
| POST   | /api/v1/staff/inventory/adjust    | Adjust stock, logged (implemented) |
| GET    | /api/v1/staff/orders              | View orders                    |
| PUT    | /api/v1/staff/orders/{id}/status  | Update order status            |
| GET    | /api/v1/admin/users               | View customers                  |

Staff **cannot**: create/edit products or categories, manage coupons, manage payments, manage staff, manage settings, view reports, or view the full transaction ledger.

---

## 6. Route Grouping Strategy

In `routes/api.php`:

```
Route::prefix('v1')->group(function () {

    // Public
    Route::post('auth/register', ...);
    Route::post('auth/login', ...);
    Route::get('products', ...);
    Route::get('categories', ...);

    // Authenticated customer
    Route::middleware('auth:sanctum')->group(function () { ... });

    // Admin
    Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () { ... });

    // Staff (admin routes read + staff write)
    Route::middleware(['auth:sanctum', 'role:admin,staff'])->prefix('staff') ...
});
```

**Middleware / Policies intent:**
- `auth:sanctum` — authentication.
- `role:{roles}` — coarse role gate. Phase 3 registers the aliases `role.admin`, `role.staff`, `role.admin_or_staff`, plus `active` (blocks inactive/banned accounts).
- **Policies** — object-level authorization (e.g., a customer owning a cart/order/review, or admin-only mutations).

---

## 7. Request → Service → Response Flow

For complex flows (checkout, payment, inventory, coupon):

```
POST /api/v1/checkout
  └─ CheckoutRequest::authorize() + rules()   (validate input: address_id)
       └─ OrderService::placeOrder()          (business logic, DB transactions)
            ├─ load cart & validate availability/stock
            ├─ validate address ownership
            ├─ compute subtotal, shipping fee, tax, total (server-side)
            ├─ create order + order_items (product name/price snapshots)
            ├─ store shipping_address_snapshot
            └─ clear the cart
       └─ OrderResource                                (serialize response)
```

The controller stays thin: validate → delegate to service → return a Resource with the standard envelope.

---

## 8. Validation, Rate Limiting & Security

- **Form Requests** on every write route (fields, types, ranges, uniqueness scoped to user).
- **Rate limiting** (`throttle`) on `auth/*` and public write endpoints to mitigate brute force/abuse.
- **Policies** for object-level authorization.
- Prices/totals always recalculated on the server; client-sent prices are ignored.
- Coupons always validated on the backend against active window, limits, and minimums.
