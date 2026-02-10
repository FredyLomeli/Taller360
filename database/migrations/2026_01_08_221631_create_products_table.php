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
    Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained(); // Ej: Roperos
            $table->string('name'); // Ej: "Modelo California"
            $table->string('measurements')->nullable(); // Ej: "180x120"
            $table->text('description')->nullable(); // Detalles generales del diseño
            $table->string('image')->nullable(); // Imagen principal del modelo
            $table->boolean('is_favorite')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
