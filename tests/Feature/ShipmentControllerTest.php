<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Shipment;
use App\Models\SaleDelivery;

class ShipmentControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_shipment_decreases_stock_and_creates_delivery()
    {
        $inventario = User::factory()->create(['role' => 'inventario']);
        $client = Client::factory()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 50, 'price_1' => 100, 'material' => 'Tela', 'measurements' => '2x2']);
        
        $sale = Sale::factory()->create(['client_id' => $client->id, 'stage' => 'produccion']);
        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'product_name' => 'Sillon Tela 2x2',
            'unit_price' => 100,
            'subtotal' => 1000
        ]);

        $payload = [
            'driver_name' => 'Juan Perez',
            'license_plate' => 'ABC-123',
            'destination' => 'Sucursal 1',
            'pickup_type' => 'flota_propia',
            'items' => [
                [
                    'sale_detail_id' => $detail->id,
                    'quantity' => 5
                ]
            ]
        ];

        $response = $this->actingAs($inventario)->post('/shipments', $payload);
        $response->assertRedirect('/shipments');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'stock' => 45 // 50 - 5
        ]);

        $this->assertDatabaseHas('shipments', [
            'driver_name' => 'Juan Perez',
            'status' => 'en_transito'
        ]);
        
        $shipment = Shipment::where('driver_name', 'Juan Perez')->first();

        $this->assertDatabaseHas('sale_deliveries', [
            'shipment_id' => $shipment->id,
            'sale_detail_id' => $detail->id,
            'quantity_delivered' => 5
        ]);
    }

    public function test_cancel_shipment_returns_stock_and_changes_status()
    {
        $inventario = User::factory()->create(['role' => 'inventario']);
        $variant = ProductVariant::factory()->create(['stock' => 45, 'material' => 'Tela', 'measurements' => '1x1']);
        $sale = Sale::factory()->create(['stage' => 'produccion']);
        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'product_name' => 'Item',
            'unit_price' => 10,
            'subtotal' => 100
        ]);
        
        $shipment = Shipment::create(['status' => 'en_transito', 'pickup_type' => 'flota_propia', 'driver_name' => 'D', 'license_plate' => 'L', 'destination' => 'D', 'user_id' => $inventario->id]);
        SaleDelivery::create([
            'shipment_id' => $shipment->id,
            'sale_detail_id' => $detail->id,
            'quantity_delivered' => 5
        ]);

        $response = $this->actingAs($inventario)->patch("/shipments/{$shipment->id}/cancel");
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('shipments', [
            'id' => $shipment->id,
            'status' => 'cancelado'
        ]);

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'stock' => 50 // 45 + 5 returned
        ]);
    }

    public function test_cannot_cancel_flota_propia_shipment_if_entregado()
    {
        $inventario = User::factory()->create(['role' => 'inventario']);
        $variant = ProductVariant::factory()->create(['stock' => 45, 'material' => 'Tela', 'measurements' => '1x1']);
        $sale = Sale::factory()->create(['stage' => 'produccion']);
        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'product_name' => 'Item',
            'unit_price' => 10,
            'subtotal' => 100
        ]);
        
        $shipment = Shipment::create(['status' => 'entregado', 'pickup_type' => 'flota_propia', 'driver_name' => 'D', 'license_plate' => 'L', 'destination' => 'D', 'user_id' => $inventario->id]);
        SaleDelivery::create([
            'shipment_id' => $shipment->id,
            'sale_detail_id' => $detail->id,
            'quantity_delivered' => 5
        ]);

        $response = $this->actingAs($inventario)->patch("/shipments/{$shipment->id}/cancel");
        
        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('shipments', [
            'id' => $shipment->id,
            'status' => 'entregado'
        ]);
        
        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'stock' => 45 // Not returned
        ]);
    }
    public function test_cancel_shipment_reverts_entregado_to_produccion_on_partial_delivery()
    {
        $inventario = User::factory()->create(['role' => 'inventario']);
        $variant = ProductVariant::factory()->create(['stock' => 40, 'material' => 'Madera', 'measurements' => '2x2']);
        
        // Creamos una venta que ya fue "entregada"
        $sale = Sale::factory()->create(['stage' => 'entregado']);
        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'product_name' => 'Silla Madera',
            'unit_price' => 10,
            'subtotal' => 100
        ]);
        
        // Este embarque había entregado el 100% de la venta
        $shipment = Shipment::create(['status' => 'entregado', 'pickup_type' => 'recoleccion_cliente', 'driver_name' => 'D', 'license_plate' => 'L', 'destination' => 'D', 'user_id' => $inventario->id]);
        SaleDelivery::create([
            'shipment_id' => $shipment->id,
            'sale_detail_id' => $detail->id,
            'quantity_delivered' => 10
        ]);

        // Cancelamos el embarque
        $response = $this->actingAs($inventario)->patch("/shipments/{$shipment->id}/cancel");
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('shipments', [
            'id' => $shipment->id,
            'status' => 'cancelado'
        ]);

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'stock' => 50 // 40 + 10 regresadas
        ]);

        // Aseguramos que el estado volvió a 'produccion' porque hay 10 piezas faltantes
        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'stage' => 'produccion'
        ]);
    }
}
    public function test_store_shipment_decreases_reserved_stock()
    {
        $inventario = User::factory()->create(['role' => 'inventario']);
        $client = Client::factory()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id, 
            'stock' => 50, 
            'reserved_stock' => 10,
            'price_1' => 100, 
            'material' => 'Tela', 
            'measurements' => '2x2'
        ]);
        
        $sale = Sale::factory()->create(['client_id' => $client->id, 'stage' => 'produccion']);
        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Test',
            'quantity' => 15,
            'unit_price' => 100,
            'subtotal' => 1500
        ]);

        \App\Models\DetalladoRecord::create([
            'sale_detail_id' => $detail->id,
            'quantity' => 5,
            'user_id' => $inventario->id
        ]);

        $response = $this->actingAs($inventario)->post('/shipments', [
            'driver_name' => 'Juan',
            'license_plate' => 'ABC-123',
            'destination' => 'Test',
            'pickup_type' => 'flota_propia',
            'items' => [
                ['sale_detail_id' => $detail->id, 'quantity' => 10]
            ]
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/shipments');
        
        $this->assertEquals(40, $variant->fresh()->stock);
        $this->assertEquals(5, $variant->fresh()->reserved_stock);
}
