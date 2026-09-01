# Organic Store E-Commerce System — Database Design

> Phase 1 — Technical Foundation. Version 1.0.0

This document defines the complete MySQL schema design for all 18 tables, including column types, keys, constraints, indexes, and defaults. This is a **design document only** — no migrations have been created yet (Phase 2).

---

## 1. Conventions & Global Rules

- **Primary keys:** auto-increment `BIGINT UNSIGNED` named `id` on every table.
- **Foreign keys:** `BIGINT UNSIGNED`, nullable where noted, with an index; names use `<singular>_id`.
- **Timestamps:** all tables include `created_at` and `updated_at` (Laravel `timestamps()`). Order-related flows also use explicit state columns.
- **Currency:** stored as `DECIMAL(10,2)`. Product price and order monetary values never use floats.
- **Snapshots:** `order_items` stores immutable product name + price snapshots so historical orders stay valid.
- **Soft Deletes:** applied where historical retainment matters — specifically `products`, `categories`, `users`, `coupons`. Not tracked in every table to keep design clean; flagged per-table.
- **Enum vs string:** MySQL `ENUM` used for small fixed sets (role, status). Strings used where values may grow.
- **Decimal precision note for stock:** stock/quantity integers.

---

## 2. Table: `users`

Stores customers, staff, and admins.

| Column        | Data type        | PK | FK              | Nullable | Default | Unique | Index | Description |
|---------------|------------------|----|-----------------|----------|---------|--------|-------|-------------|
| id            | BIGINT UNSIGNED  | ✔  | —               | No       | auto    | ✔      | ✔     | Primary key |
| name          | VARCHAR(255)     | —  | —               | No       | —       | —      | —     | Full name |
| email         | VARCHAR(255)     | —  | —               | No       | —       | ✔      | ✔     | Login email |
| password      | VARCHAR(255)     | —  | —               | No       | —       | —      | —     | Bcrypt/Argon2id hash |
| role          | ENUM('customer','staff','admin') | — | —   | No       | 'customer' | —  | ✔    | User role |
| status        | ENUM('active','inactive','banned') | — | —  | No       | 'active' | —    | ✔    | Account status |
| phone         | VARCHAR(20)      | —  | —               | Yes      | NULL    | —      | —     | Contact number |
| avatar        | VARCHAR(255)     | —  | —               | Yes      | NULL    | —      | —     | Avatar path |
| email_verified_at | TIMESTAMP   | —  | —               | Yes      | NULL    | —      | —     | Email verification time |
| remember_token  | VARCHAR(100)   | —  | —               | Yes      | NULL    | —      | —     | Auth remember token |
| created_at    | TIMESTAMP        | —  | —               | No       | now     | —      | ✔     | Created |
| updated_at    | TIMESTAMP        | —  | —               | No       | now     | —      | —     | Updated |
| deleted_at    | TIMESTAMP        | —  | —               | Yes      | NULL    | —      | —     | Soft delete (staff/admin retention) |

**Notes:** `email` unique. Role enforced by Laravel middleware/policies. Soft delete used to retain audit history for staff/admin; customers are soft-deleted so their orders remain valid.

---

## 3. Table: `categories`

Product categories (self-referencing, supporting a tree).

