<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\WorkOrder;
use App\Models\SaleDetail;
use App\Models\ProductionCompletion;
use App\Models\ProductVariant;
use App\Models\Product;
use App\Models\User;
use App\Models\Sale;
use App\Models\Client;

class WorkOrderDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_order_can_be_created_and_saved()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $workOrder = WorkOrder::create([
            'product_variant_id' => $variant->id,
            'quantity_requested' => 10,
            'target_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => 'pending',
            'notes' => 'Test notes',
            'created_by' => $user->id,
        ]);

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'quantity_requested' => 10,
            'status' => 'pending',
        ]);
    }

    public function test_production_hold_can_be_set_on_sale_detail()
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $sale = Sale::factory()->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
        ]);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $saleDetail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Test Product',
            'quantity' => 5,
            'unit_price' => 100,
            'subtotal' => 500,
            'discount_percent' => 0,
            'production_hold' => true,
        ]);

        $this->assertDatabaseHas('sale_details', [
            'id' => $saleDetail->id,
            'production_hold' => 1,
        ]);

        $this->assertTrue($saleDetail->fresh()->production_hold);
    }

    public function test_production_completion_can_be_linked_to_work_order()
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $workOrder = WorkOrder::create([
            'product_variant_id' => $variant->id,
            'quantity_requested' => 10,
            'created_by' => $user->id,
        ]);

        $completion = ProductionCompletion::create([
            'work_order_id' => $workOrder->id,
            'quantity_completed' => 5,
            'user_id' => $user->id,
            'completed_at' => now(),
        ]);

        $this->assertDatabaseHas('production_completions', [
            'id' => $completion->id,
            'work_order_id' => $workOrder->id,
            'sale_detail_id' => null,
        ]);
    }
}
