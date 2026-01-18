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
        Schema::create('sale_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->onDelete('cascade');
            
            // Relación con la variante específica (MDF Chocolate)
            $table->foreignId('product_variant_id')->constrained();
            
            // Guardamos datos históricos (Snapshot)
            // Guardamos el nombre del producto por si luego lo borran del catálogo
            $table->string('product_name'); 
            $table->integer('quantity');
            $table->integer('discount_percent')->default(0);
            $table->decimal('unit_price', 10, 2);
            $table->decimal('subtotal', 10, 2);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_details');
    }
};