| Column         | Data type       | PK | FK          | Nullable | Default | Unique | Index | Description |
|----------------|-----------------|----|-------------|----------|---------|--------|-------|-------------|
| id             | BIGINT UNSIGNED | ✔  | —           | No       | auto    | ✔      | ✔     | Primary key |
| parent_id      | BIGINT UNSIGNED | —  | categories.id | Yes    | NULL    | —      | ✔     | Parent category (self FK, cascade null) |
| name           | VARCHAR(255)    | —  | —           | No       | —       | —      | ✔     | Category name |
| slug           | VARCHAR(255)    | —  | —           | No       | —       | ✔      | ✔     | URL slug |
| description    | TEXT            | —  | —           | Yes      | NULL    | —      | —     | Description |
| icon           | VARCHAR(255)    | —  | —           | Yes      | NULL    | —      | —     | Icon/image path |
| status         | ENUM('active','inactive') | — | —     | No       | 'active' | —     | ✔    | Visibility |
| sort_order     | INT UNSIGNED    | —  | —           | No       | 0       | —      | —     | Display order |
| created_at     | TIMESTAMP       | —  | —           | No       | now     | —      | ✔     | Created |
| updated_at     | TIMESTAMP       | —  | —           | No       | now     | —      | —     | Updated |
| deleted_at     | TIMESTAMP       | —  | —           | Yes      | NULL    | —      | —     | Soft delete |

**Notes:** `slug` unique. `parent_id` self-FK; when a parent is deleted, children are set to top-level (`ON DELETE SET NULL`).

---

## 4. Table: `products`

Core product catalog. Prices are stored; stock tracked for inventory.

| Column          | Data type       | PK | FK            | Nullable | Default   | Unique | Index | Description |
|-----------------|-----------------|----|---------------|----------|-----------|--------|-------|-------------|
| id              | BIGINT UNSIGNED | ✔  | —             | No       | auto      | ✔      | ✔     | Primary key |
| category_id     | BIGINT UNSIGNED | —  | categories.id | No       | —         | —      | ✔     | Category |
| name            | VARCHAR(255)    | —  | —             | No       | —         | —      | ✔     | Product name |
| slug            | VARCHAR(255)    | —  | —             | No       | —         | ✔      | ✔     | URL slug |
| description     | TEXT            | —  | —             | Yes      | NULL      | —      | —     | Long description |
| short_description | VARCHAR(500) | —  | —             | Yes      | NULL      | —      | —     | Short teaser |
| sku             | VARCHAR(100)    | —  | —             | No       | —         | ✔      | ✔     | SKU code |
| barcode         | VARCHAR(100)    | —  | —             | Yes      | NULL      | —      | —     | Optional barcode |
| price           | DECIMAL(10,2)   | —  | —             | No       | 0.00      | —      | ✔     | Selling price |
| compare_at_price | DECIMAL(10,2) | —  | —             | Yes      | NULL      | —      | —     | Old/discount reference |
| cost_price      | DECIMAL(10,2)   | —  | —             | Yes      | NULL      | —      | —     | Purchase cost (admin) |
| stock_quantity  | INT UNSIGNED    | —  | —             | No       | 0         | —      | ✔     | Available stock |
| low_stock_threshold | INT UNSIGNED | — | —            | No       | 5         | —      | —     | Alert threshold |
| is_featured     | BOOLEAN         | —  | —             | No       | false     | —      | ✔     | Featured flag |
| status          | ENUM('active','inactive','draft') | — | — | No       | 'active'  | —      | ✔    | Lifecycle status |
| unit            | VARCHAR(50)     | —  | —             | Yes      | NULL      | —      | —     | e.g. kg, pcs, bunch |
| weight          | DECIMAL(10,3)   | —  | —             | Yes      | NULL      | —      | —     | Weight (kg) |
| min_order_qty   | INT UNSIGNED    | —  | —             | No       | 1         | —      | —     | Minimum purchase |
| created_at      | TIMESTAMP       | —  | —             | No       | now       | —      | ✔     | Created |
| updated_at      | TIMESTAMP       | —  | —             | No       | now       | —      | —     | Updated |
| deleted_at      | TIMESTAMP       | —  | —             | Yes      | NULL      | —      | —     | Soft delete |

**Notes:** `slug` and `sku` unique. `status` controls availability (inactive products cannot be added to cart). Products are soft-deleted to preserve historical orders.

---

## 5. Table: `product_images`

Gallery images for products.

