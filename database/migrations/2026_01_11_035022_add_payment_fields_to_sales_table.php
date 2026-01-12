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
        Schema::table('sales', function (Blueprint $table) {
            // Agregamos columna para saber cuánto pagó (para abonos)
            $table->decimal('paid_amount', 10, 2)->after('total')->default(0);
            // Columna para cambio (opcional, pero útil para historial)
            $table->decimal('change_amount', 10, 2)->after('paid_amount')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            //
        });
    }
};
