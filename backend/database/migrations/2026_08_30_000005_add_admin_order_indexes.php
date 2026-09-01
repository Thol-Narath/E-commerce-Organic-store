<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the indexes the admin order dashboard relies on (Phase 9).
     *
     * No duplicates are created:
     *   - orders.order_number  -> already covered by the UNIQUE index;
     *   - orders.user_id       -> already indexed by the FK;
     *   - orders.status        -> already indexed;
     *   - orders.payment_status-> already indexed;
     *   - payments.order_id    -> already indexed by the FK;
     *   - payments.payment_status -> already covered by (order_id, payment_status).
     *
     * Added here: timestamp/sort columns and the two lookup columns the list
     * filters join on (payment_method via whereHas).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('placed_at');
            $table->index('created_at');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index('transaction_id');
            $table->index('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['placed_at']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['transaction_id']);
            $table->dropIndex(['payment_method']);
        });
    }
};