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
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('damaged_stock')->default(0)->after('reserved_stock');
        });

        Schema::create('damaged_stock_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('type'); // 'marcar_danada', 'reparada', 'baja_definitiva'
            $table->unsignedInteger('quantity');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('damaged_stock_logs');

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('damaged_stock');
        });
    }
};
