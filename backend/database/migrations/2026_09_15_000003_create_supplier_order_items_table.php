<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->integer('quantity');
            $table->integer('quantity_received')->default(0);
            $table->decimal('unit_cost', 10, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();

            $table->index('product_id');
            $table->index('supplier_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_order_items');
    }
};