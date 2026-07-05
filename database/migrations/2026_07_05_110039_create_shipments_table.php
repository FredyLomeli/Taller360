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
        Schema::create('shipments', function (Blueprint $table) {
            $table->id(); // Este será el Folio del Viaje
            $table->string('driver_name')->nullable();
            $table->string('license_plate')->nullable();
            $table->string('destination')->nullable();
            $table->string('status')->default('en_transito'); // 'en_transito', 'entregado', 'cancelado'
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained(); // Almacenista que registró la salida
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