| Column        | Data type       | PK | FK            | Nullable | Default | Unique | Index | Description |
|---------------|-----------------|----|---------------|----------|---------|--------|-------|-------------|
| id            | BIGINT UNSIGNED | ✔  | —             | No       | auto    | ✔      | ✔     | Primary key |
| product_id    | BIGINT UNSIGNED | —  | products.id   | No       | —       | —      | ✔     | Product |
| image         | VARCHAR(255)    | —  | —             | No       | —       | —      | —     | Image file path |
| alt_text      | VARCHAR(255)    | —  | —             | Yes      | NULL    | —      | —     | Accessible alt text |
| sort_order    | INT UNSIGNED    | —  | —             | No       | 0       | —      | —     | Display order |
| is_primary    | BOOLEAN         | —  | —             | No       | false   | —      | ✔     | Primary thumbnail |
| created_at    | TIMESTAMP       | —  | —             | No       | now     | —      | —     | Created |
| updated_at    | TIMESTAMP       | —  | —             | No       | now     | —      | —     | Updated |

**Notes:** `ON DELETE CASCADE` for `product_id` (images have no historical value beyond the product).

---

## 6. Table: `addresses`

Customer shipping/billing addresses.

| Column         | Data type       | PK | FK         | Nullable | Default | Unique | Index | Description |
|----------------|-----------------|----|-----------|----------|---------|--------|-------|-------------|
| id             | BIGINT UNSIGNED | ✔  | —          | No       | auto    | ✔      | ✔     | Primary key |
| user_id        | BIGINT UNSIGNED | —  | users.id   | No       | —       | —      | ✔     | Owner |
| label          | VARCHAR(50)     | —  | —          | Yes      | NULL    | —      | —     | e.g. Home, Work |
| recipient_name | VARCHAR(255)    | —  | —          | No       | —       | —      | —     | Recipient |
| recipient_phone| VARCHAR(20)     | —  | —          | No       | —       | —      | —     | Recipient phone |
| address_line1  | VARCHAR(255)    | —  | —          | No       | —       | —      | —     | Street address |
| address_line2  | VARCHAR(255)    | —  | —          | Yes      | NULL    | —      | —     | Apt/suite |
| city           | VARCHAR(100)    | —  | —          | No       | —       | —      | ✔     | City |
| state          | VARCHAR(100)    | —  | —          | No       | —       | —      | —     | State/province |
| postal_code    | VARCHAR(20)     | —  | —          | Yes      | NULL    | —      | —     | ZIP/Postal |
| country        | VARCHAR(100)    | —  | —          | No       | —       | —      | —     | Country |
| is_default     | BOOLEAN         | —  | —          | No       | false   | —      | ✔     | Default address |
| created_at     | TIMESTAMP       | —  | —          | No       | now     | —      | —     | Created |
| updated_at     | TIMESTAMP       | —  | —          | No       | now     | —      | —     | Updated |

**Notes:** `ON DELETE CASCADE` for `user_id`. Only one `is_default = true` per user is enforced by Laravel.

---

## 7. Table: `carts`

A cart belongs to a user (guest carts are out of scope; carts are user-bound).

| Column     | Data type       | PK | FK        | Nullable | Default | Unique | Index | Description |
|------------|-----------------|----|-----------|----------|---------|--------|-------|-------------|
| id         | BIGINT UNSIGNED | ✔  | —         | No       | auto    | ✔      | ✔     | Primary key |
| user_id    | BIGINT UNSIGNED | —  | users.id  | No       | —       | ✔      | ✔     | Owner (one active cart per user) |
| session_id | VARCHAR(100)    | —  | —         | Yes      | NULL    | —      | —     | Optional guest/session link |
| status     | ENUM('active','converted','abandoned') | — | — | No  | 'active' | —    | ✔    | Cart lifecycle |
| created_at | TIMESTAMP       | —  | —         | No       | now     | —      | —     | Created |
| updated_at | TIMESTAMP       | —  | —         | No       | now     | —      | —     | Updated |

