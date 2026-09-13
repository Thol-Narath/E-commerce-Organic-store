<?php

namespace App\Providers;

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
use App\Observers\CacheObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerCacheObservers();
    }

    /**
     * Keep the response cache consistent with the database: every relevant
     * model write invalidates the affected cache buckets automatically.
     */
    protected function registerCacheObservers(): void
    {
        Product::observe(CacheObserver::class);
        ProductImage::observe(CacheObserver::class);
        Category::observe(CacheObserver::class);
        Review::observe(CacheObserver::class);
        Banner::observe(CacheObserver::class);
        Blog::observe(CacheObserver::class);
        Testimonial::observe(CacheObserver::class);
        Setting::observe(CacheObserver::class);
        Order::observe(CacheObserver::class);
        Payment::observe(CacheObserver::class);
    }
}
