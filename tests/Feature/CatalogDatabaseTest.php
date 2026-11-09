<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Database\Seeders\CatalogSeeder;
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
use App\Models\User;

class CatalogDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_tables_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasTable('materials'));
        $this->assertTrue(Schema::hasColumns('materials', ['id', 'name', 'activo', 'color_obligatorio', 'stock_por_color', 'created_at', 'updated_at']));

        $this->assertTrue(Schema::hasTable('colors'));
        $this->assertTrue(Schema::hasColumns('colors', ['id', 'name', 'hex', 'activo', 'created_at', 'updated_at']));

        $this->assertTrue(Schema::hasTable('category_material'));
        $this->assertTrue(Schema::hasColumns('category_material', ['id', 'category_id', 'material_id', 'created_at', 'updated_at']));

        $this->assertTrue(Schema::hasTable('material_color'));
        $this->assertTrue(Schema::hasColumns('material_color', ['id', 'material_id', 'color_id', 'activo', 'orden', 'created_at', 'updated_at']));

        $this->assertTrue(Schema::hasColumn('categories', 'activo'));
        $this->assertTrue(Schema::hasColumn('product_variants', 'material_id'));
        $this->assertTrue(Schema::hasColumn('variant_color_stocks', 'color_id'));
        $this->assertTrue(Schema::hasColumn('work_orders', 'color_id'));
        $this->assertTrue(Schema::hasColumn('sale_details', 'color_id'));
    }

    public function test_catalog_seeder_seeds_15_colors_and_4_materials(): void
    {
        $this->seed(CatalogSeeder::class);

        // 15 Colores Oficiales
        $this->assertEquals(15, Color::count());

        $expectedColors = [
            'Chocolate' => '#3B2314',
            'Caoba' => '#4A1525',
            'Tabaco' => '#422A1D',
            'Cherry' => '#722F37',
            'Nogal' => '#5C3A21',
            'Blanco' => '#FFFFFF',
            'Gris' => '#9CA3AF',
            'Gris Antracita' => '#374151',
            'Gris Cenizo' => '#6B7280',
            'Parota' => '#8B5A2B',
            'Tzalam' => '#6F4E37',
            'Moka' => '#4A3728',
            'Fresno Andino' => '#D4C4A8',
            'Nogal Africano' => '#50382B',
            '258' => '#A08A75',
        ];

        foreach ($expectedColors as $name => $hex) {
            $color = Color::where('name', $name)->first();
            $this->assertNotNull($color, "Color {$name} no fue encontrado.");
            $this->assertEquals($hex, $color->hex, "HEX incorrecto para color {$name}.");
            $this->assertTrue($color->activo);
        }

        // 4 Materiales Oficiales
        $this->assertEquals(4, Material::count());

        $melamina = Material::where('name', 'Melamina')->first();
        $this->assertNotNull($melamina);
        $this->assertTrue($melamina->activo);
        $this->assertTrue($melamina->color_obligatorio);
        $this->assertTrue($melamina->stock_por_color);

        $nonMelamina = ['MDF', 'Madera', 'Madera y MDF enchapado'];
        foreach ($nonMelamina as $matName) {
            $mat = Material::where('name', $matName)->first();
            $this->assertNotNull($mat, "Material {$matName} no fue encontrado.");
            $this->assertTrue($mat->activo);
            $this->assertFalse($mat->color_obligatorio);
            $this->assertFalse($mat->stock_por_color);
        }
    }

    public function test_material_color_relations_count_and_palette(): void
    {
        $this->seed(CatalogSeeder::class);

        $mdf = Material::where('name', 'MDF')->first();
        $madera = Material::where('name', 'Madera')->first();
        $enchapado = Material::where('name', 'Madera y MDF enchapado')->first();
        $melamina = Material::where('name', 'Melamina')->first();

        // 6 MDF, 4 Madera, 4 Enchapado, 7 Melamina = 21 relaciones
        $this->assertCount(6, $mdf->colors);
        $this->assertCount(4, $madera->colors);
        $this->assertCount(4, $enchapado->colors);
        $this->assertCount(7, $melamina->colors);

        $melaminaColors = $melamina->colors->pluck('name')->all();
        $this->assertEqualsCanonicalizing([
            'Fresno Andino', 'Parota', 'Nogal Africano', 'Gris Cenizo', 'Gris Antracita', 'Tzalam', 'Moka'
        ], $melaminaColors);

        $maderaColors = $madera->colors->pluck('name')->all();
        $this->assertEqualsCanonicalizing([
            'Chocolate', 'Caoba', 'Tabaco', 'Cherry'
        ], $maderaColors);
    }

    public function test_category_material_relations_and_a11_compliance(): void
    {
        // Crear categorías requeridas antes del seeder
        Category::firstOrCreate(['name' => 'Roperos']);
        Category::firstOrCreate(['name' => 'Trincheros']);
        Category::firstOrCreate(['name' => 'Cómodas y Lokers']);
        Category::firstOrCreate(['name' => 'Recámaras y comedores']);
        Category::firstOrCreate(['name' => 'Bases']);

        $this->seed(CatalogSeeder::class);

        $roperos = Category::where('name', 'Roperos')->first();
        $comodas = Category::where('name', 'Cómodas y Lokers')->first();
        $trincheros = Category::where('name', 'Trincheros')->first();

        // Regla A11: Roperos y Cómodas deben incluir 'Madera' para no invalidar las 46 variantes reales
        $this->assertContains('Madera', $roperos->materials->pluck('name')->all());
        $this->assertContains('Madera', $comodas->materials->pluck('name')->all());
        $this->assertContains('Melamina', $roperos->materials->pluck('name')->all());
        $this->assertContains('MDF', $roperos->materials->pluck('name')->all());
        $this->assertContains('Madera y MDF enchapado', $roperos->materials->pluck('name')->all());

        $this->assertCount(4, $roperos->materials);
        $this->assertCount(4, $comodas->materials);
        $this->assertCount(3, $trincheros->materials);
    }

    public function test_backfill_existing_data_rule_a11(): void
    {
        $category = Category::create(['name' => 'Roperos']);
        $product = Product::factory()->create(['category_id' => $category->id]);
        $client = Client::factory()->create();
        $user = User::factory()->create(['role' => 'admin']);

        // Crear variante con material string antes del seeder
        $variantMadera = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'material' => 'Madera',
            'material_id' => null,
        ]);

        $variantMelamina = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'material' => 'Melamina',
            'material_id' => null,
        ]);

        // VCS sin color_id
        $vcs = VariantColorStock::create([
            'product_variant_id' => $variantMelamina->id,
            'color' => 'Parota',
            'color_id' => null,
            'stock' => 10,
        ]);

        // Sale y SaleDetail sin color_id
        $sale = Sale::factory()->create([
            'client_id' => $client->id,
            'user_id' => $user->id,
        ]);

        $saleDetail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $variantMelamina->id,
            'product_name' => 'Ropero Melamina Test',
            'quantity' => 1,
            'unit_price' => 1500,
            'subtotal' => 1500,
            'chosen_color' => 'Tzalam',
            'color_id' => null,
        ]);

        // WorkOrder sin color_id
        $workOrder = WorkOrder::create([
            'product_variant_id' => $variantMelamina->id,
            'origin_sale_detail_id' => $saleDetail->id,
            'created_by' => $user->id,
            'quantity_requested' => 1,
            'color' => 'Fresno Andino',
            'color_id' => null,
            'status' => 'pending',
        ]);

        // Ejecutar seeder que debe hacer el backfill
        $this->seed(CatalogSeeder::class);

        $variantMadera->refresh();
        $variantMelamina->refresh();
        $vcs->refresh();
        $saleDetail->refresh();
        $workOrder->refresh();

        $this->assertNotNull($variantMadera->material_id);
        $this->assertEquals('Madera', $variantMadera->materialRelation->name);

        $this->assertNotNull($variantMelamina->material_id);
        $this->assertEquals('Melamina', $variantMelamina->materialRelation->name);

        $this->assertNotNull($vcs->color_id);
        $this->assertEquals('Parota', $vcs->colorRelation->name);

        $this->assertNotNull($saleDetail->color_id);
        $this->assertEquals('Tzalam', $saleDetail->colorRelation->name);

        $this->assertNotNull($workOrder->color_id);
        $this->assertEquals('Fresno Andino', $workOrder->colorRelation->name);
    }

    public function test_eloquent_relationships_between_catalogs_and_models(): void
    {
        $this->seed(CatalogSeeder::class);

        $melamina = Material::where('name', 'Melamina')->first();
        $parota = Color::where('name', 'Parota')->first();

        // Relaciones Material <-> Color
        $this->assertTrue($melamina->colors->contains($parota));
        $this->assertTrue($parota->materials->contains($melamina));

        // activeColors scope
        $this->assertTrue($melamina->activeColors->contains($parota));

        // Desactivar en pivote
        $melamina->colors()->updateExistingPivot($parota->id, ['activo' => false]);
        $melamina->refresh();
        $this->assertFalse($melamina->activeColors->contains($parota));
        $this->assertTrue($melamina->colors->contains($parota));

        // Reactivar en pivote
        $melamina->colors()->updateExistingPivot($parota->id, ['activo' => true]);

        // Desactivar a nivel color
        $parota->update(['activo' => false]);
        $melamina->refresh();
        $this->assertFalse($melamina->activeColors->contains($parota));
    }
}