**Notes:** `user_id` is unique (one active cart per user is the simplest contract). Cart is marked `converted` when an order is placed.

---

## 8. Table: `cart_items`

Line items inside a cart.

| Column       | Data type       | PK | FK         | Nullable | Default | Unique | Index | Description |
|--------------|-----------------|----|-----------|----------|---------|--------|-------|-------------|
| id           | BIGINT UNSIGNED | ✔  | —          | No       | auto    | ✔      | ✔     | Primary key |
| cart_id      | BIGINT UNSIGNED | —  | carts.id   | No       | —       | —      | ✔     | Cart |
| product_id   | BIGINT UNSIGNED | —  | products.id| No       | —       | —      | ✔     | Product |
| quantity     | INT UNSIGNED    | —  | —          | No       | 1       | —      | —     | Quantity (≥ 1) |
| created_at   | TIMESTAMP       | —  | —          | No       | now     | —      | —     | Created |
| updated_at   | TIMESTAMP       | —  | —          | No       | now     | —      | —     | Updated |

**Notes:** Unique composite index `(cart_id, product_id)` prevents duplicate product lines (updated instead). Price is **not** stored here — it is fetched from the product at add-time and be re-validated at checkout by Laravel.

---

## 9. Table: `wishlists`

A wishlist belongs to a user (one per user).

| Column     | Data type       | PK | FK        | Nullable | Default | Unique | Index | Description |
|------------|-----------------|----|-----------|----------|---------|--------|-------|-------------|
| id         | BIGINT UNSIGNED | ✔  | —         | No       | auto    | ✔      | ✔     | Primary key |
| user_id    | BIGINT UNSIGNED | —  | users.id  | No       | —       | ✔      | ✔     | Owner (one wishlist per user) |
| created_at | TIMESTAMP       | —  | —         | No       | now     | —      | —     | Created |
| updated_at | TIMESTAMP       | —  | —         | No       | now     | —      | —     | Updated |

---

## 10. Table: `wishlist_items`

Products saved in a wishlist.

| Column        | Data type       | PK | FK         | Nullable | Default | Unique | Index | Description |
|---------------|-----------------|----|-----------|----------|---------|--------|-------|-------------|
| id            | BIGINT UNSIGNED | ✔  | —          | No       | auto    | ✔      | ✔     | Primary key |
| wishlist_id   | BIGINT UNSIGNED | —  | wishlists.id | No     | —       | —      | ✔     | Wishlist |
| product_id    | BIGINT UNSIGNED | —  | products.id | No      | —       | —      | ✔     | Product |
| created_at    | TIMESTAMP       | —  | —          | No       | now     | —      | —     | Created |
| updated_at    | TIMESTAMP       | —  | —          | No       | now     | —      | —     | Updated |

**Notes:** Unique composite index `(wishlist_id, product_id)` prevents duplicates.

---

## 11. Table: `orders`

The authoritative order record. All monetary totals are calculated and stored by Laravel.

