<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the icon column to the categories table.
     *
     * The column may already exist via the base create_categories_table
     * migration in existing installs, so this migration is guarded to be
     * safe when run against an already-migrated database.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('categories', 'icon')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->string('icon', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('categories', 'icon')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('icon');
            });
        }
    }
};
