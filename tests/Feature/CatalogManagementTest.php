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
use App\Models\WorkOrder;
use App\Models\Client;

class CatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CatalogSeeder::class);
    }

    public function test_catalog_management_access_control(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $vendedor = User::factory()->create(['role' => 'vendedor']);
        $inventario = User::factory()->create(['role' => 'inventario']);

        // Admin y Supervisor pueden ver la página
        $this->actingAs($admin)->get(route('catalogs.index'))->assertOk();
        $this->actingAs($supervisor)->get(route('catalogs.index'))->assertOk();

        // Vendedor e Inventario reciben 403 Forbidden
        $this->actingAs($vendedor)->get(route('catalogs.index'))->assertForbidden();
        $this->actingAs($inventario)->get(route('catalogs.index'))->assertForbidden();

        // Invitado es redirigido a login
        auth()->logout();
        $this->get(route('catalogs.index'))->assertRedirect(route('login'));
    }

    public function test_can_create_and_update_category_and_sync_materials(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // 1. Crear categoría
        $response = $this->actingAs($admin)->post(route('catalogs.categories.store'), [
            'name' => 'Comedores Especiales',
            'activo' => true,
        ]);
        $response->assertSessionHasNoErrors();
        $category = Category::where('name', 'Comedores Especiales')->first();
        $this->assertNotNull($category);
        $this->assertTrue($category->activo);

        // 2. Actualizar categoría
        $this->actingAs($admin)->put(route('catalogs.categories.update', $category), [
            'name' => 'Comedores Modernos',
            'activo' => false,
        ])->assertSessionHasNoErrors();

        $category->refresh();
        $this->assertEquals('Comedores Modernos', $category->name);
        $this->assertFalse($category->activo);

        // 3. Sincronizar materiales permitidos
        $mdf = Material::where('name', 'MDF')->first();
        $madera = Material::where('name', 'Madera')->first();

        $this->actingAs($admin)->post(route('catalogs.categories.sync-materials', $category), [
            'material_ids' => [$mdf->id, $madera->id],
        ])->assertSessionHasNoErrors();

        $category->refresh();
        $this->assertCount(2, $category->materials);
        $this->assertTrue($category->materials->contains($mdf));
        $this->assertTrue($category->materials->contains($madera));
    }

    public function test_can_create_and_update_material_with_flags_and_sync_colors(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // 1. Crear material con flags
        $response = $this->actingAs($admin)->post(route('catalogs.materials.store'), [
            'name' => 'Acrílico',
            'color_obligatorio' => true,
            'stock_por_color' => false,
            'activo' => true,
        ]);
        $response->assertSessionHasNoErrors();

        $material = Material::where('name', 'Acrílico')->first();
        $this->assertNotNull($material);
        $this->assertTrue($material->color_obligatorio);
        $this->assertFalse($material->stock_por_color);

        // 2. Actualizar material
        $this->actingAs($admin)->put(route('catalogs.materials.update', $material), [
            'name' => 'Acrílico Reforzado',
            'color_obligatorio' => false,
            'stock_por_color' => true,
            'activo' => true,
        ])->assertSessionHasNoErrors();

        $material->refresh();
        $this->assertEquals('Acrílico Reforzado', $material->name);
        $this->assertFalse($material->color_obligatorio);
        $this->assertTrue($material->stock_por_color);

        // 3. Sincronizar colores
        $blanco = Color::where('name', 'Blanco')->first();
        $chocolate = Color::where('name', 'Chocolate')->first();

        $this->actingAs($admin)->post(route('catalogs.materials.sync-colors', $material), [
            'color_ids' => [$blanco->id, $chocolate->id],
        ])->assertSessionHasNoErrors();

        $material->refresh();
        $this->assertCount(2, $material->colors);
    }

    public function test_can_toggle_color_active_per_material_independently(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $melamina = Material::where('name', 'Melamina')->first();
        $parota = Color::where('name', 'Parota')->first();

        // Parota está activo inicialmente en Melamina
        $this->assertTrue($melamina->activeColors->contains($parota));

        // Desactivar Parota solo en Melamina
        $this->actingAs($admin)->post(route('catalogs.materials.colors.toggle', [$melamina, $parota]), [
            'activo' => false,
        ])->assertSessionHasNoErrors();

        $melamina->refresh();
        $parota->refresh();

        // En Melamina activeColors ya no lo incluye
        $this->assertFalse($melamina->activeColors->contains($parota));
        // Pero sigue existiendo en su colección de colores (descontinuado)
        $this->assertTrue($melamina->colors->contains($parota));
        // Y a nivel global el color sigue activo
        $this->assertTrue($parota->activo);
    }

    public function test_can_create_color_with_valid_hex(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('catalogs.colors.store'), [
            'name' => 'Azul Marino',
            'hex' => '#001F3F',
            'activo' => true,
        ]);
        $response->assertSessionHasNoErrors();

        $color = Color::where('name', 'Azul Marino')->first();
        $this->assertNotNull($color);
        $this->assertEquals('#001F3F', $color->hex);
    }

    public function test_rename_lock_prevents_renaming_color_with_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::factory()->create();
        $user = User::factory()->create(['role' => 'vendedor']);

        $parota = Color::where('name', 'Parota')->first();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        // Crear una venta con el color Parota para generar historial
        $sale = Sale::factory()->create(['client_id' => $client->id, 'user_id' => $user->id]);
        SaleDetail::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Mueble Parota',
            'quantity' => 1,
            'unit_price' => 2000,
            'subtotal' => 2000,
            'chosen_color' => 'Parota',
            'color_id' => $parota->id,
        ]);

        // Intentar renombrar Parota debe fallar por el candado de renombrado (Regla A5)
        $response = $this->actingAs($admin)->put(route('catalogs.colors.update', $parota), [
            'name' => 'Parota Modificada',
            'hex' => $parota->hex,
            'activo' => true,
        ]);

        $response->assertSessionHasErrors('name');
        $parota->refresh();
        $this->assertEquals('Parota', $parota->name);

        // Sin embargo, cambiar el HEX o su estado activo sí debe permitirse
        $responseOk = $this->actingAs($admin)->put(route('catalogs.colors.update', $parota), [
            'name' => 'Parota',
            'hex' => '#996633',
            'activo' => false,
        ]);
        $responseOk->assertSessionHasNoErrors();

        $parota->refresh();
        $this->assertEquals('Parota', $parota->name);
        $this->assertEquals('#996633', $parota->hex);
        $this->assertFalse($parota->activo);
    }

    public function test_can_rename_color_without_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Crear color nuevo sin historial
        $color = Color::create([
            'name' => 'Color Prueba',
            'hex' => '#CCCCCC',
            'activo' => true,
        ]);

        // Renombrar debe ser exitoso
        $response = $this->actingAs($admin)->put(route('catalogs.colors.update', $color), [
            'name' => 'Color Prueba Renombrado',
            'hex' => '#DDDDDD',
            'activo' => true,
        ]);
        $response->assertSessionHasNoErrors();

        $color->refresh();
        $this->assertEquals('Color Prueba Renombrado', $color->name);
        $this->assertEquals('#DDDDDD', $color->hex);
    }
}
