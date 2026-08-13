<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_completions', function (Blueprint $table) {
            $table->foreignId('work_order_id')->nullable()->after('id')->constrained('work_orders');
            $table->unsignedBigInteger('sale_detail_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('production_completions', function (Blueprint $table) {
            $table->dropForeign(['work_order_id']);
            $table->dropColumn('work_order_id');
            $table->unsignedBigInteger('sale_detail_id')->nullable(false)->change();
        });
    }
};
