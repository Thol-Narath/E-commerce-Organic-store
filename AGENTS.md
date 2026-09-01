# Organic Store E-Commerce System

## Project Goal

Build a professional full-stack Organic Store E-Commerce System.

The application consists of:

1. Customer E-Commerce Website
2. Admin Dashboard
3. Laravel 10 REST API
4. MySQL Database

---

# Technology Stack

## Backend

- Laravel 10
- PHP
- Laravel Sanctum
- Eloquent ORM
- REST API

## Frontend

- React.js
- Bootstrap 5
- React-Bootstrap
- Axios
- React Router
- Chart.js

## Database

- MySQL

---

# Architecture

Use a separated frontend/backend architecture.

React Customer Website
        |
        | REST API
        v
Laravel 10 REST API
        |
        | Eloquent ORM
        v
MySQL

React Admin Dashboard
        |
        | REST API
        v
Laravel 10 REST API

React must never directly access MySQL.

Laravel is responsible for:

- Authentication
- Authorization
- Validation
- Business logic
- Database operations
- Orders
- Payments
- Inventory
- Coupons
- Reviews
- Reports

---

# User Roles

## Customer

Customers can:

- Register
- Login
- Logout
- View products
- Search products
- Filter products
- View categories
- View product details
- Add products to cart
- Update cart
- Remove cart items
- Manage wishlist
- Manage profile
- Manage addresses
- Checkout
- Place orders
- View orders
- Track orders
- Review purchased products

## Admin

Admins can:

- Login
- View dashboard
- Manage products
- Manage categories
- Manage customers
- Manage orders
- Manage payments
- Manage inventory
- Manage reviews
- Manage coupons
- View reports
- Manage staff
- Manage settings

## Staff

Staff has limited permissions.

Staff can:

- View products
- Manage inventory
- View orders
- Update order status
- View customers

Staff must not have full Admin permissions.

---

# Database Tables

Use these tables:

- users
- categories
- products
- product_images
- addresses
- carts
- cart_items
- wishlists
- wishlist_items
- orders
- order_items
- payments
- reviews
- coupons
- coupon_usages
- inventory_transactions
- notifications
- settings

---

# Important Database Rules

Use:

- Primary keys
- Foreign keys
- Unique constraints
- Appropriate indexes
- Timestamps
- Appropriate nullable fields
- Appropriate defaults
- Appropriate delete/update rules

Historical order information must remain valid even if a product is later changed or disabled.

Do not physically delete important historical product/order data.

Use soft deletion where appropriate.

---

# Important Business Rules

## Product Stock

Never allow:

ordered_quantity > available_stock

Never allow negative stock.

## Cart

Customers cannot add:

- Inactive products
- Out-of-stock products
- More quantity than available stock

## Orders

Order totals must always be calculated by Laravel.

Never trust prices or totals received from React.

Laravel must calculate:

- Product price
- Subtotal
- Discount
- Shipping fee
- Total

## Order Items

Store product name and price snapshots inside order_items.

This protects historical orders when product information changes.

## Inventory

Inventory operations must use database transactions where appropriate.

Every stock change must create an inventory transaction record.

## Reviews

A customer can review a product only if they purchased that product.

## Coupons

Coupons must always be validated on the backend.

## Authorization

Never rely only on React for authorization.

Laravel must enforce permissions using middleware and/or policies.

---

# API

Use versioned APIs:

/api/v1

Use RESTful conventions.

Use appropriate HTTP status codes.

Use consistent API responses.

Success:

{
    "success": true,
    "message": "Success",
    "data": {}
}

Error:

{
    "success": false,
    "message": "Something went wrong",
    "data": null
}

---

# Laravel Architecture

Recommended structure:

backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   ├── Resources/
│   │   └── Middleware/
│   ├── Models/
│   ├── Services/
│   ├── Policies/
│   └── Exceptions/
├── database/
│   ├── migrations/
│   ├── factories/
│   └── seeders/
├── routes/
│   └── api.php
└── .env

Use Services for complex business logic:

- CartService
- OrderService
- PaymentService
- InventoryService
- CouponService

Keep controllers thin.

---

# React Architecture

Recommended structure:

frontend/
└── src/
    ├── components/
    ├── pages/
    ├── layouts/
    ├── admin/
    ├── services/
    ├── context/
    ├── hooks/
    ├── routes/
    ├── utils/
    └── assets/

Use reusable components.

Use Bootstrap 5 / React-Bootstrap.

The interface must be responsive for:

- Desktop
- Tablet
- Mobile

---

# Security

Follow Laravel security best practices.

Implement:

- Password hashing
- Laravel Sanctum
- Authorization
- Form Request validation
- Policies
- Middleware
- Rate limiting
- Secure file uploads
- SQL injection protection
- XSS protection

Never hardcode:

- Passwords
- API secrets
- Database credentials
- Payment credentials

Use .env for secrets.

---

# Code Quality

Follow:

- Laravel conventions
- React conventions
- SOLID where appropriate
- DRY
- Clean Code

Use meaningful names.

Avoid duplicated code.

Do not create fake placeholder implementations.

Do not leave unfinished TODO implementations when a feature is requested.

---

# Agent Development Rules

Before modifying code:

1. Inspect the current project.
2. Understand the existing architecture.
3. Identify files that need modification.
4. Make the smallest reasonable changes.
5. Run relevant tests or commands.
6. Fix errors.
7. Explain what changed.

Do not modify unrelated files.

Do not delete working code without a reason.

Do not rewrite the entire application to fix one error.

When an error occurs, identify the root cause before changing code.

---

# Development Phases

## Phase 1
Architecture + Database Design + ERD

## Phase 2
Laravel 10 + MySQL + Migrations + Models + Seeders

## Phase 3
Authentication + Roles + Authorization

## Phase 4
Product + Category APIs

## Phase 5
React Customer Website

## Phase 6
Cart + Wishlist

## Phase 7
Checkout + Order System

## Phase 8
Payment + Inventory

## Phase 9
React Admin Dashboard

## Phase 10
Reviews + Coupons + Reports

## Phase 11
Testing + Security + Optimization

## Phase 12
Deployment + Documentation

---

# Critical Rule

DO NOT build the entire project at once.

Work phase by phase.

After completing each phase:

1. Show what was implemented.
2. Show files created or modified.
3. Run relevant tests.
4. Report errors or remaining issues.
5. Stop and wait for the next instruction.

Do not automatically continue to the next phase.

---

# Destructive Commands

Never run destructive commands against an unknown database without confirmation.

Be especially careful with:

- migrate:fresh
- database drops
- rm -rf
- deleting project directories

If a command can destroy existing data, ask for confirmation first.

---

# Current Project Objective

Build a production-quality Organic Store E-Commerce System using:

Laravel 10 + React.js + Bootstrap 5 + MySQL.

The database design must support:

- Products
- Categories
- Customers
- Addresses
- Shopping Cart
- Wishlist
- Orders
- Payments
- Inventory
- Reviews
- Coupons
- Notifications
- Admin Dashboard
- Sales Reports

Start with architecture and database design before implementing application features.