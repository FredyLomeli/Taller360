<?php
// tests/Feature/ProductTest.php

use App\Models\User;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Category;
use App\Models\Sale;
use App\Models\SaleDetail;
use Inertia\Testing\AssertableInertia;

// ... (Tus tests anteriores de crear producto déjalos aquí) ...

test('se puede eliminar un producto que no tiene ventas', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);

    $this->actingAs($user)->delete(route('products.destroy', $product->id));

    $this->assertDatabaseMissing('products', ['id' => $product->id]);
});test('NO se puede eliminar un producto que ya fue vendido', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);
    $variant = ProductVariant::create([
        'product_id' => $product->id, 
        'material' => 'Madera',
        'measurements' => '2x2',
        'stock' => 10,
        'price_1' => 100
    ]);

    // Crear una venta vinculada a esta variante
    $sale = Sale::factory()->create();
    SaleDetail::create([
        'sale_id' => $sale->id,
        'product_variant_id' => $variant->id,
        'quantity' => 1,
        'unit_price' => 100,
        'subtotal' => 100,
        'product_name' => 'Test'
    ]);

    // Intentar borrar
    $this->actingAs($user)->delete(route('products.destroy', $product->id));

    // Debe seguir existiendo
    $this->assertDatabaseHas('products', ['id' => $product->id]);
});

test('al actualizar un producto se eliminan las variantes que no se enviaron', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create();
    
    // 1. Creamos 2 variantes iniciales
    $variant1 = ProductVariant::create(['product_id' => $product->id, 'sku' => 'VAR-1', 'stock'=>1, 'material'=>'A', 'measurements'=>'1x1', 'price_1'=>10]);
    $variant2 = ProductVariant::create(['product_id' => $product->id, 'sku' => 'VAR-2', 'stock'=>1, 'material'=>'B', 'measurements'=>'2x2', 'price_1'=>10]);

    // 2. Simulamos editar el producto, enviando SOLO la variante 1 (La 2 la borramos del form)
    $this->actingAs($user)->put(route('products.update', $product->id), [
        'name' => 'Producto Editado',
        'category_id' => $product->category_id,
        'variants' => [
            [
                'id' => $variant1->id, // Mantenemos esta
                'material' => 'A',
                'measurements' => '1x1',
                'stock' => 5,
                'price_1' => 20
            ]
            // La variante 2 NO la enviamos
        ]
    ]);

    // 3. Verificamos BD
    $this->assertDatabaseHas('product_variants', ['id' => $variant1->id, 'stock' => 5]); // Se actualizó
    $this->assertDatabaseMissing('product_variants', ['id' => $variant2->id]); // Se eliminó
});

test('un administrador puede registrar un producto con sus variantes y precios', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $category = Category::factory()->create();

    // Datos del formulario
    $payload = [
        'name' => 'Sala Modular',
        'category_id' => $category->id,
        'description' => 'Sala de 3 piezas',
        'variants' => [
            [
                'material' => 'Terciopelo',
                'measurements' => '2x2 metros',
                'sku' => 'SALA-GRIS-001',
                'stock' => 5,
                'price_1' => 15000, // Precio Público
                'price_2' => 14000, // Precio Mayorista
                'price_3' => 13000,
            ],
            [
                'material' => 'Piel',
                'measurements' => '3x2 metros',
                'sku' => 'SALA-NEGRA-002',
                'stock' => 2,
                'price_1' => 20000,
                'price_2' => 19000,
                'price_3' => 18000,
            ]
        ]
    ];

    // Acción
    $response = $this->actingAs($admin)->post(route('products.store'), $payload);

    // Verificación
    $response->assertRedirect(route('products.index'));

    // 1. Verificar que el producto padre existe
    $this->assertDatabaseHas('products', [
        'name' => 'Sala Modular',
        'category_id' => $category->id
    ]);

    // 2. Verificar que las variantes se guardaron (Buscamos por SKU)
    $this->assertDatabaseHas('product_variants', [
        'sku' => 'SALA-GRIS-001',
        'material' => 'Terciopelo',
        'measurements' => '2x2 metros',
        'price_1' => 15000
    ]);

    $this->assertDatabaseHas('product_variants', [
        'sku' => 'SALA-NEGRA-002',
        'material' => 'Piel',
        'measurements' => '3x2 metros',
        'price_1' => 20000
    ]);
});

test('la carga inicial entrega TODOS los productos al frontend para filtrado local', function () {
    $user = User::factory()->create(['role' => 'admin']);
    
    // 1. Creamos 5 productos variados
    Product::factory()->count(5)->create();
    
    // 2. Entramos al Panel de Inventario / Productos
    $response = $this->actingAs($user)->get(route('products.index'));

    // 3. Verificamos que lleguen los productos
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Products/Index')
        ->has('products', 5)
    );
});