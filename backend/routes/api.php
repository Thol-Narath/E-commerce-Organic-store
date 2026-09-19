<?php

use App\Http\Controllers\Api\V1\AdminCategoryController;
use App\Http\Controllers\Api\V1\AdminOrderController;
use App\Http\Controllers\Api\V1\AdminProductController;
use App\Http\Controllers\Api\V1\AdminBannerController;
use App\Http\Controllers\Api\V1\AdminSupplierController;
use App\Http\Controllers\Api\V1\AddressController;
use App\Http\Controllers\Api\V1\AdminContactController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BakongPaymentController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\HostedCheckoutController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaymentMethodController;
use App\Http\Controllers\Api\V1\PayWayWebhookController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\ShippingMethodController;
use App\Http\Controllers\Api\V1\AdminShippingMethodController;
use App\Http\Controllers\Api\V1\WishlistController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All API routes live under /api/v1.
| - Public: register, login, categories, products
| - Authenticated (auth:sanctum + active): logout, me, profile, password
| - Admin/staff: product & category management (role-gated)
|
*/

Route::prefix('v1')->group(function () {

    // Public read-only catalog/content (no auth). Responses carry
    // Cache-Control + ETag headers so browsers/CDNs can serve repeat requests
    // without a network round-trip. All these routes are anonymous, so a
    // shared cache is safe; body hashes (ETag) keep revalidation correct.
    Route::middleware('cache.headers:public;max_age=300;etag')->group(function () {
        Route::get('categories', [CategoryController::class, 'index']);
        Route::get('categories/{category:slug}', [CategoryController::class, 'show']);
        Route::get('categories/{category:slug}/products', [CategoryController::class, 'products']);
        Route::get('products/featured', [ProductController::class, 'featured']);
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{product:slug}', [ProductController::class, 'show']);

        // Public content: banners, testimonials, blogs
        Route::get('banners', [\App\Http\Controllers\Api\V1\BannerController::class, 'index']);
        Route::get('testimonials', [\App\Http\Controllers\Api\V1\TestimonialController::class, 'index']);
        Route::get('blogs', [\App\Http\Controllers\Api\V1\BlogController::class, 'index']);
        Route::get('blogs/{slug}', [\App\Http\Controllers\Api\V1\BlogController::class, 'show']);

        // Public stats (active product/category counts)
        Route::get('stats', [\App\Http\Controllers\Api\V1\SettingsController::class, 'stats']);

        // Public store information used by the checkout summary (display only).
        Route::get('settings/public', [SettingsController::class, 'publicSettings']);

        // Public payment information (Phase 8).
        Route::get('payment-methods', [PaymentMethodController::class, 'index']);
    });

    // Public shipping methods: intentionally NOT cached (app-level or HTTP) so
    // an admin enabling/disabling a method takes effect immediately at checkout.
    Route::get('shipping-methods', [ShippingMethodController::class, 'index']);

    // Public mutations stay uncached.
    Route::post('newsletter/subscribe', [\App\Http\Controllers\Api\V1\NewsletterController::class, 'subscribe']);

    // Public contact form submissions (rate-limited to guard against spam).
    Route::post('contact', [\App\Http\Controllers\Api\V1\ContactController::class, 'store'])
        ->middleware('throttle:5,1');

    // PayWay callback — public by design, protected by HMAC signature.
    Route::post('payments/payway/webhook', [PayWayWebhookController::class, 'handle']);

    // Hosted card checkout page for iframe rendering, guarded by a temporary
    // signed URL (no auth token needed inside the iframe).
    Route::get('payments/payway/checkout/{payment}', [HostedCheckoutController::class, 'show'])
        ->middleware('signed')
        ->name('payments.payway.checkout');

    // Bakong transaction check — public by design, only reveals paid status for
    // a given MD5 hash (used by the Bakong Open API integration).
    Route::post('bakong/check-payment', [BakongPaymentController::class, 'check']);

    // Public authentication (rate-limited to mitigate brute force)
    Route::post('auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:6,1');

    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1');

    Route::post('auth/google', [AuthController::class, 'google'])
        ->middleware('throttle:6,1');

    // Authenticated customer / account routes.
    // `active` middleware blocks inactive/banned accounts server-side.
    Route::middleware(['auth:sanctum', 'active'])->group(function () {

        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        // User profile
        Route::get('profile', [ProfileController::class, 'show']);
        Route::put('profile', [ProfileController::class, 'update']);
        Route::put('profile/password', [ProfileController::class, 'updatePassword']);
        Route::post('profile/avatar', [ProfileController::class, 'uploadAvatar']);

        // Customer cart + wishlist (Phase 6)
        Route::get('cart', [CartController::class, 'index']);
        Route::post('cart/items', [CartController::class, 'store']);
        Route::patch('cart/items/{cartItem}', [CartController::class, 'update']);
        Route::delete('cart/items/{cartItem}', [CartController::class, 'destroy']);
        Route::delete('cart', [CartController::class, 'clear']);

        Route::get('wishlist', [WishlistController::class, 'index']);
        Route::post('wishlist/items', [WishlistController::class, 'store']);
        Route::delete('wishlist/items/{wishlistItem}', [WishlistController::class, 'destroy']);
        Route::post('wishlist/items/{wishlistItem}/move-to-cart', [WishlistController::class, 'moveToCart']);

        // Customer product reviews / ratings (Phase 10).
        Route::post('reviews', [ReviewController::class, 'store']);

        // Customer addresses (Phase 7)
        Route::get('addresses', [AddressController::class, 'index']);
        Route::post('addresses', [AddressController::class, 'store']);
        Route::get('addresses/{address}', [AddressController::class, 'show']);
        Route::patch('addresses/{address}', [AddressController::class, 'update']);
        Route::delete('addresses/{address}', [AddressController::class, 'destroy']);
        Route::patch('addresses/{address}/default', [AddressController::class, 'setDefault']);

        // Checkout + orders (Phase 7)
        Route::post('checkout', [OrderController::class, 'checkout']);
        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{orderNumber}', [OrderController::class, 'show']);
        Route::post('orders/{orderNumber}/cancel', [OrderController::class, 'cancel']);

        // Payments (Phase 8)
        Route::post('orders/{orderNumber}/payments', [PaymentController::class, 'store']);
        Route::get('orders/{orderNumber}/payment-status', [PaymentController::class, 'status']);
        Route::post('orders/{orderNumber}/payments/{payment}/refresh', [PaymentController::class, 'refresh']);

        // Admin + staff read access to the catalog (view inactive/draft too).
        Route::middleware('role.admin_or_staff')->prefix('admin')->group(function () {
            Route::get('categories', [AdminCategoryController::class, 'index']);
            Route::get('categories/{category}', [AdminCategoryController::class, 'show']);
            Route::get('products', [AdminProductController::class, 'index']);
            Route::get('products/{product}', [AdminProductController::class, 'show']);

            // Inventory (Phase 8): stock overview + manual adjustments.
            Route::get('inventory', [InventoryController::class, 'index']);
            Route::post('inventory/adjust', [InventoryController::class, 'adjust']);

            // Inventory (Phase 10): statistics, per-product detail and stock
            // actions. All parameterized routes constrain {product} to numbers
            // so the literal `transactions` path below never collides.
            Route::get('inventory/statistics', [InventoryController::class, 'statistics']);
            Route::get('inventory/{product}', [InventoryController::class, 'show'])->whereNumber('product');
            Route::get('inventory/{product}/transactions', [InventoryController::class, 'productTransactions'])->whereNumber('product');
            Route::post('inventory/{product}/add', [InventoryController::class, 'addStock'])->whereNumber('product');
            Route::post('inventory/{product}/remove', [InventoryController::class, 'removeStock'])->whereNumber('product');
            Route::post('inventory/{product}/adjust', [InventoryController::class, 'adjustStock'])->whereNumber('product');
            Route::patch('inventory/{product}/reorder-level', [InventoryController::class, 'updateReorderLevel'])->whereNumber('product');

            // Orders (Phase 9): admin + staff can view orders.
            Route::get('orders', [AdminOrderController::class, 'index']);
            Route::get('orders/{order}', [AdminOrderController::class, 'show'])->whereNumber('order');
        });

        // Admin-only catalog management.
        Route::middleware('role.admin')->prefix('admin')->group(function () {
            Route::post('categories', [AdminCategoryController::class, 'store']);
            Route::put('categories/{category}', [AdminCategoryController::class, 'update']);
            Route::delete('categories/{category}', [AdminCategoryController::class, 'destroy']);

            Route::post('products', [AdminProductController::class, 'store']);
            Route::put('products/{product}', [AdminProductController::class, 'update']);
            Route::delete('products/{product}', [AdminProductController::class, 'destroy']);
            Route::patch('products/{product}/status', [AdminProductController::class, 'updateStatus']);
            Route::patch('products/{product}/featured', [AdminProductController::class, 'updateFeatured']);

            Route::post('products/{product}/images', [AdminProductController::class, 'uploadImage']);
            Route::post('products/{product}/images/{image}/primary', [AdminProductController::class, 'setPrimaryImage']);
            Route::delete('products/{product}/images/{image}', [AdminProductController::class, 'deleteImage']);

            // Banner management (CRUD + reorder).
            Route::get('banners', [AdminBannerController::class, 'index']);
            Route::post('banners', [AdminBannerController::class, 'store']);
            Route::get('banners/{banner}', [AdminBannerController::class, 'show']);
            Route::put('banners/{banner}', [AdminBannerController::class, 'update']);
            Route::delete('banners/{banner}', [AdminBannerController::class, 'destroy']);
            Route::patch('banners/{banner}/toggle', [AdminBannerController::class, 'toggle']);
            Route::post('banners/reorder', [AdminBannerController::class, 'reorder']);

            // Suppliers (admin only): supplier CRUD + purchase orders to
            // restock low-inventory products from suppliers.
            Route::get('suppliers/options', [AdminSupplierController::class, 'options']);
            Route::get('suppliers', [AdminSupplierController::class, 'index']);
            Route::post('suppliers', [AdminSupplierController::class, 'store']);
            Route::get('suppliers/{supplier}', [AdminSupplierController::class, 'show']);
            Route::put('suppliers/{supplier}', [AdminSupplierController::class, 'update']);
            Route::delete('suppliers/{supplier}', [AdminSupplierController::class, 'destroy']);
            Route::patch('suppliers/{supplier}/toggle', [AdminSupplierController::class, 'toggle']);

            Route::get('supplier-orders', [AdminSupplierController::class, 'orders']);
            Route::post('supplier-orders', [AdminSupplierController::class, 'createOrder']);
            Route::get('supplier-orders/{order}', [AdminSupplierController::class, 'showOrder'])->whereNumber('order');
            Route::post('supplier-orders/{order}/place', [AdminSupplierController::class, 'placeOrder'])->whereNumber('order');
            Route::post('supplier-orders/{order}/receive', [AdminSupplierController::class, 'receiveOrder'])->whereNumber('order');
            Route::post('supplier-orders/{order}/cancel', [AdminSupplierController::class, 'cancelOrder'])->whereNumber('order');

            // Shipping methods (admin only): CRUD + status/default toggles.
            Route::get('shipping-methods', [AdminShippingMethodController::class, 'index']);
            Route::post('shipping-methods', [AdminShippingMethodController::class, 'store']);
            Route::get('shipping-methods/{shipping_method}', [AdminShippingMethodController::class, 'show']);
            Route::put('shipping-methods/{shipping_method}', [AdminShippingMethodController::class, 'update']);
            Route::delete('shipping-methods/{shipping_method}', [AdminShippingMethodController::class, 'destroy']);
            Route::patch('shipping-methods/{shipping_method}/toggle', [AdminShippingMethodController::class, 'toggle']);
            Route::patch('shipping-methods/{shipping_method}/default', [AdminShippingMethodController::class, 'setDefault']);
        });

        // Admin-scoped routes: admin only.
        Route::middleware('role.admin')->prefix('admin')->group(function () {
            Route::get('me', [AuthController::class, 'me']);

            // Inventory audit trail (admin only; staff can manage stock but not
            // view the full ledger).
            Route::get('inventory/transactions', [InventoryController::class, 'transactions']);

            // Orders (Phase 9): statistics, cancellation, admin notes.
            Route::get('orders/statistics', [AdminOrderController::class, 'statistics']);
            Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->whereNumber('order');
            Route::post('orders/{order}/cancel', [AdminOrderController::class, 'cancel'])->whereNumber('order');
            Route::post('orders/{order}/notes', [AdminOrderController::class, 'addNote'])->whereNumber('order');

            // Dashboard: revenue trend over time.
            Route::get('dashboard/revenue-trend', [SettingsController::class, 'revenueTrend']);

            // Store settings (admin only): logo control.
            Route::get('settings/logo', [SettingsController::class, 'getLogo']);
            Route::post('settings/logo', [SettingsController::class, 'uploadLogo']);
            Route::delete('settings/logo', [SettingsController::class, 'removeLogo']);

            // About section image (admin only): homepage about image control.
            Route::get('settings/about-image', [SettingsController::class, 'getAboutImage']);
            Route::post('settings/about-image', [SettingsController::class, 'uploadAboutImage']);
            Route::delete('settings/about-image', [SettingsController::class, 'removeAboutImage']);

            // Store location, contact and Google Map config (admin only).
            Route::get('settings/store-location', [SettingsController::class, 'getStoreLocation']);
            Route::put('settings/store-location', [SettingsController::class, 'updateStoreLocation']);

            // Store branding (admin only): name, tagline, logo size.
            Route::put('settings/store-branding', [SettingsController::class, 'updateStoreBranding']);

            // Store contact details (admin only): address, phone, email.
            Route::put('settings/contact', [SettingsController::class, 'updateContact']);

            // Email delivery test (admin only): verifies SMTP sends a real
            // message. Rate-limited to avoid sending spam.
            Route::post('settings/test-email', [SettingsController::class, 'sendTestEmail'])
                ->middleware('throttle:3,1');

            // Customer contact inbox (admin only).
            Route::get('contact-messages', [AdminContactController::class, 'index']);
            Route::get('contact-messages/{contactMessage}', [AdminContactController::class, 'show']);
            Route::patch('contact-messages/{contactMessage}/read', [AdminContactController::class, 'markRead']);
            Route::post('contact-messages/{contactMessage}/reply', [AdminContactController::class, 'reply']);
            Route::delete('contact-messages/{contactMessage}', [AdminContactController::class, 'destroy']);
        });

        // Staff-scoped routes: staff or admin.
        Route::middleware('role.admin_or_staff')->prefix('staff')->group(function () {
            Route::get('me', [AuthController::class, 'me']);

            Route::get('inventory', [InventoryController::class, 'index']);
            Route::post('inventory/adjust', [InventoryController::class, 'adjust']);

            // Orders (Phase 9): staff can view orders and update status
            // (business-rules §1.3) but cannot cancel or add notes.
            Route::get('orders', [AdminOrderController::class, 'index']);
            Route::get('orders/{order}', [AdminOrderController::class, 'show'])->whereNumber('order');
            Route::patch('orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->whereNumber('order');
        });
    });
});