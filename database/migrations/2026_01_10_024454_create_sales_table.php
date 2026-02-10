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
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('change_amount', 10, 2)->default(0);
            $table->string('payment_method')->default('Efectivo'); // Efectivo, Tarjeta, Transferencia
            // NUEVO: FLUJO DE ESTADOS
            // pedido: Borrador / Cotización
            // confirmado: Cliente aprobó (Anticipo)
            // produccion: En taller
            // enviado: Salió a ruta
            // entregado: Cliente recibió (Final)
            // cancelado: Se cayó la venta
            $table->enum('stage', ['pedido', 'confirmado', 'produccion', 'enviado', 'entregado', 'cancelado'])->default('pedido');
        
            $table->date('promised_date')->nullable(); // Fecha promesa de entrega
            $table->boolean('is_partial_shipping')->default(false); // Si se entregó una parte
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
