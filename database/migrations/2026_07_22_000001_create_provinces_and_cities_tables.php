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
        Schema::create('provinces', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('name');
            $table->string('code')->unique();
            $table->smallInteger('sort_order')->default(0);
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedSmallInteger('province_id');
            $table->string('name');
            $table->string('type')->default('city');
            $table->integer('sort_order')->default(0);

            $table->foreign('province_id')->references('id')->on('provinces')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cities');
        Schema::dropIfExists('provinces');
    }
};
