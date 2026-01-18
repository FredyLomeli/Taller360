<?php
// tests/Feature/SalesTest.php

use App\Models\User;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Client;
use App\Models\Category;
use App\Models\Sale;
use App\Models\SaleDetail;
use Inertia\Testing\AssertableInertia;

// Test 1: Crear venta y bajar stock (YA LO TIENES HECHO DEL PASO ANTERIOR ✅)
// ... mantenlo aquí ...

// Test 2: Cancelar venta y devolver stock
test('al cancelar una venta el stock se devuelve al inventario', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);
    
    // 1. Inventario inicial: 5
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'material' => 'Tela', 'color' => 'Azul',
        'stock' => 5, 
        'price_1' => 100
    ]);

    // 2. Creamos una venta YA existente de 2 unidades
    $sale = Sale::factory()->create(['status' => 'pagado']);
    SaleDetail::create([
        'sale_id' => $sale->id,
        'product_variant_id' => $variant->id,
        'quantity' => 2,
        'unit_price' => 100,
        'subtotal' => 200,
        'product_name' => 'Silla Azul'
    ]);
    
    // Simulamos que el stock bajó a 3 cuando se hizo la venta
    $variant->update(['stock' => 3]); 

    // 3. ACTUAR: Cancelar la venta
    // NOTA: Ajusta la ruta 'sales.cancel' si se llama diferente en tu web.php
    $this->actingAs($user)->post(route('sales.cancel', $sale->id));

    // 4. VERIFICAR
    // La venta debe estar cancelada
    $this->assertDatabaseHas('sales', ['id' => $sale->id, 'status' => 'cancelado']);
    
    // El stock debe haber subido: 3 (que había) + 2 (devueltos) = 5
    $this->assertDatabaseHas('product_variants', [
        'id' => $variant->id,
        'stock' => 5
    ]);
});

// Test 3: No se pueden eliminar ventas
test('las ventas no pueden ser eliminadas fisicamente', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $sale = Sale::factory()->create();

    // Intentamos mandar petición DELETE
    $response = $this->actingAs($user)->delete("/sales/{$sale->id}");

    // Debería dar error 404 (Ruta no existe) o 405 (Método no permitido)
    // O si tienes ruta pero protegida, verifica que no se borre.
    $this->assertDatabaseHas('sales', ['id' => $sale->id]);
});

// Test 4: No se pueden editar ventas
test('las ventas no pueden ser editadas', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $sale = Sale::factory()->create(['total' => 100]);

    // Intentamos hacer PUT para cambiar el total
    $this->actingAs($user)->put("/sales/{$sale->id}", ['total' => 500]);

    // El total debe seguir siendo 100
    $this->assertDatabaseHas('sales', ['id' => $sale->id, 'total' => 100]);
});

test('no se puede realizar una venta si no hay suficiente stock', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create();
    
    // Producto con solo 2 unidades
    $variant = ProductVariant::factory()->create(['stock' => 2]); 

    $response = $this->actingAs($user)->post(route('sales.store'), [
        'client_id' => $client->id,
        'payment_method' => 'Efectivo',
        'amount_received' => 1000,
        'cart' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 10, // <--- Intentamos vender 10
                'price' => 100,
                'product_name' => 'Test', 'material' => 'X', 'color' => 'Y'
            ]
        ]
    ]);

    // Debe rebotarnos con un error en la sesión
    $response->assertSessionHasErrors('error');
    
    // El stock debe seguir intacto en 2
    $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock' => 2]);
});

test('se puede generar el PDF del ticket sin errores', function () {
    $user = User::factory()->create();
    $sale = Sale::factory()->create(); // Crea venta con detalles
    
    // Necesitamos crear detalles para que el ticket tenga qué imprimir
    // (Asumiendo que tu SaleFactory ya crea detalles, si no, agréalo aquí)
    
    $response = $this->actingAs($user)->get(route('sales.print', $sale->id));
    
    // Verificar que responde OK (200) y que es un PDF
    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'application/pdf');
});

use App\Models\Setting; // Importar arriba

test('bloqueo de venta sin stock segun configuracion', function () {
    $user = User::factory()->create();
    $client = \App\Models\Client::factory()->create();
    
    // CASO 1: BLOQUEAR (allow_negative_stock = 0)
    Setting::updateOrCreate(['key' => 'allow_negative_stock'], ['value' => '0']);
    
    $variant = \App\Models\ProductVariant::factory()->create(['stock' => 1]); 

    // Intentar vender 5
    $this->actingAs($user)->post(route('sales.store'), [
        'client_id' => $client->id, 'payment_method' => 'Efectivo', 'amount_received' => 500,
        'cart' => [[
            'variant_id' => $variant->id, 'quantity' => 5, // Excede
            'price' => 10, 'product_name' => 'X', 'material'=>'A', 'color'=>'B'
        ]]
    ])->assertSessionHasErrors(); // Esperamos error

    // CASO 2: PERMITIR (allow_negative_stock = 1)
    Setting::updateOrCreate(['key' => 'allow_negative_stock'], ['value' => '1']);
    
    // Intentar vender 5 de nuevo
    $this->actingAs($user)->post(route('sales.store'), [
        'client_id' => $client->id, 'payment_method' => 'Efectivo', 'amount_received' => 500,
        'cart' => [[
            'variant_id' => $variant->id, 'quantity' => 5,
            'price' => 10, 'product_name' => 'X', 'material'=>'A', 'color'=>'B'
        ]]
    ])->assertSessionHasNoErrors(); // Debería pasar

    // El stock debe quedar en -4 (1 - 5)
    $this->assertDatabaseHas('product_variants', ['id' => $variant->id, 'stock' => -4]);
});

test('el historial de ventas SÍ filtra por servidor (AJAX)', function () {
    $user = User::factory()->create();
    
    // 1. Crear datos escenario
    $clienteJuan = Client::factory()->create(['name' => 'Juan Perez']);
    $clientePedro = Client::factory()->create(['name' => 'Pedro Paramo']);

    // Venta 1: De Juan
    Sale::factory()->create(['client_id' => $clienteJuan->id, 'total' => 500]);
    
    // Venta 2: De Pedro
    Sale::factory()->create(['client_id' => $clientePedro->id, 'total' => 800]);

    // 2. PRUEBA A: Carga normal (debe traer las 2)
    $this->actingAs($user)
         ->get(route('sales.index'))
         ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('sales.data', 2) // Vemos las 2
         );

    // 3. PRUEBA B: Filtrado (Simulamos buscar "Pedro")
    // Asumiendo que tu controlador recibe ?search=Pedro
    $this->actingAs($user)
         ->get(route('sales.index', ['search' => 'Pedro']))
         ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('sales.data', 1) // Solo debe llegar 1
            ->where('sales.data.0.client.name', 'Pedro Paramo') // Y debe ser la de Pedro
         );
});