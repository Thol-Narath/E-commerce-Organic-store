# Organic Store E-Commerce System — System Architecture

> Phase 7 — Checkout, Addresses & Orders. Version 1.0.0

This document defines the complete system architecture, user roles and permissions, and the internal Laravel / React application structures for the Organic Store E-Commerce System.

---

## 1. System Overview

A production-quality, full-stack e-commerce system for an organic food store, split into four cooperating parts:

1. **Customer E-Commerce Website** — React SPA for shopping.
2. **Admin Dashboard** — React SPA for administration and reporting.
3. **Laravel 10 REST API** — the single backend that owns all business logic and data access.
4. **MySQL Database** — persistent storage.

A **strictly separated frontend/backend** architecture is enforced. The React applications **never** access MySQL directly — all data flows through the Laravel REST API.

---

## 2. High-Level Architecture Diagram

```
┌──────────────────────────┐        ┌──────────────────────────┐
│   React Customer Website │        │   React Admin Dashboard  │
│   (public storefront)    │        │   (staff + admin)        │
└────────────┬─────────────┘        └────────────┬─────────────┘
             │  HTTPS / REST (JSON)              │  HTTPS / REST (JSON)
             │  Auth: Bearer Token (Sanctum)     │  Auth: Bearer Token (Sanctum)
             ▼                                  ▼
┌──────────────────────────────────────────────────────────────┐
│                    Laravel 10 REST API                        │
│                                                               │
│   Authentication · Authorization · Validation                  │
│   Business Logic · Orders · Payments · Inventory              │
│   Coupons · Reviews · Reports · Notifications                 │
│                                                               │
│   Controllers (thin) → Services → Repositories/Models         │
└────────────────────────────┬─────────────────────────────────┘
                             │ Eloquent ORM
                             ▼
                   ┌────────────────────┐
                   │    MySQL Database   │
                   └────────────────────┘
```

**Key rules:**
- React may only interact with Laravel via the versioned REST API (`/api/v1`).
- Laravel is the **only** component that touches MySQL.
- Frontend never trusts its own computed prices or totals; Laravel recalculates everything.

---

## 3. Authentication Flow

- **Customer** and **Admin/Staff** authenticate with email/password.
- Passwords are hashed with Laravel's default `bcrypt` (Argon2id available).
- Session/API tokens are issued via **Laravel Sanctum** (Bearer tokens + optional SPA stateful guards).
- The customer website and admin dashboard each have their own token scope, separated by middleware.

---

## 4. User Roles and Permissions

Three roles are supported. Roles are stored on the `users` table (`role` column with an enum). Authorization is enforced **server-side** by middleware and Policies, never by React alone.

### 4.1 Customer

Can:
- Register, login, logout
- View / search / filter products
- View categories and product details
- Manage cart (add, update, remove)
- Manage wishlist
- Manage profile and addresses
- Checkout, place orders, view and track orders
- Review purchased products

### 4.2 Admin

Can do everything Staff can, plus:
- View dashboard / reports
- Manage products, categories, customers, orders, payments, inventory, reviews, coupons
- Manage staff accounts
- Manage settings

### 4.3 Staff

Has **limited** permissions (must not have full admin rights):
- View products
- Manage inventory
- View orders
- Update order status
- View customers

### 4.4 Permission Enforcement Matrix

| Capability                | Customer | Staff | Admin |
|---------------------------|:--------:|:-----:|:-----:|
| View products             | ✔        | ✔     | ✔     |
| Place orders              | ✔        | ✘     | ✘     |
| Manage own cart/wishlist  | ✔        | ✘     | ✘     |
| Manage own profile        | ✔        | ✘     | ✘     |
| Review purchased products | ✔        | ✘     | ✘     |
| View customers            | ✘        | ✔     | ✔     |
| Manage inventory          | ✘        | ✔     | ✔     |
| View orders               | ✘        | ✔     | ✔     |
| Update order status       | ✘        | ✔     | ✔     |
| Manage products           | ✘        | ✘     | ✔     |
| Manage categories         | ✘        | ✘     | ✔     |
| Manage all orders/payments| ✘        | ✘     | ✔     |
| Manage reviews            | ✘        | ✘     | ✔     |
| Manage coupons            | ✘        | ✘     | ✔     |
| Manage staff              | ✘        | ✘     | ✔     |
| Manage settings           | ✘        | ✘     | ✔     |
| View reports              | ✘        | ✘     | ✔     |

