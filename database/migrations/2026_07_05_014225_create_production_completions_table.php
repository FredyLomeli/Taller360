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
        Schema::create('production_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_detail_id')->constrained()->onDelete('cascade');
            $table->integer('quantity_completed');
            $table->foreignId('user_id')->constrained(); // Registra quién reportó el avance
            $table->timestamp('completed_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_completions');
    }
};
