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
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('store_id')->nullable()->index();
            $table->string('store_name')->nullable();
            $table->string('store_slug')->nullable()->index();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->string('origin')->nullable()->index();
            $table->string('roast_level')->nullable()->index();
            $table->string('process')->nullable()->index();
            $table->json('flavor_notes')->nullable();
            $table->decimal('price_min', 12, 2)->default(0)->index();
            $table->decimal('price_max', 12, 2)->default(0)->index();
            $table->string('image_url')->nullable();
            $table->boolean('is_available')->default(true)->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
    }
};