| Column           | Data type      | PK | FK            | Nullable | Default | Unique | Index | Description |
|------------------|----------------|----|---------------|----------|---------|--------|-------|-------------|
| id               | BIGINT UNSIGNED| ✔  | —             | No       | auto    | ✔      | ✔     | Primary key |
| order_number     | VARCHAR(50)    | —  | —             | No       | —       | ✔      | ✔     | Human-readable order ref |
| user_id          | BIGINT UNSIGNED| —  | users.id      | No       | —       | —      | ✔     | Customer |
| address_id       | BIGINT UNSIGNED| —  | addresses.id  | No       | —       | —      | ✔     | Shipping address |
| coupon_id        | BIGINT UNSIGNED| —  | coupons.id    | Yes      | NULL    | —      | ✔     | Applied coupon |
| subtotal         | DECIMAL(10,2)  | —  | —             | No       | 0.00    | —      | —     | Sum of item line totals |
| discount         | DECIMAL(10,2)  | —  | —             | No       | 0.00    | —      | —     | Coupon discount applied |
| shipping_fee     | DECIMAL(10,2)  | —  | —             | No       | 0.00    | —      | —     | Shipping charge |
| tax              | DECIMAL(10,2)  | —  | —             | No       | 0.00    | —      | —     | Tax amount |
| total            | DECIMAL(10,2)  | —  | —             | No       | 0.00    | —      | ✔     | Grand total |
| status           | ENUM('pending','processing','shipped','delivered','cancelled','refunded') | — | — | No | 'pending' | —  | ✔   | Order lifecycle |
| payment_status   | ENUM('unpaid','paid','refunded','failed') | — | —   | No       | 'unpaid' | —    | ✔   | Payment state |
| shipping_address_snapshot | TEXT | —  | —             | Yes      | NULL    | —      | —     | Address snapshot at order time |
| notes            | TEXT           | —  | —             | Yes      | NULL    | —      | —     | Customer/order notes |
| placed_at        | TIMESTAMP      | —  | —             | No       | now     | —      | ✔     | Placement time |
| created_at       | TIMESTAMP      | —  | —             | No       | now     | —      | —     | Created |
| updated_at       | TIMESTAMP      | —  | —             | No       | now     | —      | —     | Updated |

**Notes:** `order_number` unique. `total = subtotal + shipping_fee + tax − discount`, always computed server-side. `shipping_address_snapshot` preserves the address even if the customer edits it later (historical integrity). No soft delete — orders are immutable historical records.

---

## 12. Table: `order_items`

Immutable line items with product snapshots so historical orders remain valid.

| Column        | Data type       | PK | FK          | Nullable | Default | Unique | Index | Description |
|---------------|-----------------|----|-------------|----------|---------|--------|-------|-------------|
| id            | BIGINT UNSIGNED | ✔  | —           | No       | auto    | ✔      | ✔     | Primary key |
| order_id      | BIGINT UNSIGNED | —  | orders.id   | No       | —       | —      | ✔     | Order |
| product_id    | BIGINT UNSIGNED | —  | products.id | Yes      | NULL    | —      | ✔     | Product (nullable if later removed) |
| product_name  | VARCHAR(255)    | —  | —           | No       | —       | —      | —     | **Snapshot** of product name |
| product_sku   | VARCHAR(100)    | —  | —           | No       | —       | —      | —     | **Snapshot** of SKU |
| unit_price    | DECIMAL(10,2)   | —  | —           | No       | 0.00    | —      | —     | **Snapshot** unit price at order time |
| quantity      | INT UNSIGNED    | —  | —           | No       | 1       | —      | —     | Quantity |
| line_total    | DECIMAL(10,2)   | —  | —           | No       | 0.00    | —      | —     | `unit_price × quantity` (server) |
| created_at    | TIMESTAMP       | —  | —           | No       | now     | —      | —     | Created |
| updated_at    | TIMESTAMP       | —  | —           | No       | now     | —      | —     | Updated |

**Notes:** `product_id` nullable + `ON DELETE SET NULL` so a product can be deleted without breaking order history; snapshots preserve name/SKU/price. Optional unique on `(order_id, product_id)` — but relax to allow same product with distinct config if needed; for this system keep one line per product.

---

## 13. Table: `payments`

Payment records for orders.

