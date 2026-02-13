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
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            
            // Relación con la Venta
            $table->foreignId('sale_id')->constrained()->onDelete('cascade');
            
            // Quién registró el cobro (Auditoría)
            $table->foreignId('user_id')->constrained();
            
            $table->decimal('amount', 10, 2); // Cuánto pagó
            $table->string('payment_method'); // Efectivo, Transferencia, Tarjeta
            $table->string('reference')->nullable(); // Num. Autorización o Nota
            
            $table->timestamp('paid_at'); // Fecha real del pago
            $table->timestamps(); // Fecha de registro en sistema
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
