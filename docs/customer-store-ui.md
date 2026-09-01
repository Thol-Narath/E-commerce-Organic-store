# Organic Store E-Commerce System — Customer Store UI

> Phase 5 — Customer E-Commerce Website. Version 1.0.0

This document describes the customer-facing React storefront: its architecture, pages, shared components, API integration, state handling, and the decisions made during implementation.

---

## 1. Overview

The customer website is a React SPA (Vite + React 18 + Bootstrap 5 + React-Bootstrap) that consumes the Phase 3 / Phase 4 Laravel REST API (`/api/v1`). It covers:

- Store layout (header, footer, responsive navigation)
- Homepage (hero, categories, featured products, value props)
- Shop / product listing with search, filters, sorting, pagination
- Product detail with image gallery
- Category browsing
- Login / Register UI and Customer profile
- About / Contact / 404 pages

Deliberately **not** in this phase (later phases): cart, wishlist, checkout, orders, payments, reviews, coupons, admin dashboard.

The storefront is fully responsive (desktop / tablet / mobile), uses only real API data (no mock data), and shows proper loading, empty, error, and 404 states.

---

## 2. Tech Stack & Conventions

| Item | Choice |
| --- | --- |
| Build tool | Vite 5 |
| Framework | React 18 (function components + hooks) |
| Styling | Bootstrap 5 + React-Bootstrap + custom CSS variables |
| HTTP | Axios instance (`src/services/api.js`) |
| Routing | React Router v6 (`createBrowserRouter`-style `<Routes>` wrapper) |
| Icons | Inline SVG components in `src/assets/icons.jsx` |
| Currency | `src/utils/format.js` → `formatPrice()` (LKR); `formatDate()`; `discountPercent()` |

### 2.1 Environment

Copy `frontend/.env.example` to `frontend/.env` if needed:

```
VITE_API_BASE_URL=/api/v1
```

`api.js` falls back to `/api/v1` when the variable is not set, so in development Vite proxies to the Laravel server.

---

## 3. Application Structure

```
frontend/src/
├── App.jsx                      # BrowserRouter > AuthProvider > AppRoutes
├── main.jsx                     # entry (Bootstrap CSS + index.css + App)
├── index.css                    # organic theme + admin classes preserved
├── routes/AppRoutes.jsx         # canonical route tree
├── layouts/
│   ├── CustomerLayout.jsx       # storefront shell (Header + Outlet + Footer, scroll-to-top)
│   └── AdminLayout.jsx          # admin shell (unchanged, /admin)
├── context/AuthContext.jsx      # auth + user state (login/logout/updateUser)
├── services/
│   ├── api.js                   # axios instance, envelope + token helpers
│   ├── productService.js        # getProducts / getFeaturedProducts / getProduct
│   ├── categoryService.js       # getCategories / getCategory / getCategoryProducts
│   └── authService.js           # register / login / logout / getProfile / updateProfile
├── hooks/
│   ├── useProductQuery.js       # shared shop/category listing logic
│   └── usePageTitle.js          # document.title helper
├── utils/
│   ├── format.js                # formatPrice, formatDate, discountPercent
│   ├── queryState.js            # cleanParams, buildParams, paramsToObject
│   └── error.js                 # getErrorMessage
├── components/
│   ├── common/                  # LoadingSpinner, EmptyState, ErrorState, PageHeader, ImageWithFallback
│   ├── layout/                  # Header, Footer
│   ├── product/                 # ProductCard, ProductGrid, ProductSkeletons, ProductGallery,
│   │                            # ProductSearch, ProductSort, ProductCategoryFilter,
│   │                            # ProductPriceFilter, ProductFiltersBar, ProductPagination
│   ├── category/                # CategoryCard, CategoryGrid
│   └── admin/                   # (admin components, preserved)
├── pages/
│   ├── home/HomePage.jsx
│   ├── products/ProductListPage.jsx, ProductDetailsPage.jsx
│   ├── categories/CategoriesPage.jsx, CategoryProductsPage.jsx
│   ├── profile/ProfilePage.jsx
│   ├── auth/Login.jsx, Register.jsx
│   ├── admin/                   # admin pages (preserved)
│   └── AboutPage.jsx, ContactPage.jsx, NotFoundPage.jsx
└── assets/icons.jsx             # Leaf, Cart, User, Search, Truck, Shield, Store, Tag, Star, Phone, Mail, MapPin
```

