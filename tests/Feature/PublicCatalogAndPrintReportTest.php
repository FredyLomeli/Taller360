<?php

use App\Models\Category;
use App\Models\Color;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use App\Models\VariantColorStock;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

test('public catalog returns dynamic categories, materials and colors from database', function () {
    // 1. Crear categoría, material y color activos
    $category = Category::create(['name' => 'Recámaras de Lujo', 'activo' => true]);
    $material = Material::create([
        'name' => 'Parota Sólida',
        'activo' => true,
        'color_obligatorio' => true,
        'stock_por_color' => true
    ]);
    $category->materials()->attach($material->id);

    $color = Color::create(['name' => 'Parota Natural', 'hex' => '#8B5A2B', 'activo' => true]);
    $material->colors()->attach($color->id, ['activo' => true, 'orden' => 1]);

    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Cabecera Imperial',
        'description' => 'Madera sólida',
        'image' => 'products/cabecera.jpg',
        'is_favorite' => false
    ]);

    ProductVariant::create([
        'product_id' => $product->id,
        'material' => 'Parota Sólida',
        'material_id' => $material->id,
        'measurements' => 'King Size',
        'price_1' => 12000,
        'stock' => 3
    ]);

    $response = $this->get(route('catalog.index'));
    $response->assertStatus(200);

    // Verificar datos pasados a la vista
    $categoryMaterials = $response->viewData('categoryMaterials');
    $materialColors = $response->viewData('materialColors');
    $colorHex = $response->viewData('colorHex');

    expect($categoryMaterials)->toBeArray();
    expect($categoryMaterials)->toHaveKey('category-' . $category->id);
    expect($categoryMaterials['category-' . $category->id])->toContain('Parota Sólida');

    expect($materialColors)->toBeArray();
    expect($materialColors)->toHaveKey('Parota Sólida');
    expect($materialColors['Parota Sólida'])->toContain('Parota Natural');

    expect($colorHex)->toBeArray();
    expect($colorHex)->toHaveKey('Parota Natural');
    expect($colorHex['Parota Natural'])->toBe('#8B5A2B');
});

test('public catalog hides inactive categories and materials', function () {
    // Categoría inactiva
    $catInactive = Category::create(['name' => 'Categoría Oculta', 'activo' => false]);
    $mat = Material::create(['name' => 'Material X', 'activo' => true]);
    $catInactive->materials()->attach($mat->id);

    $prod1 = Product::create([
        'category_id' => $catInactive->id,
        'name' => 'Producto Oculto',
        'image' => 'products/oculto.jpg'
    ]);
    ProductVariant::create([
        'product_id' => $prod1->id,
        'material' => 'Material X',
        'material_id' => $mat->id,
        'measurements' => '100x50',
        'price_1' => 1000,
        'stock' => 1
    ]);

    $response = $this->get(route('catalog.index'));
    $response->assertStatus(200);
    $response->assertDontSee('Categoría Oculta');
    $response->assertDontSee('Producto Oculto');
});

test('public catalog excludes discontinued colors from material colors palette', function () {
    $category = Category::create(['name' => 'Comedores Finos', 'activo' => true]);
    $material = Material::create(['name' => 'Tzalam', 'activo' => true]);
    $category->materials()->attach($material->id);

    $activeColor = Color::create(['name' => 'Tzalam Vivo', 'hex' => '#112233', 'activo' => true]);
    $discontinuedColor = Color::create(['name' => 'Tzalam Viejo', 'hex' => '#445566', 'activo' => true]);

    $material->colors()->attach($activeColor->id, ['activo' => true, 'orden' => 1]);
    $material->colors()->attach($discontinuedColor->id, ['activo' => false, 'orden' => 2]); // Descontinuado para Tzalam

    $product = Product::create([
        'category_id' => $category->id,
        'name' => 'Mesa Tzalam',
        'image' => 'products/mesa.jpg'
    ]);
    ProductVariant::create([
        'product_id' => $product->id,
        'material' => 'Tzalam',
        'material_id' => $material->id,
        'measurements' => '200x100',
        'price_1' => 15000,
        'stock' => 2
    ]);

    $response = $this->get(route('catalog.index'));
    $response->assertStatus(200);

    $materialColors = $response->viewData('materialColors');
    expect($materialColors['Tzalam'])->toContain('Tzalam Vivo');
    expect($materialColors['Tzalam'])->not->toContain('Tzalam Viejo');
});

