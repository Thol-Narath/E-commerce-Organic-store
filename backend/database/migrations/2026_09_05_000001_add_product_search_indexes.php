<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index-backed storefront search (Phase 11 — performance).
 *
 * Adds a MySQL FULLTEXT index over the product search columns so the catalog
 * search can run MATCH ... AGAINST instead of a full-table `LIKE '%term%'`
 * scan. The existing `sku` UNIQUE index handles exact SKU lookups, and the
 * `is_featured` index covers the featured filter used by the home page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->fullText(['name', 'short_description', 'description']);
            $table->index('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropFullText(['name', 'short_description', 'description']);
            $table->dropIndex(['is_featured']);
        });
    }
};