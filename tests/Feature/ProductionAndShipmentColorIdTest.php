<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Database\Seeders\CatalogSeeder;
use App\Models\User;
use App\Models\Category;
use App\Models\Material;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantColorStock;
use App\Models\WorkOrder;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Client;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;

class ProductionAndShipmentColorIdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Category::firstOrCreate(['name' => 'Roperos']);
        Category::firstOrCreate(['name' => 'Trincheros']);
        Category::firstOrCreate(['name' => 'Cómodas y Lokers']);
        Category::firstOrCreate(['name' => 'Recámaras y comedores']);
        Category::firstOrCreate(['name' => 'Bases']);
        $this->seed(CatalogSeeder::class);
    }

    private function createMelamineVariant(int $stock = 0): ProductVariant
    {
        $roperos = Category::where('name', 'Roperos')->first();
        $melamina = Material::where('name', 'Melamina')->first();

        $product = Product::create([
            'name' => 'Ropero Premier Test',
            'category_id' => $roperos->id,
            'description' => 'Test',
            'is_favorite' => false,
        ]);

        return ProductVariant::create([
            'product_id' => $product->id,
            'material' => $melamina->name,
            'material_id' => $melamina->id,
            'measurements' => '120x60x180',
            'stock' => $stock,
            'reserved_stock' => 0,
            'damaged_stock' => 0,
            'price_1' => 5000,
            'price_2' => 4500,
            'price_3' => 4000,
            'price_4' => 3800,
            'price_5' => 3500,
        ]);
    }

    public function test_work_order_creation_stores_color_id_and_color_name_for_melamine(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $variant = $this->createMelamineVariant();
        $fresno = Color::where('name', 'Fresno Andino')->first();

        $response = $this->actingAs($admin)->post(route('work-orders.store'), [
            'product_variant_id' => $variant->id,
            'quantity_requested' => 5,
            'color_id' => $fresno->id,
            'notes' => 'Lote para stock general',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('work_orders', [
            'product_variant_id' => $variant->id,
            'quantity_requested' => 5,
            'color_id' => $fresno->id,
            'color' => 'Fresno Andino',
            'status' => 'pending',
        ]);
    }

    public function test_work_order_creation_requires_color_for_melamine(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $variant = $this->createMelamineVariant();

        $response = $this->actingAs($admin)->post(route('work-orders.store'), [
            'product_variant_id' => $variant->id,
            'quantity_requested' => 5,
            // Sin color_id ni color
        ]);

        $response->assertSessionHasErrors(['color']);
    }

    public function test_work_order_creation_rejects_discontinued_color_for_material_rule_a6(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $variant = $this->createMelamineVariant();
        $parota = Color::where('name', 'Parota')->first();
        $melamina = Material::where('name', 'Melamina')->first();

        // Descontinuamos Parota para Melamina
        DB::table('material_color')
            ->where('material_id', $melamina->id)
            ->where('color_id', $parota->id)
            ->update(['activo' => false]);

        $response = $this->actingAs($admin)->post(route('work-orders.store'), [
            'product_variant_id' => $variant->id,
            'quantity_requested' => 3,
            'color_id' => $parota->id,
        ]);

        $response->assertSessionHasErrors(['color_id']);
        $this->assertDatabaseMissing('work_orders', [
            'product_variant_id' => $variant->id,
            'color_id' => $parota->id,
        ]);
    }

    public function test_production_completion_for_work_order_increments_variant_color_stock_with_color_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $variant = $this->createMelamineVariant(stock: 0);
        $fresno = Color::where('name', 'Fresno Andino')->first();

        $wo = WorkOrder::create([
            'product_variant_id' => $variant->id,
            'quantity_requested' => 4,
            'color_id' => $fresno->id,
            'color' => $fresno->name,
            'status' => 'pending',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('production.complete'), [
            'work_order_id' => $wo->id,
            'quantity' => 4,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Verifica incremento en variante general
        $this->assertEquals(4, $variant->fresh()->stock);

        // Verifica existencia en variant_color_stocks con color_id y color
        $this->assertDatabaseHas('variant_color_stocks', [
            'product_variant_id' => $variant->id,
            'color_id' => $fresno->id,
            'color' => 'Fresno Andino',
            'stock' => 4,
        ]);

        // La orden debe marcarse completada
        $this->assertEquals('completed', $wo->fresh()->status);
    }

    public function test_production_completion_for_sale_detail_increments_variant_color_stock_with_color_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $variant = $this->createMelamineVariant(stock: 0);
        $fresno = Color::where('name', 'Fresno Andino')->first();
        $client = Client::factory()->create();

        $sale = Sale::create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'stage' => 'produccion',
            'total' => 5000,
        ]);

        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Ropero Test',
            'quantity' => 2,
            'color_id' => $fresno->id,
            'chosen_color' => $fresno->name,
            'unit_price' => 2500,
            'subtotal' => 5000,
        ]);

        $response = $this->actingAs($admin)->post(route('production.complete'), [
            'sale_detail_id' => $detail->id,
            'quantity' => 2,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals(2, $variant->fresh()->stock);
        $this->assertDatabaseHas('variant_color_stocks', [
            'product_variant_id' => $variant->id,
            'color_id' => $fresno->id,
            'color' => 'Fresno Andino',
            'stock' => 2,
        ]);
    }

    public function test_shipment_creation_deducts_variant_color_stock_by_color_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $variant = $this->createMelamineVariant(stock: 10);
        $fresno = Color::where('name', 'Fresno Andino')->first();
        $client = Client::factory()->create();

        VariantColorStock::create([
            'product_variant_id' => $variant->id,
            'color_id' => $fresno->id,
            'color' => $fresno->name,
            'stock' => 10,
        ]);

        $sale = Sale::create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'stage' => 'confirmado',
            'total' => 5000,
        ]);

        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Ropero Test',
            'quantity' => 3,
            'color_id' => $fresno->id,
            'chosen_color' => $fresno->name,
            'unit_price' => 2500,
            'subtotal' => 5000,
        ]);

        $response = $this->actingAs($admin)->post(route('shipments.store'), [
            'driver_name' => 'Juan Pérez',
            'license_plate' => 'ABC-123',
            'destination' => 'Sucursal Centro',
            'pickup_type' => 'flota_propia',
            'items' => [
                [
                    'sale_detail_id' => $detail->id,
                    'quantity' => 3,
                ]
            ],
        ]);

        $response->assertRedirect(route('shipments.index'));
        $response->assertSessionHas('success');

        // Stock general descontado
        $this->assertEquals(7, $variant->fresh()->stock);

        // Stock por color descontado atómicamente por color_id
        $this->assertDatabaseHas('variant_color_stocks', [
            'product_variant_id' => $variant->id,
            'color_id' => $fresno->id,
            'stock' => 7,
        ]);
    }

    public function test_shipment_creation_allows_discontinued_color_if_physical_stock_exists_rule_a6(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $variant = $this->createMelamineVariant(stock: 5);
        $parota = Color::where('name', 'Parota')->first();
        $melamina = Material::where('name', 'Melamina')->first();
        $client = Client::factory()->create();

        // Descontinuar Parota
        DB::table('material_color')
            ->where('material_id', $melamina->id)
            ->where('color_id', $parota->id)
            ->update(['activo' => false]);

        // Pero hay 5 en bodega
        VariantColorStock::create([
            'product_variant_id' => $variant->id,
            'color_id' => $parota->id,
            'color' => $parota->name,
            'stock' => 5,
        ]);

        $sale = Sale::create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'stage' => 'confirmado',
            'total' => 2500,
        ]);

        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Ropero Test Parota',
            'quantity' => 2,
            'color_id' => $parota->id,
            'chosen_color' => $parota->name,
            'unit_price' => 2500,
            'subtotal' => 5000,
        ]);

        // Se permite embarcar porque hay stock físico remanente
        $response = $this->actingAs($admin)->post(route('shipments.store'), [
            'driver_name' => 'Juan Pérez',
            'license_plate' => 'ABC-123',
            'destination' => 'Sucursal Sur',
            'pickup_type' => 'flota_propia',
            'items' => [
                [
                    'sale_detail_id' => $detail->id,
                    'quantity' => 2,
                ]
            ],
        ]);

        $response->assertRedirect(route('shipments.index'));
        $this->assertDatabaseHas('variant_color_stocks', [
            'product_variant_id' => $variant->id,
            'color_id' => $parota->id,
            'stock' => 3,
        ]);
    }

    public function test_shipment_cancellation_restores_variant_color_stock_with_color_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $variant = $this->createMelamineVariant(stock: 10);
        $fresno = Color::where('name', 'Fresno Andino')->first();
        $client = Client::factory()->create();

        $colorStock = VariantColorStock::create([
            'product_variant_id' => $variant->id,
            'color_id' => $fresno->id,
            'color' => $fresno->name,
            'stock' => 10,
        ]);

        $sale = Sale::create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'stage' => 'confirmado',
            'total' => 5000,
        ]);

        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Ropero Test',
            'quantity' => 4,
            'color_id' => $fresno->id,
            'chosen_color' => $fresno->name,
            'unit_price' => 2500,
            'subtotal' => 5000,
        ]);

        // Embarcar
        $this->actingAs($admin)->post(route('shipments.store'), [
            'driver_name' => 'Juan Pérez',
            'license_plate' => 'ABC-123',
            'destination' => 'Sucursal Centro',
            'pickup_type' => 'flota_propia',
            'items' => [
                ['sale_detail_id' => $detail->id, 'quantity' => 4]
            ],
        ]);

        $shipment = Shipment::latest()->first();
        $this->assertEquals(6, $variant->fresh()->stock);
        $this->assertEquals(6, $colorStock->fresh()->stock);

        // Cancelar el embarque
        $cancelResponse = $this->actingAs($admin)->patch(route('shipments.cancel', $shipment->id));
        $cancelResponse->assertSessionHas('success');

        // Stock debe regresar intacto
        $this->assertEquals(10, $variant->fresh()->stock);
        $this->assertEquals(10, $colorStock->fresh()->stock);
        $this->assertEquals('cancelado', $shipment->fresh()->status);
    }

    public function test_production_index_includes_custom_notes_for_special_colors_and_dynamic_colors(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $variant = $this->createMelamineVariant(stock: 0);
        $client = Client::factory()->create();

        $sale = Sale::create([
            'client_id' => $client->id,
            'user_id' => $admin->id,
            'stage' => 'produccion',
            'promised_date' => now()->addDays(2)->format('Y-m-d'),
            'total' => 6000,
        ]);

        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Ropero Premier Especial',
            'quantity' => 1,
            'chosen_color' => 'Especial: Verde Bosque',
            'color_id' => null,
            'custom_notes' => 'Muestra física entregada por cliente en mostrador',
            'additional_cost' => 500,
            'unit_price' => 5500,
            'subtotal' => 5500,
        ]);

        $response = $this->actingAs($admin)->get(route('production.plan'));

        $response->assertOk();
        $response->assertInertia(function ($page) use ($variant) {
            $page->component('Production/Index')
                ->has('productionQueue')
                ->has('allVariants')
                ->where('productionQueue.' . $variant->id . '.details.0.is_special', true)
                ->where('productionQueue.' . $variant->id . '.details.0.custom_notes', 'Muestra física entregada por cliente en mostrador');
        });
    }
}
