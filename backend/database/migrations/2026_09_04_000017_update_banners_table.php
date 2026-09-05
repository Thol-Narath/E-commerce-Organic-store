<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->string('discount_label')->nullable()->after('subtitle');
            $table->string('cta_text')->default('Shop Now')->after('link_url');
            $table->string('cta_link')->nullable()->after('cta_text');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn(['discount_label', 'cta_text', 'cta_link']);
        });
    }
};
