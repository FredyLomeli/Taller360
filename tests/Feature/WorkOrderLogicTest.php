<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SaleDetail;
use App\Models\Sale;
use App\Models\Client;
use App\Models\WorkOrder;

class WorkOrderLogicTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_work_order()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $response = $this->actingAs($user)->post(route('work-orders.store'), [
            'product_variant_id' => $variant->id,
            'quantity_requested' => 10,
            'notes' => 'Test notes',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('work_orders', [
            'quantity_requested' => 10,
            'status' => 'pending',
            'notes' => 'Test notes'
        ]);
    }

    public function test_can_release_production_hold()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $client = Client::factory()->create();
        $sale = Sale::factory()->create(['client_id' => $client->id, 'user_id' => $user->id]);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        
        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Test',
            'quantity' => 10,
            'unit_price' => 100,
            'subtotal' => 1000,
            'discount_percent' => 0,
            'production_hold' => true,
        ]);

        $response = $this->actingAs($user)->patch(route('sale-details.release-hold', $detail->id), [
            'new_date' => '2026-12-01'
        ]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertFalse((bool)$detail->fresh()->production_hold);
    }

    public function test_partial_shipment_triggers_production_hold()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $client = Client::factory()->create();
        $sale = Sale::factory()->create(['client_id' => $client->id, 'user_id' => $user->id, 'stage' => 'produccion']);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 10]);
        
        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Test',
            'quantity' => 10,
            'unit_price' => 100,
            'subtotal' => 1000,
            'discount_percent' => 0,
            'production_hold' => false,
        ]);

        \Illuminate\Support\Facades\DB::table('settings')->insert(['key' => 'allow_negative_stock', 'value' => '1', 'created_at' => now(), 'updated_at' => now()]);

        $response = $this->actingAs($user)->post(route('shipments.store'), [
            'driver_name' => 'Test Driver',
            'license_plate' => 'ABC-123',
            'destination' => 'Test Destination',
            'pickup_type' => 'flota_propia',
            'items' => [
                [
                    'sale_detail_id' => $detail->id,
                    'quantity' => 5,
                ]
            ]
        ]);
        
        $response->assertSessionHasNoErrors();
        $this->assertTrue((bool)$detail->fresh()->production_hold);
    }

    public function test_production_completion_closes_work_order()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $workOrder = WorkOrder::create([
            'product_variant_id' => $variant->id,
            'quantity_requested' => 10,
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->post(route('production.complete'), [
            'work_order_id' => $workOrder->id,
            'quantity' => 10,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertEquals('completed', $workOrder->fresh()->status);
    }
}
