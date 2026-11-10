<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Category;
use App\Models\Material;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantColorStock;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SaleDelivery;
use App\Models\Shipment;
use Illuminate\Support\Facades\DB;

class QueryOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_discontinued_color_stocks_query_does_not_n_plus_one_in_dashboard()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Oficina', 'activo' => true]);
        $material = Material::create(['name' => 'Melamina', 'activo' => true, 'stock_por_color' => true]);
        $category->materials()->attach($material->id);

        $product = Product::create(['category_id' => $category->id, 'name' => 'Escritorio L']);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'material' => 'Melamina',
            'material_id' => $material->id,
            'measurements' => '1.50m',
            'price_1' => 3000,
            'stock' => 50,
        ]);

        // Crear 10 colores descontinuados con stock remanente
        for ($i = 1; $i <= 10; $i++) {
            $color = Color::create(['name' => "Color Descontinuado {$i}", 'hex' => '#000000', 'activo' => true]);
            $material->colors()->attach($color->id, ['activo' => false, 'orden' => $i]);

            VariantColorStock::create([
                'product_variant_id' => $variant->id,
                'color' => $color->name,
                'color_id' => $color->id,
                'stock' => 5,
                'reserved_stock' => 0,
                'damaged_stock' => 0,
            ]);
        }

        DB::enableQueryLog();

        $response = $this->actingAs($admin)->get('/dashboard');
        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Con 10 colores descontinuados, si existiera N+1 habría 10+ consultas a material_color.
        // Verificamos que las consultas a material_color no sean individuales por fila.
        $materialColorQueries = collect($queries)->filter(function ($q) {
            return str_contains($q['query'], 'material_color');
        });

        // Debe haber como máximo 1 sola consulta SQL directa hacia material_color
        $this->assertLessThanOrEqual(1, $materialColorQueries->count(), 'La alerta de descontinuados debe resolverse en una sola consulta indexada.');
    }

    public function test_discontinued_color_stocks_query_does_not_n_plus_one_in_products_index()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Comedor', 'activo' => true]);
        $material = Material::create(['name' => 'Melamina', 'activo' => true, 'stock_por_color' => true]);
        $category->materials()->attach($material->id);

        $product = Product::create(['category_id' => $category->id, 'name' => 'Mesa Comedor']);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'material' => 'Melamina',
            'material_id' => $material->id,
            'measurements' => '2.00m',
            'price_1' => 6000,
            'stock' => 30,
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $color = Color::create(['name' => "Tono Viejo {$i}", 'hex' => '#111111', 'activo' => true]);
            $material->colors()->attach($color->id, ['activo' => false, 'orden' => $i]);

            VariantColorStock::create([
                'product_variant_id' => $variant->id,
                'color' => $color->name,
                'color_id' => $color->id,
                'stock' => 3,
                'reserved_stock' => 0,
                'damaged_stock' => 0,
            ]);
        }

        DB::enableQueryLog();

        $response = $this->actingAs($admin)->get('/products');
        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $materialColorQueries = collect($queries)->filter(function ($q) {
            return str_contains($q['query'], 'material_color');
        });

        $this->assertLessThanOrEqual(1, $materialColorQueries->count(), 'En Products/Index debe ejecutarse como máximo 1 consulta a material_color.');
    }

    public function test_sale_return_create_does_not_n_plus_one_on_client_deliveries()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::factory()->create();

        $category = Category::create(['name' => 'Salas', 'activo' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Sillón']);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'material' => 'Madera',
            'measurements' => '1.0m',
            'price_1' => 1500,
            'stock' => 20,
        ]);

        $sale = Sale::factory()->create(['client_id' => $client->id, 'stage' => 'entregado']);
        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Sillón Madera',
            'quantity' => 20,
            'unit_price' => 1500,
            'subtotal' => 30000,
            'chosen_color' => 'Nogal',
        ]);

        // Crear 6 entregas en distintos viajes
        for ($i = 1; $i <= 6; $i++) {
            $shipment = Shipment::create([
                'user_id' => $admin->id,
                'status' => 'entregado',
                'driver_name' => "Chofer {$i}",
            ]);

            SaleDelivery::create([
                'shipment_id' => $shipment->id,
                'sale_detail_id' => $detail->id,
                'quantity_delivered' => 2,
            ]);
        }

        DB::enableQueryLog();

        $response = $this->actingAs($admin)->get("/shipments/returns/create?client_id={$client->id}");
        $response->assertStatus(200);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Verificar que no haya una consulta por cada entrega a sale_return_items
        $returnItemQueries = collect($queries)->filter(function ($q) {
            return str_contains($q['query'], 'sale_return_items');
        });

        $this->assertLessThanOrEqual(1, $returnItemQueries->count(), 'Las devoluciones acumuladas deben precargarse en una sola consulta agrupada.');
    }

    public function test_production_plan_excludes_historical_delivered_sales_at_database_level()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Recámaras', 'activo' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Cabecera']);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'material' => 'Madera',
            'measurements' => 'Matrimonial',
            'price_1' => 2000,
            'stock' => 0,
        ]);

        // Venta histórica de hace 2 meses (completada y entregada)
        $oldSale = Sale::factory()->create([
            'stage' => 'entregado',
            'promised_date' => now()->subMonths(2)->format('Y-m-d'),
        ]);
        SaleDetail::create([
            'sale_id' => $oldSale->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Cabecera Histórica',
            'quantity' => 2,
            'unit_price' => 2000,
            'subtotal' => 4000,
            'chosen_color' => 'Chocolate',
        ]);

        // Venta activa atrasada (debe ser incluida)
        $overdueSale = Sale::factory()->create([
            'stage' => 'produccion',
            'promised_date' => now()->subWeeks(1)->format('Y-m-d'),
        ]);
        SaleDetail::create([
            'sale_id' => $overdueSale->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Cabecera Atrasada',
            'quantity' => 1,
            'unit_price' => 2000,
            'subtotal' => 2000,
            'chosen_color' => 'Chocolate',
        ]);

        $response = $this->actingAs($admin)->get('/production-plan');
        $response->assertStatus(200);

        $response->assertInertia(function (\Inertia\Testing\AssertableInertia $page) use ($variant) {
            $page->component('Production/Index')
                ->has('productionQueue.' . $variant->id, function (\Inertia\Testing\AssertableInertia $group) {
                    // Solo debe contener la venta atrasada activa (1 pieza), excluyendo la histórica (2 piezas)
                    $group->where('total_needed', 1)
                        ->where('pending_to_fabricate', 1)
                        ->etc();
                });
        });
    }
}
