<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attach a (nullable) shipping method to every order.
     *
     * A snapshot of the method name is kept so historical orders remain
     * understandable even if the admin later renames, disables or deletes the
     * method. The fee itself is already snapshotted in the `shipping_fee`
     * column at placement time.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shipping_method_id')
                ->nullable()
                ->after('address_id')
                ->constrained('shipping_methods')
                ->nullOnDelete();
            $table->string('shipping_method_name', 120)->nullable()->after('shipping_method_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_method_id');
            $table->dropColumn('shipping_method_name');
        });
    }
};