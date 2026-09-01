# Organic Store E-Commerce System — Entity Relationship Diagram

> Phase 1 — Technical Foundation. Version 1.0.0

This document presents the complete entity-relationship model using:

- A **textual ERD** (entity boxes with keys and relationship lines).
- A **relationship matrix** (cardinality, FK, and delete rules).
- Definition of all relationships and the FK index coverage.

---

## 1. Textual ERD

```
┌─────────────────────────┐
│        users            │
│─────────────────────────│
│ PK id                   │
│ name, email UQ, password│
│ role (ENUM), status     │
│ phone, avatar           │
│ email_verified_at       │
│ remember_token          │
│ created_at, updated_at  │
│ deleted_at (soft)       │
└─────────────┬───────────┘
              │ 1:N  (has many)
   ┌──────┬───┴───┬────────┬──────────┬─────────┐
   ▼      ▼       ▼        ▼          ▼         ▼
┌────────┐┌────────┐┌────────┐┌────────┐┌─────────┐┌──────────────┐
│addresses││carts  ││wishlists││orders  ││reviews  ││notifications │
│─────────││────────││────────││────────││─────────││──────────────│
│PK id   ││PK id   ││PK id   ││PK id   ││PK id    ││PK id         │
│FK user ││FK user ││FK user ││FK user ││FK product││FK user (null)│
│        ││UQ      ││UQ      ││FK addr ││FK user   ││              │
└────────┘└───┬────┘└───┬────┘│FK coupon││FK order  │└──────────────┘
             │         │      └────┬────┘└─────────┘
             ▼         ▼           ▼
          ┌────────┐┌──────────┐┌────────────┐
          │cart_items││wishlist_items││order_items │
          │────────││──────────││────────────│
          │PK id   ││PK id     ││PK id       │
          │FK cart ││FK wishlist││FK order    │
          │FK product││FK product││FK product (null)│
          └────────┘└──────────┘└─────┬──────┘
                                      │
                          ┌───────────┴───────────┐
                          ▼                       ▼
                     ┌────────────┐        ┌────────────┐
                     │  payments  │        │coupon_usages│
                     │────────────│        │────────────│
                     │PK id       │        │PK id       │
                     │FK order    │        │FK coupon   │
                     └────────────┘        │FK user     │
                                          │FK order    │
                                          └────────────┘

┌─────────────────────────┐
│       categories        │
│─────────────────────────│
│ PK id                   │
│ FK parent_id → categories.id (self)  │
│ name, slug UQ, status   │
│ deleted_at (soft)       │
└────────────┬────────────┘
             │ 1:N  (has many children = self)
             │
             ▼
┌─────────────────────────┐
│        products         │
│─────────────────────────│
│ PK id                   │
│ FK category_id          │
│ name, slug UQ, sku UQ   │
│ price, stock_quantity   │
│ status                  │
│ deleted_at (soft)       │
└───────┬────────┬────────┘
        │        │ 1:N
        ▼        ▼
┌────────────┐┌──────────────────┐
│product_images││inventory_transactions│
│────────────││──────────────────│
│PK id       ││PK id             │
│FK product  ││FK product        │
└────────────┘│FK user (null)    │
              │reference_type/id  │
              └──────────────────┘

┌─────────────────────────┐
│        settings         │   (standalone key/value config,
│─────────────────────────│    no FK relations)
│ PK id                   │
│ key UQ                  │
│ value, group            │
│ is_public, timestamps   │
└─────────────────────────┘
```

The diagram covers all 18 tables. `settings` is a standalone table (`key` unique, no foreign keys) used for admin-configurable values (store info, shipping fees, tax, thresholds).

**Abbreviations:** `PK` = primary key, `FK` = foreign key, `UQ` = unique, `UQ` on pair = composite unique.

---

## 2. Relationship Matrix

| # | From         | To                | Cardinality | Foreign Key (column)            | Delete Rule    | Description |
|---|--------------|-------------------|-------------|---------------------------------|----------------|-------------|
| 1 | users        | addresses         | 1 : N        | addresses.user_id               | CASCADE        | A user has many addresses |
| 2 | users        | carts             | 1 : 1(active)| carts.user_id (UQ)              | CASCADE        | A user has one active cart |
| 3 | users        | wishlists         | 1 : 1        | wishlists.user_id (UQ)          | CASCADE        | A user has one wishlist |
| 4 | users        | orders            | 1 : N        | orders.user_id                  | RESTRICT       | A customer places many orders |
| 5 | users        | reviews           | 1 : N        | reviews.user_id                 | RESTRICT       | A user writes reviews |
| 6 | users        | notifications     | 1 : N        | notifications.user_id (null)    | CASCADE        | Notifications targeted to a user |
| 7 | users        | inventory_transactions | 1 : N  | inventory_transactions.user_id (null) | SET NULL | Actor performed stock changes |
| 8 | categories   | categories        | 1 : N (self)| categories.parent_id            | SET NULL      | Parent→child category tree |
| 9 | categories   | products          | 1 : N        | products.category_id            | RESTRICT      | A category holds many products |
| 10| products     | product_images    | 1 : N        | product_images.product_id       | CASCADE       | Images belong to a product |
| 11| products     | inventory_transactions | 1 : N | inventory_transactions.product_id | RESTRICT   | Product has a stock ledger |
| 12| products     | cart_items        | 1 : N        | cart_items.product_id           | RESTRICT      | Products appear in cart lines |
| 13| products     | wishlist_items    | 1 : N        | wishlist_items.product_id       | RESTRICT      | Products saved to wishlists |
| 14| products     | order_items       | 1 : N        | order_items.product_id (null)   | SET NULL      | Product referenced by order line |
| 15| products     | reviews           | 1 : N        | reviews.product_id              | RESTRICT      | Product receives reviews |
| 16| carts        | cart_items        | 1 : N        | cart_items.cart_id              | CASCADE       | Cart has line items (UQ cart+product) |
| 17| wishlists    | wishlist_items    | 1 : N        | wishlist_items.wishlist_id      | CASCADE       | Wishlist has saved items (UQ) |
| 18| addresses    | orders            | 1 : N        | orders.address_id               | RESTRICT      | Order references shipping address |
| 19| orders       | order_items       | 1 : N        | order_items.order_id            | CASCADE       | Order has line items |
| 20| orders       | payments          | 1 : N        | payments.order_id               | CASCADE       | Order has payment(s) |
| 21| orders       | coupon_usages     | 1 : 1        | coupon_usages.order_id (UQ)     | RESTRICT      | One coupon use per order |
| 22| orders       | reviews           | 1 : N        | reviews.order_id                | RESTRICT      | Order verifies a purchase review |
| 23| coupons      | coupon_usages     | 1 : N        | coupon_usages.coupon_id         | RESTRICT      | Coupon has many usage records |
| 24| orders       | coupons           | N : 1        | orders.coupon_id (null)         | SET NULL      | Optional coupon applied to an order |

