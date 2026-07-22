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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            // Relación con el padre
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            
            // Atributos físicos del mueble en bodega
            $table->string('measurements'); // Ej: "1.50", "1.20"
            $table->string('material'); // Ej: "OKUME", "MDF"

            $table->integer('stock')->default(0);
            $table->string('sku')->nullable();
            $table->string('stock_notes')->nullable(); // Para anotar excepciones: "1 pieza pintada en Chocolate"
            
            // Precios
            $table->decimal('price_1', 10, 2); // Precio Público (Obligatorio)
            $table->decimal('price_2', 10, 2)->nullable(); 
            $table->decimal('price_3', 10, 2)->nullable(); 
            $table->decimal('price_4', 10, 2)->nullable(); 
            $table->decimal('price_5', 10, 2)->nullable(); 

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