---

## 4. Routes

| Path | Page | Access |
| --- | --- | --- |
| `/` | HomePage | public |
| `/shop` | ProductListPage | public |
| `/products/:slug` | ProductDetailsPage | public |
| `/categories` | CategoriesPage | public |
| `/categories/:slug` | CategoryProductsPage | public |
| `/about` | AboutPage | public |
| `/contact` | ContactPage | public |
| `/login` | Login | guest-only (redirects to `/` if authenticated) |
| `/register` | Register | guest-only |
| `/profile` | ProfilePage | customer |
| `/admin/...` | admin pages | admin/staff (ProtectedRoute) |
| `*` | NotFoundPage | public |

All public pages render inside `CustomerLayout` (pathless `<Route element={<CustomerLayout />}>`). The admin group is untouched.

---

## 5. Catalog Consumption & Query State

### 5.1 API services

- `productService.getProducts(params)` → `GET /products` with `search, category_id, min_price, max_price, sort, per_page, page`. Returns `data.items` + `data.pagination`.
- `productService.getProduct(slug)` → `GET /products/{slug}` (`data` is the product resource with `images`).
- `productService.getFeaturedProducts(params)` → `GET /products/featured`.
- `categoryService.getCategories()` → array under `data`.
- `categoryService.getCategory(slug)` → category under `data`.
- `categoryService.getCategoryProducts(slug, params)` → `GET /categories/{slug}/products` (same `items` + `pagination` shape).

### 5.2 Sorting

Only backend-whitelisted sort keys are used (`ProductService::SORT_WHITELIST`):

`newest`, `oldest`, `price_low`, `price_high`, `name_asc`, `name_desc`

`ProductSort.jsx` exports `SORT_OPTIONS`; default is `newest`.

### 5.3 URL-driven filters (Shop + Category pages)

Selecting a category filters the store to only that category. Clicking a category radiolist behaves as expected; unchecking resets/clears the category filter. Pagination, sorting, price, search text, active category, and any unsupported/unknown query parameters are dropped from the URL before the request is built.

`useProductQuery` keeps filters in sync with the URL (via `useSearchParams`):

- Shop page keys: `search`, `category_id`, `min_price`, `max_price`, `sort`, `page`
- Category page keys: `sort`, `page` (category comes from the route slug)
- Searching applies on submit (button / Enter) to avoid request churn.
- Each parameter change resets `page` to 1; `key` on the grid remounts on a new query to clear stale UI.
- Response stores `items` + `pagination`; total pages clamp to `max(1, last_page)`.

---

## 6. Pages

### 6.1 Home (`/`)
Hero (brand + CTA), featured categories (top 8 by sort order), "Featured Products" (4), "Why Choose Us" features, CTA banner. Uses `getFeaturedProducts({per_page: 4})` lazily (loading / error handled per section via `ErrorState` + fallback renders).

### 6.2 Shop (`/shop`)
Sidebar: search, category radiolist, price min/max, clear-filters button. Toolbar: result count + sort dropdown. Body: skeleton grid → product grid / empty / error. Bottom: pagination.

### 6.3 Product detail (`/products/:slug`)
Breadcrumb, gallery (`ProductGallery` with active-image state + fallbacks), category chip, sku/unit/short description, price + compare-at price + discount badge, disabled Add to Cart placeholder, description, features/trust badges, "Related Products". Handles 404 (`normalizeError.status === 404`) and inactive products with a proper not-found state.

### 6.4 Categories (`/categories`) & Category products (`/categories/:slug`)
`CategoriesPage` uses the same layout as shop but without the category list; `CategoryProductsPage` shows the single category's own products via the dedicated `/categories/{slug}/products` endpoint.

### 6.5 Auth (`/login`, `/register`)
Login: email/password → `authService.login` → stores token + user via `AuthContext`. Register: name/email/password/password_confirmation. Both redirect authenticated users to `/`. After login, the `me` profile is fetched.

### 6.6 Profile (`/profile`)
Protected customer page. Loads profile (`authService.getProfile`), shows name/email/phone + avatar fallback, and lets the customer update name/phone (backend validates uniqueness; email read-only in this phase). Uses `AuthContext.updateUser` to keep header/user state current.