**Note on convention:** Where a history/audit table must never be destroyed, the delete rule is `RESTRICT` or `SET NULL`. Companion/detail tables (`cart_items`, `wishlist_items`, `product_images`, `order_items`, `payments`) use `CASCADE` because they are subordinate to their parent and carry no independent historical value once the parent is gone. `orders`, `order_items`, `coupon_usages`, and `inventory_transactions` are treated as immutable and are never hard-deleted by the application (no soft-delete column; application avoids deleting them).

---

## 3. Key Relationships Explained (Business-Driven)

### 3.1 users → orders (1:N)
One customer places many orders. `RESTRICT` prevents deleting a user who has historical orders. Customers are instead soft-deleted (or `banned`), preserving the order trail.

### 3.2 orders → order_items (1:N) with snapshots
`order_items.product_id` uses `SET NULL` so deleting a product never destroys order lines. The `product_name`, `product_sku`, and `unit_price` **snapshots** keep the order line accurate even after the product is renamed, re-priced, or deleted. This satisfies the "historical order validity" rule.

### 3.3 orders → payments (1:N)
An order may have multiple payment records (e.g., partial/failed then retry). `RESTRICT`-equivalent is approximated; here `CASCADE` is fine because payments are subordinate to the order lifecycle. The payment `amount` must match order `total`.

### 3.4 carts → cart_items (1:N)
A cart has lines. Composite `UNIQUE (cart_id, product_id)` ensures one line per product; adding again increments quantity (subject to stock cap). Cart is marked `converted` when the order is placed.

### 3.5 products → inventory_transactions (1:N)
Every stock movement creates an immutable audit row recording `stock_before`, `stock_after`, and the signed change. This powers inventory reporting and enforces "no negative stock".

### 3.6 users → reviews, products → reviews, orders → reviews
A review links to the **product**, the **reviewer**, and the **verifying order**. `UNIQUE (user_id, product_id)` = one review per product per customer. The `order_id` presence enforces "only purchased products can be reviewed."

### 3.7 coupons → coupon_usages → orders/users
`coupon_usages` is a join/ledger that records each use. `UNIQUE (coupon_id, order_id)` prevents double application. Counting rows enforces `usage_limit` and `per_user_limit`.

### 3.8 categories self-reference
`categories.parent_id` allows an arbitrary tree. Deleting a parent sets children's `parent_id` to NULL (they become top-level), so no orphaned/empty categories.

---

## 4. Index Coverage

For each foreign key, an index is created (MySQL implicitly indexes FKs, but we name them explicitly). Composite indexes:

| Table           | Composite Index            | Purpose                              |
|-----------------|----------------------------|--------------------------------------|
| cart_items      | UNIQUE (cart_id, product_id) | one line per product in a cart      |
| wishlist_items  | UNIQUE (wishlist_id, product_id) | one saved instance per product   |
| reviews         | UNIQUE (user_id, product_id) | one review per product per customer |
| coupon_usages   | UNIQUE (coupon_id, order_id) | prevent double application          |
| orders          | INDEX (status), INDEX (payment_status) | filtering/reporting          |
| products        | INDEX (category_id), INDEX (status), INDEX (price) | catalog queries      |
| inventory_transactions | INDEX (product_id), INDEX (reference_type, reference_id) | ledger lookups |

---

## 5. Soft vs Hard Delete Policy

| Table                 | Policy   | Reason |
|-----------------------|----------|--------|
| users                 | soft     | retain order/review history for customers/staff |
| categories            | soft     | retain products historically |
| products              | soft     | retain historical order/metadata; disable instead of delete |
| coupons               | soft     | retain usage history |
| orders, order_items, coupon_usages, inventory_transactions | immutable (no deletes) | audit trail & historical integrity |
| cart_items, wishlist_items, product_images, payments, notifications | cascade/hard | subordinate details, no independent history |

This policy guarantees **historical order information remains valid even if a product is later changed or disabled**, and **important historical product/order data is never physically deleted**.