**Authorization implementation:**
- `auth:sanctum` — authentication.
- Custom role middleware (`role:admin`, `role:staff`, `role:admin,staff`) — coarse permission.
- **Policies** — fine-grained authorization (e.g., a customer may only modify their own cart, wishlist, orders, and reviews).

---

## 5. Laravel 10 REST API Architecture

Versioned REST API under `/api/v1`, following RESTful conventions with consistent JSON responses.

### 5.1 Consistent API Response Shape

Success:
```json
{
  "success": true,
  "message": "Success",
  "data": {}
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

Validation errors include a machine-readable field map inside `data` when relevant.

### 5.2 Recommended Backend Structure

```
backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/V1/
│   │   ├── Requests/            (Form Request validation)
│   │   ├── Resources/           (API Resources / transformers)
│   │   └── Middleware/          (role, ensure token, etc.)
│   ├── Models/
│   ├── Services/                (Cart, Order, Payment, Inventory, Coupon)
│   ├── Policies/
│   ├── Repositories/            (optional, thin data layer)
│   ├── Enums/                   (role, order status, payment status, etc.)
│   └── Exceptions/
├── config/
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
├── routes/
│   ├── api.php                  (all /api/v1 routes)
│   └── web.php
└── .env                         (secrets only — never committed)
```

### 5.3 Concerns and Responsibilities

| Layer       | Responsibility                                                  |
|-------------|-----------------------------------------------------------------|
| Controller  | HTTP in/out, validation dispatch, status codes, thin orchestration |
| Request     | Field validation rules and authorization gate                   |
| Resource    | JSON serialization / shape                                      |
| Service     | Complex business logic (Cart, Order, Payment, Inventory, Coupon, Review, Report) |
| Policy      | Object-level authorization                                      |
| Model       | Eloquent relationships, scopes, mutators, casts                 |
| Migration   | Schema definition (Phase 2)                                     |
| Seeder      | Initial roles/users/categories/demo data (Phase 2)              |

### 5.4 Services

- **AuthService** — registration, login, logout, token issuance.
- **CartService** *(Phase 6)* — active cart, add/update/remove/clear, stock & availability validation, server-computed totals.
- **WishlistService** *(Phase 6)* — wishlist CRUD, idempotent add, move-to-cart (merges via CartService).
- **AddressService** *(Phase 7)* — address CRUD, ownership scoping (cross-user → 404), default-address promotion, delete guard against historical orders (409).
- **OrderService** *(Phase 7)* — order creation from the cart, server-side totals, `shipping_address_snapshot`, product/price snapshots on order lines, cart clearing after a successful order inside one DB transaction. *(Phase 8)* — reserves stock per line under a row lock at order placement (re-validates quantity, calls `InventoryService::sell`, rolls everything back on failure).
- **PaymentService** *(Phase 8)* — ABA PayWay payment lifecycle: attempt creation (amount/currency always from the stored order total), status data for the customer poll, authoritative confirmation (verified HMAC webhook or check-transaction) that settles the order (`payment_status=paid`, `status=confirmed`) and retires sibling attempts, expiry/failure transitions, and amount/currency reconciliation. The low-level gateway client is **PayWayService** (signed purchase + check-transaction + callback signature verification), and **CheckPendingPaymentsJob** (scheduler, 1 min) expires stale attempts / reconciles `verify_transaction` attempts.
- **InventoryService** *(Phase 8)* — the single stock ledger authority: `changeStock` locks the product row, enforces non-negative stock, updates `stock_quantity`, writes the immutable `inventory_transactions` row (`stock_before`/`stock_after`), and raises a `low_stock` notification to active admins exactly when stock crosses at/below the threshold. Public entry points: `sell` (order placement, `sale`), `adjust` (admin/staff manual, `adjustment`/`purchase`), `returnStock` (refunds, `return`).
- **CouponService** — coupon validation and application.
- **ReviewService** — review validation (purchased-only), moderation.
- **ReportService** — sales, inventory, customer reports.

Controllers stay thin: they parse input, delegate to a service, then return an API Resource.

### 5.5 Resources (implemented)

API responses use `app/Http/Resources` with a shared envelope. Phase 6 added:

- `CartResource` / `CartItemResource` — id, items, subtotal, total_items; line totals computed from the live product (never the client).
- `WishlistResource` / `WishlistItemResource` — id, items, total_items; `available` flag reflects live product state.
- `Concerns/FormatsMoney` — decimal-string money formatting shared by cart resources.

Phase 7 added:

- `AddressResource` — id, label, recipient, address lines, city/state/postal/country, `is_default`.
- `OrderResource` / `OrderItemResource` — server-stored totals, `shipping_address` (snapshot JSON), item name/SKU/price snapshots, live `product` reference when eager-loaded.
- `SettingsController::publicSettings` — `GET /api/v1/settings/public` exposes store + shipping display info (the shipping flat rate mirrors the value `OrderService` actually charges).

Phase 8 added:

- `PaymentResource` — attempt id, `payment_number`/`payment_method`/`gateway`/`payment_status`/`amount`/`currency`; display-only gateway data (`qr_string`, `deeplink`) is exposed **only while pending**; card uses a signed `checkout_url` (the raw hosted HTML never leaves the server).
- `OrderResource.payment` — the latest attempt (eager-resolved because `whenLoaded` never evaluates Closure arguments); a failed/expired attempt does not alter the order, so the receipt shows the real state.
- `InventoryItemResource` — extends `AdminProductResource` with `is_low_stock`, `available` and `last_inventory_transaction_at` for the admin/staff stock overview.
- `InventoryTransactionResource` — one ledger row: product + actor summaries, `type`, signed `quantity_change`, `stock_before`/`stock_after`, and the origin (`reference_type`/`reference_id`/`reference_number` for order-source movements); null-safe when no reference exists.

---

## 6. React Architecture

Two separate React applications sharing the same conventions:

- `frontend/` — Customer Website (Phase 5).
- `admin/` — Admin Dashboard (Phase 9).

### 6.1 Recommended Frontend Structure

```
frontend/
└── src/
    ├── components/      (reusable UI components)
    ├── pages/           (route-level views)
    ├── layouts/         (public, auth, admin layouts)
    ├── admin/           (admin-specific components/pages)
    ├── services/        (Axios API wrappers)
    ├── context/         (Auth, Cart, Wishlist context providers)
    ├── hooks/           (custom hooks)
    ├── routes/          (route definitions + guards)
    ├── utils/           (helpers, formatting)
    └── assets/          (images, styles)
