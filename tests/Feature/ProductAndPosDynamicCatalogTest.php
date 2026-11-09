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
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\Client;

class ProductAndPosDynamicCatalogTest extends TestCase
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

    public function test_product_creation_stores_material_id_and_color_id_for_color_stocks(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $roperos = Category::where('name', 'Roperos')->first();
        $melamina = Material::where('name', 'Melamina')->first();
        $fresno = Color::where('name', 'Fresno Andino')->first();
        $parota = Color::where('name', 'Parota')->first();

        $payload = [
            'name' => 'Ropero Premier Melamina',
            'category_id' => $roperos->id,
            'description' => 'Ropero de melamina fina',
            'is_favorite' => false,
            'variants' => [
                [
                    'material' => $melamina->name,
                    'material_id' => $melamina->id,
                    'measurements' => '2.00 x 1.80 m',
                    'stock' => 15,
                    'price_1' => 4500,
                    'color_stocks' => [
                        [
                            'color' => $fresno->name,
                            'color_id' => $fresno->id,
                            'stock' => 10,
                        ],
                        [
                            'color' => $parota->name,
                            'color_id' => $parota->id,
                            'stock' => 5,
                        ],
                    ],
                ]
            ]
        ];

        $response = $this->actingAs($admin)->post(route('products.store'), $payload);
        $response->assertSessionHasNoErrors();

        $product = Product::where('name', 'Ropero Premier Melamina')->first();
        $this->assertNotNull($product);
        $this->assertCount(1, $product->variants);

        $variant = $product->variants->first();
        $this->assertEquals($melamina->id, $variant->material_id);
        $this->assertEquals(15, $variant->stock);

        $this->assertCount(2, $variant->colorStocks);
        $vcsFresno = $variant->colorStocks->where('color', 'Fresno Andino')->first();
        $this->assertNotNull($vcsFresno);
        $this->assertEquals($fresno->id, $vcsFresno->color_id);
        $this->assertEquals(10, $vcsFresno->stock);
    }

    public function test_pos_requires_color_for_melamina_sales(): void
    {
        $vendedor = User::factory()->create(['role' => 'vendedor']);
        $client = Client::factory()->create();
        $melamina = Material::where('name', 'Melamina')->first();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'material' => 'Melamina',
            'material_id' => $melamina->id,
            'stock' => 10,
        ]);

        // Intento de venta de Melamina SIN color
        $payload = [
            'client_id' => $client->id,
            'payment_method' => 'Efectivo',
            'signature' => 'data:image/png;base64,sample',
            'paid_amount' => 1000,
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'price' => 3000,
                    'chosen_color' => null, // Omitido
                    'color_id' => null,
                ]
            ]
        ];

        $response = $this->actingAs($vendedor)->post(route('sales.store'), $payload);
        $response->assertSessionHasErrors('error');
    }

    public function test_pos_strictly_rejects_special_color_for_melamina(): void
    {
        $vendedor = User::factory()->create(['role' => 'vendedor']);
        $client = Client::factory()->create();
        $melamina = Material::where('name', 'Melamina')->first();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'material' => 'Melamina',
            'material_id' => $melamina->id,
            'stock' => 10,
        ]);

        // Intento de color especial en Melamina (Prohibido por Regla A7)
        $payload = [
            'client_id' => $client->id,
            'payment_method' => 'Efectivo',
            'signature' => 'data:image/png;base64,sample',
            'paid_amount' => 1000,
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'price' => 3000,
                    'chosen_color' => 'Especial: Verde Olivo',
                    'color_id' => null,
                    'is_special_color' => true,
                    'notes' => 'Tono solicitado por cliente',
                ]
            ]
        ];

        $response = $this->actingAs($vendedor)->post(route('sales.store'), $payload);
        $response->assertSessionHasErrors('error');
    }

    public function test_pos_allows_null_color_for_mdf_and_madera(): void
    {
        $vendedor = User::factory()->create(['role' => 'vendedor']);
        $client = Client::factory()->create();
        $mdf = Material::where('name', 'MDF')->first();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'material' => 'MDF',
            'material_id' => $mdf->id,
            'stock' => 10,
        ]);

        // Venta de MDF sin color (Acabado natural / sin pintar)
        $payload = [
            'client_id' => $client->id,
            'payment_method' => 'Efectivo',
            'signature' => 'data:image/png;base64,sample',
            'paid_amount' => 1000,
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'price' => 2000,
                    'chosen_color' => null,
                    'color_id' => null,
                ]
            ]
        ];

        $response = $this->actingAs($vendedor)->post(route('sales.store'), $payload);
        $response->assertSessionHasNoErrors();

        $detail = SaleDetail::latest('id')->first();
        $this->assertNotNull($detail);
        $this->assertNull($detail->chosen_color);
        $this->assertNull($detail->color_id);
    }

    public function test_pos_allows_special_color_with_note_and_additional_cost_for_madera(): void
    {
        $vendedor = User::factory()->create(['role' => 'vendedor']);
        $client = Client::factory()->create();
        $madera = Material::where('name', 'Madera')->first();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'material' => 'Madera',
            'material_id' => $madera->id,
            'stock' => 10,
        ]);

        // Color especial en Madera con nota técnica y costo adicional (Regla A4)
        $payload = [
            'client_id' => $client->id,
            'payment_method' => 'Efectivo',
            'signature' => 'data:image/png;base64,sample',
            'paid_amount' => 2500,
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 2,
                    'price' => 1000,
                    'is_special_color' => true,
                    'special_color_name' => 'Nogal Envejecido',
                    'chosen_color' => 'Especial: Nogal Envejecido',
                    'color_id' => null,
                    'notes' => 'Acabado rústico según muestra física traída por cliente',
                    'additional_cost' => 250, // 250 extra por pieza
                ]
            ]
        ];

        $response = $this->actingAs($vendedor)->post(route('sales.store'), $payload);
        $response->assertSessionHasNoErrors();

        $detail = SaleDetail::latest('id')->first();
        $this->assertNotNull($detail);
        $this->assertNull($detail->color_id);
        $this->assertEquals('Especial: Nogal Envejecido', $detail->chosen_color);
        $this->assertEquals('Acabado rústico según muestra física traída por cliente', $detail->custom_notes);
        $this->assertEquals(250, $detail->additional_cost);
        // (1000 * 2) + 250 = 2250
        $this->assertEquals(2250, $detail->subtotal);
    }

    public function test_pos_rejects_discontinued_colors_for_new_sales(): void
    {
        $vendedor = User::factory()->create(['role' => 'vendedor']);
        $client = Client::factory()->create();
        $melamina = Material::where('name', 'Melamina')->first();
        $tzalam = Color::where('name', 'Tzalam')->first();

        // Descontinuar Tzalam en Melamina
        $melamina->colors()->updateExistingPivot($tzalam->id, ['activo' => false]);

        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'material' => 'Melamina',
            'material_id' => $melamina->id,
            'stock' => 10,
        ]);

        // Intento de venta de Melamina con el color descontinuado
        $payload = [
            'client_id' => $client->id,
            'payment_method' => 'Efectivo',
            'signature' => 'data:image/png;base64,sample',
            'paid_amount' => 1000,
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                    'price' => 3000,
                    'chosen_color' => 'Tzalam',
                    'color_id' => $tzalam->id,
                ]
            ]
        ];

        $response = $this->actingAs($vendedor)->post(route('sales.store'), $payload);
        $response->assertSessionHasErrors('error');
    }
}
