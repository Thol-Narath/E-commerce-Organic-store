<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow users created via Google OAuth to have no local password and
     * store their Google subject identifier for repeat logins.
     *
     * Uses raw SQL (rather than Schema::table ->change()) to avoid requiring
     * doctrine/dbal for column modification on Laravel 10.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
        });

        DB::statement('ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NULL');
    }

    /**
     * Reverse the changes.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NOT NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn('google_id');
        });
    }
};
