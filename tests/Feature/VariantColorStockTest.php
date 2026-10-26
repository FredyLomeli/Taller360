<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantColorStock;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\WorkOrder;
use App\Models\Shipment;
use App\Models\SaleDelivery;

class VariantColorStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_variant_color_stock_relationships_and_available_stock()
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'material' => 'Melamina',
            'measurements' => '1.80m',
            'stock' => 5,
            'price_1' => 2000,
        ]);

        $blanco = VariantColorStock::create([
            'product_variant_id' => $variant->id,
            'color' => 'Blanco',
            'stock' => 3,
            'damaged_stock' => 1,
            'reserved_stock' => 0,
        ]);

        $gris = VariantColorStock::create([
            'product_variant_id' => $variant->id,
            'color' => 'Gris',
            'stock' => 2,
            'damaged_stock' => 0,
            'reserved_stock' => 0,
        ]);

        $this->assertEquals(2, $blanco->available_stock); // 3 - 1
        $this->assertEquals(2, $gris->available_stock);
        $this->assertCount(2, $variant->fresh()->colorStocks);
    }

    public function test_production_completion_for_sale_detail_increments_specific_color_stock()
    {
        $produccion = User::factory()->create(['role' => 'produccion']);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'material' => 'Melamina',
            'measurements' => '1.80m',
            'stock' => 0,
            'price_1' => 2000,
        ]);

        VariantColorStock::create([
            'product_variant_id' => $variant->id,
            'color' => 'Blanco',
            'stock' => 0,
        ]);

        $client = Client::factory()->create();
        $sale = Sale::factory()->create(['client_id' => $client->id, 'stage' => 'produccion']);
        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Ropero Melamina 1.80m',
            'quantity' => 2,
            'chosen_color' => 'Blanco',
            'unit_price' => 2000,
            'subtotal' => 4000,
        ]);

        $response = $this->actingAs($produccion)->post('/production-plan/complete', [
            'sale_detail_id' => $detail->id,
            'quantity' => 2,
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'stock' => 2,
        ]);

        $this->assertDatabaseHas('variant_color_stocks', [
            'product_variant_id' => $variant->id,
            'color' => 'Blanco',
            'stock' => 2,
        ]);
    }

    public function test_production_completion_for_work_order_increments_specific_color_stock()
    {
        $produccion = User::factory()->create(['role' => 'produccion']);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'material' => 'Melamina',
            'measurements' => '1.80m',
            'stock' => 1,
            'price_1' => 2000,
        ]);

        VariantColorStock::create([
            'product_variant_id' => $variant->id,
            'color' => 'Gris',
            'stock' => 1,
        ]);

        $workOrder = WorkOrder::create([
            'product_variant_id' => $variant->id,
            'quantity_requested' => 3,
            'color' => 'Gris',
            'status' => 'pending',
            'created_by' => $produccion->id,
        ]);

        $response = $this->actingAs($produccion)->post('/production-plan/complete', [
            'work_order_id' => $workOrder->id,
            'quantity' => 2,
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'stock' => 3, // 1 + 2
        ]);

        $this->assertDatabaseHas('variant_color_stocks', [
            'product_variant_id' => $variant->id,
            'color' => 'Gris',
            'stock' => 3, // 1 + 2
        ]);
    }

    public function test_shipment_blocks_when_specific_color_stock_is_insufficient()
    {
        $inventario = User::factory()->create(['role' => 'inventario']);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'material' => 'Melamina',
            'measurements' => '1.80m',
            'stock' => 5, // Hay 5 piezas físicas globales...
            'price_1' => 2000,
        ]);

        // ... pero las 5 son 'Gris', y 0 son 'Blanco'
        VariantColorStock::create([
            'product_variant_id' => $variant->id,
            'color' => 'Gris',
            'stock' => 5,
        ]);
        VariantColorStock::create([
            'product_variant_id' => $variant->id,
            'color' => 'Blanco',
            'stock' => 0,
        ]);

        $client = Client::factory()->create();
        $sale = Sale::factory()->create(['client_id' => $client->id, 'stage' => 'produccion']);
        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Ropero Melamina',
            'quantity' => 1,
            'chosen_color' => 'Blanco', // El cliente compró Blanco
            'unit_price' => 2000,
            'subtotal' => 2000,
        ]);

        $payload = [
            'driver_name' => 'Chofer 1',
            'license_plate' => 'ABC-123',
            'destination' => 'Tepatitlan',
            'pickup_type' => 'flota_propia',
            'items' => [
                [
                    'sale_detail_id' => $detail->id,
                    'quantity' => 1,
                ]
            ]
        ];

        $response = $this->actingAs($inventario)->post('/shipments', $payload);
        $response->assertSessionHasErrors('error');

        // El stock no debió cambiar
        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'stock' => 5,
        ]);
        $this->assertDatabaseHas('variant_color_stocks', [
            'product_variant_id' => $variant->id,
            'color' => 'Blanco',
            'stock' => 0,
        ]);
    }

    public function test_shipment_decrements_specific_color_stock_and_reverts_on_cancel()
    {
        $inventario = User::factory()->create(['role' => 'inventario']);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'material' => 'Melamina',
            'measurements' => '1.80m',
            'stock' => 4,
            'price_1' => 2000,
        ]);

        $blancoStock = VariantColorStock::create([
            'product_variant_id' => $variant->id,
            'color' => 'Blanco',
            'stock' => 4,
        ]);

        $client = Client::factory()->create();
        $sale = Sale::factory()->create(['client_id' => $client->id, 'stage' => 'produccion']);
        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Ropero Melamina',
            'quantity' => 2,
            'chosen_color' => 'Blanco',
            'unit_price' => 2000,
            'subtotal' => 4000,
        ]);

        $payload = [
            'driver_name' => 'Chofer 1',
            'license_plate' => 'ABC-123',
            'destination' => 'Tepatitlan',
            'pickup_type' => 'flota_propia',
            'items' => [
                [
                    'sale_detail_id' => $detail->id,
                    'quantity' => 2,
                ]
            ]
        ];

        // 1. Embarcar
        $response = $this->actingAs($inventario)->post('/shipments', $payload);
        $response->assertRedirect('/shipments');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'stock' => 2, // 4 - 2
        ]);
        $this->assertDatabaseHas('variant_color_stocks', [
            'product_variant_id' => $variant->id,
            'color' => 'Blanco',
            'stock' => 2, // 4 - 2
        ]);

        $shipment = Shipment::first();

        // 2. Cancelar embarque -> debe regresar el stock tanto a variant como al color específico
        $cancelResponse = $this->actingAs($inventario)->patch("/shipments/{$shipment->id}/cancel");
        $cancelResponse->assertSessionHas('success');

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'stock' => 4,
        ]);
        $this->assertDatabaseHas('variant_color_stocks', [
            'product_variant_id' => $variant->id,
            'color' => 'Blanco',
            'stock' => 4,
        ]);
    }

    public function test_product_store_and_update_with_color_stocks_syncs_correctly()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = \App\Models\Category::create(['name' => 'Roperos']);

        // 1. Crear producto con variantes y desglose de color
        $createPayload = [
            'name' => 'Ropero Viena Melamina',
            'category_id' => $category->id,
            'description' => 'Ropero moderno',
            'is_favorite' => false,
            'variants' => [
                [
                    'material' => 'Melamina',
                    'measurements' => '2.00x1.00m',
                    'sku' => 'ROP-VIE-MEL',
                    'stock' => 0,
                    'price_1' => 3500,
                    'color_stocks' => [
                        ['color' => 'Blanco', 'stock' => 3],
                        ['color' => 'Gris', 'stock' => 2],
                    ],
                ]
            ]
        ];

        $response = $this->actingAs($admin)->post('/products', $createPayload);
        $response->assertRedirect('/products');

        $variant = ProductVariant::where('sku', 'ROP-VIE-MEL')->first();
        $this->assertNotNull($variant);
        $this->assertEquals(5, $variant->stock); // 3 + 2

        $this->assertDatabaseHas('variant_color_stocks', [
            'product_variant_id' => $variant->id,
            'color' => 'Blanco',
            'stock' => 3,
        ]);
        $this->assertDatabaseHas('variant_color_stocks', [
            'product_variant_id' => $variant->id,
            'color' => 'Gris',
            'stock' => 2,
        ]);

        // 2. Actualizar producto modificando stock de colores
        $product = $variant->product;
        $updatePayload = [
            'name' => 'Ropero Viena Melamina Pro',
            'category_id' => $category->id,
            'description' => 'Ropero moderno actualizado',
            'is_favorite' => false,
            'variants' => [
                [
                    'id' => $variant->id,
                    'material' => 'Melamina',
                    'measurements' => '2.00x1.00m',
                    'sku' => 'ROP-VIE-MEL',
                    'stock' => 0,
                    'price_1' => 3800,
                    'color_stocks' => [
                        ['color' => 'Blanco', 'stock' => 4], // 3 -> 4
                        ['color' => 'Oyamel', 'stock' => 1], // nuevo
                    ],
                ]
            ]
        ];

        $updateResponse = $this->actingAs($admin)->put("/products/{$product->id}", $updatePayload);
        $updateResponse->assertRedirect('/products');

        $variant->refresh();
        $this->assertEquals(5, $variant->stock); // 4 + 1

        $this->assertDatabaseHas('variant_color_stocks', [
            'product_variant_id' => $variant->id,
            'color' => 'Blanco',
            'stock' => 4,
        ]);
        $this->assertDatabaseHas('variant_color_stocks', [
            'product_variant_id' => $variant->id,
            'color' => 'Oyamel',
            'stock' => 1,
        ]);
        // Gris debe haber sido eliminado al no venir en la actualización
        $this->assertDatabaseMissing('variant_color_stocks', [
            'product_variant_id' => $variant->id,
            'color' => 'Gris',
        ]);
    }

    public function test_production_queue_calculates_pending_by_color_for_melamina()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create(['name' => 'MUEBLE MELAMINA']);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'material' => 'Melamina',
            'measurements' => '1.50m',
            'stock' => 6,
            'price_1' => 5000,
        ]);

        VariantColorStock::create(['product_variant_id' => $variant->id, 'color' => 'Blanco', 'stock' => 2]);
        VariantColorStock::create(['product_variant_id' => $variant->id, 'color' => 'Gris', 'stock' => 1]);
        VariantColorStock::create(['product_variant_id' => $variant->id, 'color' => 'Texturizado', 'stock' => 3]);
        VariantColorStock::create(['product_variant_id' => $variant->id, 'color' => 'Oyamel', 'stock' => 0]);

        // Cliente pide 1 Oyamel (stock es 0 aunque el total sea 6)
        $client = Client::factory()->create();
        $sale = Sale::create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'total' => 5000,
            'subtotal' => 5000,
            'status' => 'aprobado',
            'stage' => 'produccion',
            'promised_date' => now()->addDays(2),
        ]);

        SaleDetail::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'MUEBLE MELAMINA',
            'quantity' => 1,
            'unit_price' => 5000,
            'subtotal' => 5000,
            'chosen_color' => 'Oyamel',
            'production_hold' => false,
        ]);

        $response = $this->actingAs($admin)->get('/production');
        $response->assertStatus(200);

        $response->assertInertia(function (\Inertia\Testing\AssertableInertia $page) use ($variant) {
            $page->has('productionQueue.' . $variant->id, function (\Inertia\Testing\AssertableInertia $group) {
                $group->where('pending_to_fabricate', 1)
                      ->has('color_breakdown', 4)
                      ->where('color_breakdown.3.color', 'Oyamel')
                      ->where('color_breakdown.3.pending_to_fabricate', 1)
                      ->etc();
            });
        });
    }
}
