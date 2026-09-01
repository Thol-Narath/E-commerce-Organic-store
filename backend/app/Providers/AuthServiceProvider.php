<?php

namespace App\Providers;

use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Policies\InventoryPolicy;
use App\Policies\OrderPolicy;
// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Order::class => OrderPolicy::class,
        Product::class => InventoryPolicy::class,
        InventoryTransaction::class => InventoryPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        //
    }
}
