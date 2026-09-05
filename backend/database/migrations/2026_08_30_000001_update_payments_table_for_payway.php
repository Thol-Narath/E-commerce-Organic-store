<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('payments', 'status')) {
            DB::statement("ALTER TABLE payments CHANGE COLUMN status payment_status VARCHAR(20) NOT NULL DEFAULT 'pending'");
        }

        DB::statement("ALTER TABLE payments MODIFY payment_method VARCHAR(50) NULL");
        DB::statement("ALTER TABLE payments MODIFY gateway_response LONGTEXT NULL");

        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'payment_number')) {
                $table->string('payment_number', 30)->nullable()->after('id');
            }
            if (!Schema::hasColumn('payments', 'gateway')) {
                $table->string('gateway', 30)->default('payway')->after('payment_method');
            }
            if (!Schema::hasColumn('payments', 'gateway_transaction_id')) {
                $table->string('gateway_transaction_id', 255)->nullable()->after('transaction_id');
            }
            if (!Schema::hasColumn('payments', 'gateway_reference')) {
                $table->string('gateway_reference', 255)->nullable()->after('gateway_transaction_id');
            }
            if (!Schema::hasColumn('payments', 'currency')) {
                $table->string('currency', 5)->default('USD')->after('amount');
            }
            if (!Schema::hasColumn('payments', 'qr_string')) {
                $table->text('qr_string')->nullable()->after('gateway_response');
            }
            if (!Schema::hasColumn('payments', 'deeplink')) {
                $table->string('deeplink', 500)->nullable()->after('qr_string');
            }
            if (!Schema::hasColumn('payments', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('deeplink');
            }

            if (!Schema::hasIndex('payments', 'payments_payment_number_unique')) {
                $table->unique('payment_number');
            }
            if (!Schema::hasIndex('payments', 'payments_gateway_transaction_id_unique')) {
                $table->unique('gateway_transaction_id');
            }
            if (!Schema::hasIndex('payments', 'payments_order_id_payment_status_index')) {
                $table->index(['order_id', 'payment_status']);
            }
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
        DB::statement("ALTER TABLE payments CHANGE COLUMN payment_status status ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE payments MODIFY payment_method ENUM('cod', 'card', 'bank_transfer', 'online') NOT NULL DEFAULT 'cod'");
    }
};