<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE payments MODIFY payment_method VARCHAR(50) NULL");
        DB::statement("ALTER TABLE payments RENAME COLUMN status TO payment_status");
        DB::statement("ALTER TABLE payments MODIFY payment_status VARCHAR(20) NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE payments MODIFY gateway_response LONGTEXT NULL");

        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_number', 30)->nullable()->after('id');
            $table->string('gateway', 30)->default('payway')->after('payment_method');
            $table->string('gateway_transaction_id', 255)->nullable()->after('transaction_id');
            $table->string('gateway_reference', 255)->nullable()->after('gateway_transaction_id');
            $table->string('currency', 5)->default('USD')->after('amount');
            $table->text('qr_string')->nullable()->after('gateway_response');
            $table->string('deeplink', 500)->nullable()->after('qr_string');
            $table->timestamp('expires_at')->nullable()->after('deeplink');

            $table->unique('payment_number');
            $table->unique('gateway_transaction_id');
            $table->index(['order_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'payment_status']);
            $table->dropUnique(['gateway_transaction_id']);
            $table->dropUnique(['payment_number']);
            $table->dropColumn(['expires_at', 'deeplink', 'qr_string', 'currency', 'gateway_reference', 'gateway_transaction_id', 'gateway', 'payment_number']);
        });

        DB::statement("ALTER TABLE payments MODIFY gateway_response TEXT NULL");
        DB::statement("ALTER TABLE payments MODIFY payment_status ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE payments RENAME COLUMN payment_status TO status");
        DB::statement("ALTER TABLE payments MODIFY payment_method ENUM('cod', 'card', 'bank_transfer', 'online') NOT NULL DEFAULT 'cod'");
    }
};