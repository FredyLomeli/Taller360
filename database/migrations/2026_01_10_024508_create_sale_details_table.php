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
            $table->foreignId('product_variant_id')->constrained();
    
            $table->string('product_name'); // Snapshot del nombre
            $table->integer('quantity');
            // Color elegido al momento de la venta
            $table->string('chosen_color')->nullable();

            // Adicionales (ej. "Espejo extra") y su costo
            $table->text('custom_notes')->nullable(); 
            $table->decimal('additional_cost', 10, 2)->default(0);

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