| Column          | Data type       | PK | FK          | Nullable | Default    | Unique | Index | Description |
|-----------------|-----------------|----|-------------|----------|-----------|--------|-------|-------------|
| id              | BIGINT UNSIGNED | ✔  | —           | No       | auto       | ✔      | ✔     | Primary key |
| order_id        | BIGINT UNSIGNED | —  | orders.id   | No       | —         | —      | ✔     | Order |
| payment_method  | ENUM('cod','card','bank_transfer','online') | — | — | No | 'cod'  | —      | —   | Method |
| transaction_id  | VARCHAR(255)    | —  | —           | Yes      | NULL       | —      | ✔     | Gateway txn reference |
| amount          | DECIMAL(10,2)   | —  | —           | No       | 0.00      | —      | —     | Amount paid |
| status          | ENUM('pending','paid','failed','refunded') | — | — | No  | 'pending' | —    | ✔    | Payment status |
| paid_at         | TIMESTAMP       | —  | —           | Yes      | NULL      | —      | —     | Payment time |
| gateway_response| TEXT           | —  | —           | Yes      | NULL      | —      | —     | Raw gateway response |
| created_at      | TIMESTAMP       | —  | —           | No       | now       | —      | —     | Created |
| updated_at      | TIMESTAMP       | —  | —           | No       | now       | —      | —     | Updated |

**Notes:** A real payment gateway will send payment credentials via env config — never stored here. `amount` should equal the order `total`. `ON DELETE CASCADE` for `order_id` is acceptable (payment is subordinate to order) — or `RESTRICT`; we use `CASCADE` for cleanup consistency with order lifecycle.

---

## 14. Table: `reviews`

Product reviews written by customers who purchased the product.

| Column        | Data type       | PK | FK          | Nullable | Default | Unique | Index | Description |
|---------------|-----------------|----|-------------|----------|---------|--------|-------|-------------|
| id            | BIGINT UNSIGNED | ✔  | —           | No       | auto    | ✔      | ✔     | Primary key |
| product_id    | BIGINT UNSIGNED | —  | products.id | No       | —       | —      | ✔     | Product |
| user_id       | BIGINT UNSIGNED | —  | users.id    | No       | —       | —      | ✔     | Reviewer |
| order_id      | BIGINT UNSIGNED | —  | orders.id   | No       | —       | —      | ✔     | Verifying order |
| rating        | TINYINT UNSIGNED| —  | —           | No       | —       | —      | ✔     | 1–5 stars (CHECK 1–5) |
| title         | VARCHAR(255)    | —  | —           | Yes      | NULL    | —      | —     | Review title |
| comment       | TEXT            | —  | —           | Yes      | NULL    | —      | —     | Review body |
| status        | ENUM('pending','approved','rejected') | — | —  | No       | 'pending' | —    | ✔   | Moderation status |
| created_at    | TIMESTAMP       | —  | —           | No       | now     | —      | —     | Created |
| updated_at    | TIMESTAMP       | —  | —           | No       | now     | —      | —     | Updated |

**Notes:** Unique `(user_id, product_id)` → one review per product per customer. `order_id` proves purchase (business rule: only purchased products can be reviewed). `rating` constrained 1–5.

---

## 15. Table: `coupons`

Discount coupons applied at checkout.

| Column            | Data type       | PK | FK | Nullable | Default | Unique | Index | Description |
|-------------------|-----------------|----|----|----------|---------|--------|-------|-------------|
| id                | BIGINT UNSIGNED | ✔  | —  | No       | auto    | ✔      | ✔     | Primary key |
| code              | VARCHAR(50)     | —  | —  | No       | —       | ✔      | ✔     | Coupon code |
| type              | ENUM('percentage','fixed') | — | — | No | 'percentage' | — | —   | Discount type |
| value             | DECIMAL(10,2)   | —  | —  | No       | 0.00    | —      | —     | % or fixed amount |
| min_order_amount  | DECIMAL(10,2)   | —  | —  | Yes      | NULL    | —      | —     | Minimum subtotal to use |
| max_discount      | DECIMAL(10,2)   | —  | —  | Yes      | NULL    | —      | —     | Max discount cap |
| usage_limit       | INT UNSIGNED    | —  | —  | Yes      | NULL    | —      | —     | Total allowed uses |
| per_user_limit    | INT UNSIGNED    | —  | —  | Yes      | NULL    | —      | —     | Uses per customer |
| used_count        | INT UNSIGNED    | —  | —  | No       | 0       | —      | —     | Times used (denormalized) |
| starts_at         | TIMESTAMP       | —  | —  | Yes      | NULL    | —      | ✔     | Valid from |
| expires_at        | TIMESTAMP       | —  | —  | Yes      | NULL    | —      | ✔     | Valid until |
| status            | ENUM('active','inactive') | — | — | No  | 'active' | —     | ✔    | Active flag |
| created_at        | TIMESTAMP       | —  | —  | No       | now     | —      | —     | Created |
| updated_at        | TIMESTAMP       | —  | —  | No       | now     | —      | —     | Updated |
| deleted_at        | TIMESTAMP       | —  | —  | Yes      | NULL    | —      | —     | Soft delete |

