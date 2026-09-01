<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10 — Inventory Management.
 *
 * Adds the remaining query-optimisation indexes used by the inventory ledger
 * and its per-product screens. No schema/column changes: stock continues to
 * live on `products.stock_quantity` / `products.low_stock_threshold` and every
 * movement is recorded in `inventory_transactions` (existing table).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->index('type');
            $table->index('created_at');
            $table->index(['product_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'type']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['type']);
        });
    }
};