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
        Schema::create('variant_color_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->string('color');
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('damaged_stock')->default(0);
            $table->unsignedInteger('reserved_stock')->default(0);
            $table->timestamps();

            $table->unique(['product_variant_id', 'color']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variant_color_stocks');
    }
};