**Notes:** `code` unique. Validation fully on backend (CouponService). Soft delete to preserve usage history.

---

## 16. Table: `coupon_usages`

Tracks every applied coupon to enforce usage limits.

| Column       | Data type       | PK | FK            | Nullable | Default | Unique | Index | Description |
|--------------|-----------------|----|---------------|----------|---------|--------|-------|-------------|
| id           | BIGINT UNSIGNED | ✔  | —             | No       | auto    | ✔      | ✔     | Primary key |
| coupon_id    | BIGINT UNSIGNED | —  | coupons.id    | No       | —       | —      | ✔     | Coupon |
| user_id      | BIGINT UNSIGNED | —  | users.id      | No       | —       | —      | ✔     | User who used it |
| order_id     | BIGINT UNSIGNED | —  | orders.id     | No       | —       | —      | ✔     | Order where applied |
| discount_applied | DECIMAL(10,2) | — | —             | No       | 0.00    | —      | —     | Exact discount |
| created_at   | TIMESTAMP       | —  | —             | No       | now     | —      | —     | Created |
| updated_at   | TIMESTAMP       | —  | —             | No       | now     | —      | —     | Updated |

**Notes:** Unique `(coupon_id, order_id)` prevents double application on one order. `per_user_limit`/`usage_limit` computed by counting rows in this table.

---

## 17. Table: `inventory_transactions`

Audit ledger for every stock change. Immutable.

| Column          | Data type       | PK | FK            | Nullable | Default | Unique | Index | Description |
|-----------------|-----------------|----|---------------|----------|---------|--------|-------|-------------|
| id              | BIGINT UNSIGNED | ✔  | —             | No       | auto    | ✔      | ✔     | Primary key |
| product_id      | BIGINT UNSIGNED | —  | products.id   | No       | —       | —      | ✔     | Product |
| user_id         | BIGINT UNSIGNED | —  | users.id      | Yes      | NULL    | —      | ✔     | Actor (staff/admin) |
| type            | ENUM('purchase','sale','adjustment','return','initial') | — | — | No | 'adjustment' | — | ✔  | Transaction kind |
| quantity_change | INT (signed)    | —  | —             | No       | 0       | —      | —     | + / − units |
| stock_before    | INT UNSIGNED    | —  | —             | No       | —       | —      | —     | Stock prior |
| stock_after     | INT UNSIGNED    | —  | —             | No       | —       | —      | —     | Stock posterior |
| reference_type  | VARCHAR(50)     | —  | —             | Yes      | NULL    | —      | ✔     | Polymorphic ref (Order, etc.) |
| reference_id    | BIGINT UNSIGNED | —  | —             | Yes      | NULL    | —      | ✔     | Related record id |
| notes           | TEXT            | —  | —             | Yes      | NULL    | —      | —     | Notes |
| created_at      | TIMESTAMP       | —  | —             | No       | now     | —      | —     | Created |
| updated_at      | TIMESTAMP       | —  | —             | No       | now     | —      | —     | Updated |

