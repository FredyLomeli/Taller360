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
});

test('NO se puede eliminar un producto que ya fue vendido', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);
    $variant = ProductVariant::create([
        'product_id' => $product->id, 
        'material'=>'X', 'color'=>'Y', 'stock'=>10, 'price_1'=>100
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
    $variant1 = ProductVariant::create(['product_id' => $product->id, 'sku' => 'VAR-1', 'stock'=>1, 'material'=>'A', 'color'=>'A', 'price_1'=>10]);
    $variant2 = ProductVariant::create(['product_id' => $product->id, 'sku' => 'VAR-2', 'stock'=>1, 'material'=>'B', 'color'=>'B', 'price_1'=>10]);

    // 2. Simulamos editar el producto, enviando SOLO la variante 1 (La 2 la borramos del form)
    $this->actingAs($user)->put(route('products.update', $product->id), [
        'name' => 'Producto Editado',
        'category_id' => $product->category_id,
        'variants' => [
            [
                'id' => $variant1->id, // Mantenemos esta
                'material' => 'A', 'color' => 'A', 'stock' => 5, 'price_1' => 20
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
        'measurements' => '2x2 metros',
        'variants' => [
            [
                'material' => 'Terciopelo',
                'color' => 'Gris',
                'sku' => 'SALA-GRIS-001',
                'stock' => 5,
                'price_1' => 15000, // Precio Público
                'price_2' => 14000, // Precio Mayorista
                'price_3' => 13000,
            ],
            [
                'material' => 'Piel',
                'color' => 'Negro',
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
    $response->assertRedirect(route('products.inventory'));

    // 1. Verificar que el producto padre existe
    $this->assertDatabaseHas('products', [
        'name' => 'Sala Modular',
        'category_id' => $category->id
    ]);

    // 2. Verificar que las variantes se guardaron (Buscamos por SKU)
    $this->assertDatabaseHas('product_variants', [
        'sku' => 'SALA-GRIS-001',
        'material' => 'Terciopelo',
        'price_1' => 15000
    ]);

    $this->assertDatabaseHas('product_variants', [
        'sku' => 'SALA-NEGRA-002',
        'material' => 'Piel',
        'price_1' => 20000
    ]);
});

// ... tus tests anteriores ...

test('se puede eliminar una variante especifica que NO tiene ventas', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create();
    
    // Crear variante sin ventas
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'sku' => 'BORRAME-001',
        'material' => 'Plastico', 'color' => 'Rojo', 'stock' => 10, 'price_1' => 100
    ]);

    // Usamos la ruta de borrado de variantes (asegúrate que sea esta en tu web.php)
    $this->actingAs($admin)->delete(route('variants.destroy', $variant->id));

    // Debe desaparecer de la BD
    $this->assertDatabaseMissing('product_variants', ['id' => $variant->id]);
});

test('NO se puede eliminar una variante que ya fue vendida', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $product = Product::factory()->create();
    
    // Crear variante
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'sku' => 'VENDIDO-001',
        'material' => 'Madera', 'color' => 'Azul', 'stock' => 10, 'price_1' => 100
    ]);

    // Simular venta de ESTA variante
    $sale = Sale::factory()->create();
    SaleDetail::create([
        'sale_id' => $sale->id,
        'product_variant_id' => $variant->id, // <--- La vinculamos
        'quantity' => 1, 'unit_price' => 100, 'subtotal' => 100, 'product_name' => 'Test'
    ]);

    // Intentar borrar
    $response = $this->actingAs($admin)->delete(route('variants.destroy', $variant->id));

    // Debe fallar (usualmente con un error en sesión o un 403/500 controlado)
    // O simplemente verificamos que siga existiendo:
    $this->assertDatabaseHas('product_variants', ['id' => $variant->id]);
});

test('la carga inicial entrega TODOS los productos al frontend para filtrado local', function () {
    // CORRECCIÓN AQUÍ: Le damos el rol de 'admin' para que pueda entrar al inventario
    $user = User::factory()->create(['role' => 'admin']);
    
    // 1. Creamos 25 productos variados
    Product::factory()->count(25)->create();
    
    // 2. Entramos al Panel de Inventario
    $response = $this->actingAs($user)->get(route('products.inventory'));

    // 3. Verificamos que lleguen los 25 exactos
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('Products/Inventory') // Asegúrate que este sea el nombre real de tu componente Vue
        ->has('products', 25) 
    );
});