```

### 6.2 Frontend Conventions

- **React 18** with functional components + hooks.
- **Bootstrap 5** + **React-Bootstrap** for responsive UI (Desktop / Tablet / Mobile).
- **Axios** as the single HTTP client, centralized in `services/`.
- **React Router** for navigation, protected routes via route guards.
- **Chart.js** for admin dashboard charts/reports.
- **Context API** for global state (auth token, cart, wishlist).
- Components are reusable and follow DRY.

### 6.3 Frontend → Backend Contract

- All requests hit `/api/v1`.
- Auth via Bearer token stored securely; Axios interceptor attaches the token.
- A shared response interceptor unwraps `{ success, message, data }` and centralises error handling.

---

## 7. Security Architecture

- Passwords hashed (bcrypt/Argon2id).
- Authentication + tokenisation via **Laravel Sanctum**.
- Server-side authorization via middleware + Policies only.
- **Form Request** validation on every write endpoint.
- Rate limiting on auth and public write endpoints.
- Secure file uploads (validated MIME, size, storage paths).
- Eloquent query builder for **SQL injection** protection.
- **XSS** protection: React escapes by default; Laravel escapes output; validated/CSP headers.
- Secrets (DB credentials, payment keys, app keys) live in `.env` — never hardcoded.

---

## 8. Deployment Topology (Phase 12)

- **Node/React** static builds served by a web server / CDN.
- **PHP-FPM + Nginx/Apache** serving Laravel.
- **MySQL** database server.
- Environment-specific `.env` per deployment.
