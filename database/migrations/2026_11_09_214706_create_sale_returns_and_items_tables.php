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
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 50)->unique();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('authorized_by');
            $table->dateTime('returned_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained('sale_returns')->cascadeOnDelete();
            $table->foreignId('sale_detail_id')->constrained('sale_details');
            $table->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('reason_type', 50);
            $table->string('disposition', 30); // 'danada', 'baja', 'color_equivocado'
            $table->foreignId('color_id')->nullable()->constrained('colors')->nullOnDelete();
            $table->string('chosen_color', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
    }
};