### 6.7 About / Contact / NotFound
About: brand story + value props. Contact: address/phone/email info + disabled "coming soon" form (deliberately no fake submit), per Phase 5 scope. NotFound: friendly 404 with link home.

---

## 7. Shared Components

### 7.1 Common
- `LoadingSpinner` — Bootstrap spinner + optional label.
- `EmptyState` — icon/title/text + optional link with `Link`.
- `ErrorState` — icon/title/message/text + retry button (unused test/retry variants removed).
- `PageHeader` — title/subtitle/breadcrumb-lite section header.
- `ImageWithFallback` — shows image; on error/missing (`images/...` seeded paths 404) renders branded leaf placeholder.

### 7.2 Layout
- `Header` — announcement bar, brand (logo icon + name), header-wide product search (navigates to `/shop?search=`), user dropdown (Login/Register vs name + Profile/Logout), disabled cart placeholder with title "available in a later phase", responsive nav with Bootstrap `Offcanvas`.
- `Footer` — brand blurb, quick links, account links, contact placeholders, copyright.

### 7.3 Product
- `ProductCard` — image (fallback), category + featured badge, name, rating-styled star, price + compare-at + discount, sku row, card link. Whole-card clickable; no fake add-to-cart (reserved for Phase 6).
- `ProductGrid` / `ProductSkeletons` — responsive grid at multiple breakpoints and shimmer placeholders.
- `ProductSearch` — controlled input, submit via form button/Enter.
- `ProductSort` — sort select with `SORT_OPTIONS`.
- `ProductCategoryFilter` — category radiolist (counts), "All" option.
- `ProductPriceFilter` — min/max numeric inputs.
- `ProductFiltersBar` — composes search/category/price; exposes `hasFilters` + `resetFilters`.
- `ProductPagination` — pagination with ellipsis (`...`), always shows at least first/last.
- `ProductGallery` — main image + optional thumbnails from `product.images`.

### 7.4 Category
- `CategoryCard` / `CategoryGrid` — icon + name + product count, card link.

---

## 8. Error Handling

- Axios response interceptor normalizes API JSON envelopes; `normalizeError` resolves status, JSON message, or a generic fallback.
- Pages distinguish: not-found (404) → not-found states; other API errors → `ErrorState` with retry.
- Auth persistence failures (missing token/user) are surfaced by `AuthContext.loadUser` and surface a friendly message on `/profile` (via `ProfilePage`), so the user is not stuck on a blank page.

---

## 9. CSV / Data Limitations in Phase 5

- Seeded product/category image files do not exist on disk (`backend/storage/app/public`), so served image URLs 404. `ImageWithFallback` shows a branded placeholder; URLs already point to `/storage/...` via symlink when assets are added later.
- Contact form is intentionally disabled — the backend contact endpoint is out of scope for Phase 5.
- Cart / wishlist / checkout / orders are placeholders only, to be implemented in Phase 6+.

---

## 10. Verify & Run

```bash
# backend (terminal 1)
cd backend
php artisan serve

# frontend (terminal 2)
cd frontend
npm install
npm run dev

# production build (validated clean)
npm run build
```

Verified against the live API (all contract shapes match):

- `GET /api/v1/products` (+ search, sort, category_id, price range, page)
- `GET /api/v1/products/featured`
- `GET /api/v1/products/{slug}` (and 404 handling)
- `GET /api/v1/categories`, `/categories/{slug}`, `/categories/{slug}/products`
- `POST /auth/login`, `GET /auth/me`, `GET /profile`
- Backend suite: `php artisan test` → 74 passed (423 assertions); `npm run build` → clean.

---

## 11. Out of Scope (later phases)

- Phase 9: Admin Dashboard (React)
- Phase 10: Reviews + Coupons + Reports
- Phase 11: Testing + Security + Optimization

This document covers the customer experience only.

> **Update (Phase 8):** Cart/wishlist (Phase 6), checkout/addresses/orders (Phase 7) and Payment +
> Inventory (Phase 8) are now implemented. See the phase sections in `api-design.md`
> (§3.2–3.5, §3.8, §4.1) and `business-rules.md` (§3c–3d, §4–5, §2) for their documented behavior.