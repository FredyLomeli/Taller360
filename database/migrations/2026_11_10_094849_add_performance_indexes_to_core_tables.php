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
            $table->index(['stage', 'promised_date'], 'idx_sales_stage_promised_date');
            $table->index('created_at', 'idx_sales_created_at');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->index('status', 'idx_shipments_status');
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->index('status', 'idx_work_orders_status');
        });

        Schema::table('sale_details', function (Blueprint $table) {
            $table->index(['production_hold', 'sale_id'], 'idx_sale_details_hold_sale');
        });

        Schema::table('variant_color_stocks', function (Blueprint $table) {
            $table->index('stock', 'idx_variant_color_stocks_stock');
        });

        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->index(['sale_detail_id', 'shipment_id'], 'idx_return_items_detail_shipment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_return_items', function (Blueprint $table) {
            $table->dropIndex('idx_return_items_detail_shipment');
        });

        Schema::table('variant_color_stocks', function (Blueprint $table) {
            $table->dropIndex('idx_variant_color_stocks_stock');
        });

        Schema::table('sale_details', function (Blueprint $table) {
            $table->dropIndex('idx_sale_details_hold_sale');
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropIndex('idx_work_orders_status');
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropIndex('idx_shipments_status');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('idx_sales_stage_promised_date');
            $table->dropIndex('idx_sales_created_at');
        });
    }
};