test('production print report includes special notes and sample specifications', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Trincheros', 'activo' => true]);
    $material = Material::create(['name' => 'Madera', 'activo' => true]);
    $category->materials()->attach($material->id);

    $product = Product::create(['category_id' => $category->id, 'name' => 'Trinchero Chevelle']);
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'material' => 'Madera',
        'material_id' => $material->id,
        'measurements' => '1.80m',
        'price_1' => 8000,
        'stock' => 0
    ]);

    $sale = Sale::factory()->create([
        'stage' => 'produccion',
        'promised_date' => now()->addDays(3)->format('Y-m-d')
    ]);

    SaleDetail::create([
        'sale_id' => $sale->id,
        'product_variant_id' => $variant->id,
        'product_name' => 'Trinchero Chevelle',
        'quantity' => 2,
        'unit_price' => 8000,
        'subtotal' => 16000,
        'chosen_color' => 'Especial: Verde Olivo',
        'custom_notes' => 'Muestra técnica aprobada con acabado mate',
    ]);

    $response = $this->actingAs($admin)->get(route('production.print'));
    $response->assertStatus(200);

    $props = $response->original->getData()['page']['props'];
    expect($props)->toHaveKey('productionQueue');

    $queue = $props['productionQueue'];
    $item = collect($queue)->firstWhere('name', 'Trinchero Chevelle');
    expect($item)->not->toBeNull();
    expect($item)->toHaveKey('special_notes');
    expect(implode(' ', $item['special_notes']))->toContain('Muestra técnica aprobada con acabado mate');
});

test('dashboard detects discontinued color with remaining physical stock', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Libreros', 'activo' => true]);
    $material = Material::create([
        'name' => 'Melamina',
        'activo' => true,
        'stock_por_color' => true
    ]);
    $category->materials()->attach($material->id);

    $color = Color::create(['name' => 'Gris Antiguo', 'hex' => '#999999', 'activo' => true]);
    // Color descontinuado en este material:
    $material->colors()->attach($color->id, ['activo' => false, 'orden' => 1]);

    $product = Product::create(['category_id' => $category->id, 'name' => 'Librero Modular']);
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'material' => 'Melamina',
        'material_id' => $material->id,
        'measurements' => '200x80',
        'price_1' => 4500,
        'stock' => 5
    ]);

    VariantColorStock::create([
        'product_variant_id' => $variant->id,
        'color' => 'Gris Antiguo',
        'color_id' => $color->id,
        'stock' => 5,
        'reserved_stock' => 0,
        'damaged_stock' => 0
    ]);

    $response = $this->actingAs($admin)->get(route('dashboard'));
    $response->assertStatus(200);

    $props = $response->original->getData()['page']['props'];
    expect($props)->toHaveKey('discontinuedColorStocks');
    expect(count($props['discontinuedColorStocks']))->toBeGreaterThanOrEqual(1);

    $alert = collect($props['discontinuedColorStocks'])->firstWhere('color', 'Gris Antiguo');
    expect($alert)->not->toBeNull();
    expect($alert['stock'])->toBe(5);
    expect($alert['message'])->toContain('descontinuado');
});

test('production print report excludes unused colors with zero demand and zero pending fabrication', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::create(['name' => 'Recámaras Melamina', 'activo' => true]);
    $material = Material::create([
        'name' => 'Melamina',
        'activo' => true,
        'stock_por_color' => true
    ]);
    $category->materials()->attach($material->id);

    $colorActiveUsed = Color::create(['name' => 'Azul Petroleo', 'hex' => '#112233', 'activo' => true]);
    $colorActiveUnused = Color::create(['name' => 'Parota', 'hex' => '#8B5A2B', 'activo' => true]);
    $material->colors()->attach($colorActiveUsed->id, ['activo' => true, 'orden' => 1]);
    $material->colors()->attach($colorActiveUnused->id, ['activo' => true, 'orden' => 2]);

    $product = Product::create(['category_id' => $category->id, 'name' => 'Alondra Melamina']);
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'material' => 'Melamina',
        'material_id' => $material->id,
        'measurements' => '1.20m',
        'price_1' => 3500,
        'stock' => 0
    ]);

    $sale = Sale::factory()->create([
        'stage' => 'produccion',
        'promised_date' => now()->addDays(2)->format('Y-m-d')
    ]);

    SaleDetail::create([
        'sale_id' => $sale->id,
        'product_variant_id' => $variant->id,
        'product_name' => 'Alondra Melamina',
        'quantity' => 4,
        'unit_price' => 3500,
        'subtotal' => 14000,
        'chosen_color' => 'Azul Petroleo',
    ]);

    $response = $this->actingAs($admin)->get(route('production.print'));
    $response->assertStatus(200);

    $queue = $response->original->getData()['page']['props']['productionQueue'];
    $item = collect($queue)->firstWhere('name', 'Alondra Melamina');
    expect($item)->not->toBeNull();
    expect($item['color_breakdown'])->toBeArray();

    // Solo debe incluir Azul Petroleo, NO Parota ni otros colores en ceros
    $colorsInBreakdown = collect($item['color_breakdown'])->pluck('color');
    expect($colorsInBreakdown)->toContain('Azul Petroleo');
    expect($colorsInBreakdown)->not->toContain('Parota');
});
