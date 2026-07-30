<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            if (! Schema::hasColumn('catalog_items', 'store_theme_config')) {
                $table->json('store_theme_config')->nullable()->after('store_slug');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            if (Schema::hasColumn('catalog_items', 'store_theme_config')) {
                $table->dropColumn('store_theme_config');
            }
        });
    }
};
