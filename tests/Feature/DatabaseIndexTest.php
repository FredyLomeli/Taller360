<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_tables_have_performance_indexes()
    {
        $this->assertTrue(Schema::hasIndex('sales', 'idx_sales_stage_promised_date'), 'La tabla sales debe tener el índice idx_sales_stage_promised_date.');
        $this->assertTrue(Schema::hasIndex('sales', 'idx_sales_created_at'), 'La tabla sales debe tener el índice idx_sales_created_at.');
        $this->assertTrue(Schema::hasIndex('shipments', 'idx_shipments_status'), 'La tabla shipments debe tener el índice idx_shipments_status.');
        $this->assertTrue(Schema::hasIndex('work_orders', 'idx_work_orders_status'), 'La tabla work_orders debe tener el índice idx_work_orders_status.');
        $this->assertTrue(Schema::hasIndex('sale_details', 'idx_sale_details_hold_sale'), 'La tabla sale_details debe tener el índice idx_sale_details_hold_sale.');
        $this->assertTrue(Schema::hasIndex('variant_color_stocks', 'idx_variant_color_stocks_stock'), 'La tabla variant_color_stocks debe tener el índice idx_variant_color_stocks_stock.');
        $this->assertTrue(Schema::hasIndex('sale_return_items', 'idx_return_items_detail_shipment'), 'La tabla sale_return_items debe tener el índice idx_return_items_detail_shipment.');
    }
}
