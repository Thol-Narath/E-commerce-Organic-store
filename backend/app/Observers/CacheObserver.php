<?php

namespace App\Observers;

use App\Models\Banner;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Review;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Services\CacheService;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps the response cache consistent with the database.
 *
 * Registered for every model whose data is cached in a versioned bucket
 * (see CacheService). Any create / update / delete / restore bumps the
 * relevant bucket version(s) so the storefront and dashboard never serve
 * stale data after a write.
 */
class CacheObserver
{
    public function __construct(private readonly CacheService $cache) {}

    public function saved(Model $model): void
    {
        $this->invalidateFor($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidateFor($model);
    }

    public function restored(Model $model): void
    {
        $this->invalidateFor($model);
    }

    private function invalidateFor(Model $model): void
    {
        // Product changes also affect category product counts and public stats.
        if ($model instanceof Product
            || $model instanceof ProductImage   // images are rendered inside product payloads
            || $model instanceof Review) {      // avg_rating / reviews_count appear in product payloads
            $this->cache->invalidate('products', 'categories', 'stats');

            return;
        }

        // Category renames/status changes appear inside product payloads too.
        if ($model instanceof Category) {
            $this->cache->invalidate('categories', 'products', 'stats');

            return;
        }

        if ($model instanceof Banner || $model instanceof Blog || $model instanceof Testimonial) {
            $this->cache->invalidate('content');

            return;
        }

        if ($model instanceof Setting) {
            $this->cache->invalidate('settings');

            return;
        }

        // Order + payment writes affect the admin KPIs and revenue charts;
        // order placement moves stock (Product saved) which invalidates the
        // catalog separately.
        if ($model instanceof Order || $model instanceof Payment) {
            $this->cache->invalidate('orders', 'revenue');
        }
    }
}
