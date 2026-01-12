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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained(); // Vendedor (Usuario logueado)
            $table->foreignId('client_id')->nullable()->constrained(); // Cliente (Puede ser nulo si es venta rápida)
            
            $table->decimal('total', 10, 2);
            $table->string('payment_method')->default('Efectivo'); // Efectivo, Tarjeta, Transferencia
            $table->string('status')->default('pagado'); // pagado, pendiente, cancelado
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
