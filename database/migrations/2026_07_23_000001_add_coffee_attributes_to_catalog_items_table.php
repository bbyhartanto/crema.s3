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
            if (! Schema::hasColumn('catalog_items', 'coffee_attributes')) {
                $table->json('coffee_attributes')->nullable()->after('flavor_notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            if (Schema::hasColumn('catalog_items', 'coffee_attributes')) {
                $table->dropColumn('coffee_attributes');
            }
        });
    }
};
