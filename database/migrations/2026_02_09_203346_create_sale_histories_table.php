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
        Schema::create('sale_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->onDelete('cascade'); // El pedido
            $table->foreignId('user_id')->constrained(); // El usuario que hizo el cambio
            
            $table->string('from_stage')->nullable(); // Estado anterior
            $table->string('to_stage'); // Nuevo estado
            
            $table->text('notes')->nullable(); // Notas opcionales (ej. "Autorizado por Gerencia")
            $table->timestamps(); // created_at será la fecha del cambio
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_histories');
    }
};
