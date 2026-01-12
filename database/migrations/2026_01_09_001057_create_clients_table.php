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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            
            // Datos de Identificación
            $table->string('name'); // Nombre corto o de pila (para mostrar en el POS)
            $table->string('business_name')->nullable(); // Razón Social (para facturas)
            $table->integer('price_tier')->default(1); // Tipo de Cliente (1 al 5 para definir precios)
            
            // Datos de Contacto
            $table->string('email')->nullable();
            $table->string('phones')->nullable(); // Plural: "3312345678, 3387654321"
            
            // Dirección Completa
            $table->string('street_address')->nullable(); // Calle con número
            $table->string('neighborhood')->nullable();   // Colonia
            $table->string('city')->nullable();           // Ciudad
            $table->string('state')->nullable();          // Estado
            $table->string('delegation')->nullable();     // Delegación / Municipio
            $table->string('zip_code')->nullable();       // Código Postal
            
            // Referencias (Guardaremos texto largo para flexibilidad: "Juan Perez: 333... | Maria: 331...")
            $table->text('references')->nullable(); 

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