**Notes:** This is the audit trail — never updated or deleted. `quantity_change` is signed to allow both stock-in and stock-out. `stock_after` must never be negative (enforced by the InventoryService). `reference_type`/`reference_id` link the transaction to the source (e.g., an order).

---

## 18. Table: `notifications`

System notifications for users (orders, stock alerts, admin alerts).

| Column          | Data type       | PK | FK         | Nullable | Default | Unique | Index | Description |
|-----------------|-----------------|----|-----------|----------|---------|--------|-------|-------------|
| id              | BIGINT UNSIGNED | ✔  | —          | No       | auto    | ✔      | ✔     | Primary key |
| user_id         | BIGINT UNSIGNED | —  | users.id   | Yes      | NULL    | —      | ✔     | Recipient (NULL = broadcast) |
| type            | VARCHAR(100)    | —  | —          | No       | —       | —      | —     | Notification class/type |
| title           | VARCHAR(255)    | —  | —          | No       | —       | —      | —     | Title |
| message         | TEXT            | —  | —          | No       | —       | —      | —     | Message body |
| data            | JSON            | —  | —          | Yes      | NULL    | —      | —     | Extra payload |
| read_at         | TIMESTAMP       | —  | —          | Yes      | NULL    | —      | ✔     | Marked read |
| created_at      | TIMESTAMP       | —  | —          | No       | now     | —      | —     | Created |
| updated_at      | TIMESTAMP       | —  | —          | No       | now     | —      | —     | Updated |

**Notes:** `user_id` nullable supports system/broadcast notifications. `ON DELETE CASCADE`.

---

## 19. Table: `settings`

Key/value application configuration.

| Column     | Data type       | PK | FK | Nullable | Default | Unique | Index | Description |
|------------|-----------------|----|----|----------|---------|--------|-------|-------------|
| id         | BIGINT UNSIGNED | ✔  | —  | No       | auto    | ✔      | ✔     | Primary key |
| key        | VARCHAR(100)    | —  | —  | No       | —       | ✔      | ✔     | Setting key |
| value      | TEXT            | —  | —  | Yes      | NULL    | —      | —     | Setting value |
| group      | VARCHAR(100)    | —  | —  | Yes      | NULL    | —      | ✔     | Grouping (shipping, store, etc.) |
| is_public  | BOOLEAN         | —  | —  | No       | false   | —      | —     | Exposed publicly or admin-only |
| created_at | TIMESTAMP       | —  | —  | No       | now     | —      | —     | Created |
| updated_at | TIMESTAMP       | —  | —  | No       | now     | —      | —     | Updated |

**Notes:** `key` unique. Stores admin-configurable values (store name, shipping fees, tax rates, low-stock thresholds, etc.) without code changes.

---

## 20. Schema Summary

| #  | Table                 | Purpose                        | Soft Deletes |
|----|-----------------------|--------------------------------|:------------:|
| 1  | users                 | Customers, staff, admins       | yes          |
| 2  | categories            | Product categories (tree)      | yes          |
| 3  | products              | Product catalog                | yes          |
| 4  | product_images        | Product gallery                | no           |
| 5  | addresses             | Customer addresses             | no           |
| 6  | carts                 | Customer carts                 | no           |
| 7  | cart_items            | Cart line items                | no           |
| 8  | wishlists             | Customer wishlists             | no           |
| 9  | wishlist_items        | Wishlist products              | no           |
| 10 | orders                | Orders                         | no (immutable) |
| 11 | order_items           | Order line items + snapshots   | no (immutable) |
| 12 | payments              | Payments                       | no           |
| 13 | reviews               | Product reviews                | no           |
| 14 | coupons               | Discount coupons               | yes          |
| 15 | coupon_usages         | Coupon usage ledger            | no           |
| 16 | inventory_transactions| Stock change ledger            | no (immutable) |
| 17 | notifications         | System notifications           | no           |
| 18 | settings              | Key/value config               | no           |
