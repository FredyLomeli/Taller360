<?php

use App\Models\Category;
use App\Models\Client;
use App\Models\Color;
use App\Models\DamagedStockLog;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleDelivery;
use App\Models\SaleDetail;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Shipment;
use App\Models\User;
use App\Models\VariantColorStock;

function createTestDeliveryContext(bool $isMelamina = false)
{
    $admin = User::factory()->create(['role' => 'admin']);
    $client = Client::factory()->create(['name' => 'Cliente Muebles SA']);

    $category = Category::create(['name' => 'Recámaras', 'activo' => true]);
    $material = Material::create([
        'name' => $isMelamina ? 'Melamina' : 'Madera',
        'activo' => true,
        'stock_por_color' => $isMelamina,
        'color_obligatorio' => $isMelamina,
    ]);
    $category->materials()->attach($material->id);

    $color1 = Color::create(['name' => 'Nogal', 'hex' => '#5C3A21', 'activo' => true]);
    $color2 = Color::create(['name' => 'Blanco', 'hex' => '#FFFFFF', 'activo' => true]);
    $material->colors()->attach($color1->id, ['activo' => true, 'orden' => 1]);
    $material->colors()->attach($color2->id, ['activo' => true, 'orden' => 2]);

    $product = Product::create(['category_id' => $category->id, 'name' => 'Comodín Test']);
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'material' => $material->name,
        'material_id' => $material->id,
        'measurements' => '1.20x0.60',
        'price_1' => 5000,
        'stock' => 10,
        'damaged_stock' => 0,
        'reserved_stock' => 0,
    ]);

    if ($isMelamina) {
        VariantColorStock::create([
            'product_variant_id' => $variant->id,
            'color' => 'Nogal',
            'color_id' => $color1->id,
            'stock' => 5,
            'damaged_stock' => 0,
            'reserved_stock' => 0,
        ]);
        VariantColorStock::create([
            'product_variant_id' => $variant->id,
            'color' => 'Blanco',
            'color_id' => $color2->id,
            'stock' => 5,
            'damaged_stock' => 0,
            'reserved_stock' => 0,
        ]);
    }

    $sale = Sale::factory()->create([
        'client_id' => $client->id,
        'stage' => 'entregado',
    ]);

    $detail = SaleDetail::create([
        'sale_id' => $sale->id,
        'product_variant_id' => $variant->id,
        'product_name' => 'Comodín Test',
        'quantity' => 4,
        'unit_price' => 5000,
        'subtotal' => 20000,
        'chosen_color' => 'Nogal',
        'color_id' => $color1->id,
    ]);

    $shipment = Shipment::create([
        'driver_name' => 'Operador Logística',
        'license_plate' => 'ABC-999',
        'destination' => 'Domicilio Cliente',
        'status' => 'entregado',
        'pickup_type' => 'flota_propia',
        'user_id' => $admin->id,
    ]);

    $delivery = SaleDelivery::create([
        'shipment_id' => $shipment->id,
        'sale_detail_id' => $detail->id,
        'quantity_delivered' => 4,
    ]);

    return compact('admin', 'client', 'material', 'color1', 'color2', 'product', 'variant', 'sale', 'detail', 'shipment', 'delivery');
}

test('sale return requires authorized_by as mandatory free text', function () {
    $ctx = createTestDeliveryContext();

    $payload = [
        'client_id' => $ctx['client']->id,
        'authorized_by' => '', // Vacío deliberadamente
        'items' => [
            [
                'sale_detail_id' => $ctx['detail']->id,
                'shipment_id' => $ctx['shipment']->id,
                'quantity' => 1,
                'reason_type' => 'dano_transporte',
                'disposition' => 'danada',
            ]
        ]
    ];

    $response = $this->actingAs($ctx['admin'])->postJson(route('shipments.returns.store'), $payload);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['authorized_by']);
});

test('sale return rejects quantity exceeding net delivered', function () {
    $ctx = createTestDeliveryContext();

    // Se entregaron 4 piezas. Intentar devolver 5 debe ser rechazado.
    $payload = [
        'client_id' => $ctx['client']->id,
        'authorized_by' => 'Gerencia General',
        'items' => [
            [
                'sale_detail_id' => $ctx['detail']->id,
                'shipment_id' => $ctx['shipment']->id,
                'quantity' => 5, // Excede las 4 entregadas
                'reason_type' => 'defecto_fabrica',
                'disposition' => 'danada',
            ]
        ]
    ];

    $response = $this->actingAs($ctx['admin'])->postJson(route('shipments.returns.store'), $payload);
    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['items']);
});

test('sale return with disposition danada increments physical and damaged stock and creates audit log', function () {
    $ctx = createTestDeliveryContext(true); // Melamina

    $variantBeforeStock = $ctx['variant']->fresh()->stock;
    $variantBeforeDamaged = $ctx['variant']->fresh()->damaged_stock;
    $colorStockBefore = VariantColorStock::where('product_variant_id', $ctx['variant']->id)
        ->where('color_id', $ctx['color1']->id)
        ->first();

    $payload = [
        'client_id' => $ctx['client']->id,
        'authorized_by' => 'Lic. Fernández (Director Operativo)',
        'notes' => 'Devolución de pieza golpeada en flete',
        'items' => [
            [
                'sale_detail_id' => $ctx['detail']->id,
                'shipment_id' => $ctx['shipment']->id,
                'quantity' => 2,
                'reason_type' => 'dano_transporte',
                'disposition' => 'danada',
                'color_id' => $ctx['color1']->id,
                'notes' => 'Pata rota durante descarga',
            ]
        ]
    ];

    $response = $this->actingAs($ctx['admin'])->postJson(route('shipments.returns.store'), $payload);
    $response->assertStatus(200);

    // Verificar cabecera
    $return = SaleReturn::latest()->first();
    expect($return)->not->toBeNull();
    expect($return->authorized_by)->toBe('Lic. Fernández (Director Operativo)');
    expect($return->folio)->toMatch('/^DEV-\d{4}-\d{4}$/');

    // Verificar stocks en ProductVariant
    $variantAfter = $ctx['variant']->fresh();
    expect($variantAfter->stock)->toBe($variantBeforeStock + 2);
    expect($variantAfter->damaged_stock)->toBe($variantBeforeDamaged + 2);
    // El disponible no sube porque entró como dañada:
    expect($variantAfter->available_stock)->toBe(max(0, $variantAfter->stock - $variantAfter->reserved_stock - $variantAfter->damaged_stock));

    // Verificar stocks en VariantColorStock (Melamina)
    $colorStockAfter = VariantColorStock::where('product_variant_id', $ctx['variant']->id)
        ->where('color_id', $ctx['color1']->id)
        ->first();
    expect($colorStockAfter->stock)->toBe($colorStockBefore->stock + 2);
    expect($colorStockAfter->damaged_stock)->toBe($colorStockBefore->damaged_stock + 2);

    // Verificar registro en damaged_stock_logs
    $log = DamagedStockLog::where('product_variant_id', $ctx['variant']->id)->latest()->first();
    expect($log)->not->toBeNull();
    expect($log->type)->toBe('marcar_danada');
    expect($log->quantity)->toBe(2);
    expect($log->notes)->toContain($return->folio);
});

test('sale return with disposition baja does not increment physical or available stock', function () {
    $ctx = createTestDeliveryContext();

    $variantBeforeStock = $ctx['variant']->fresh()->stock;
    $variantBeforeAvailable = $ctx['variant']->fresh()->available_stock;

    $payload = [
        'client_id' => $ctx['client']->id,
        'authorized_by' => 'Jefe de Planta',
        'items' => [
            [
                'sale_detail_id' => $ctx['detail']->id,
                'shipment_id' => $ctx['shipment']->id,
                'quantity' => 1,
                'reason_type' => 'dano_transporte',
                'disposition' => 'baja', // Merma total
                'notes' => 'Destrucción total en accidente',
            ]
        ]
    ];

    $response = $this->actingAs($ctx['admin'])->postJson(route('shipments.returns.store'), $payload);
    $response->assertStatus(200);

    $variantAfter = $ctx['variant']->fresh();
    expect($variantAfter->stock)->toBe($variantBeforeStock); // No se suma al físico
    expect($variantAfter->available_stock)->toBe($variantBeforeAvailable); // No se suma al disponible

    $returnItem = SaleReturnItem::latest()->first();
    expect($returnItem->disposition)->toBe('baja');
    expect($returnItem->quantity)->toBe(1);
});

test('sale return with disposition color_equivocado increments stock of real specified color', function () {
    $ctx = createTestDeliveryContext(true); // Melamina

    // La partida se vendió como Nogal (color1), pero el cliente recibió y regresa Blanco (color2).
    $whiteColorStockBefore = VariantColorStock::where('product_variant_id', $ctx['variant']->id)
        ->where('color_id', $ctx['color2']->id)
        ->first();

    $payload = [
        'client_id' => $ctx['client']->id,
        'authorized_by' => 'Supervisión de Calidad',
        'items' => [
            [
                'sale_detail_id' => $ctx['detail']->id,
                'shipment_id' => $ctx['shipment']->id,
                'quantity' => 1,
                'reason_type' => 'color_incorrecto',
                'disposition' => 'color_equivocado',
                'color_id' => $ctx['color2']->id, // Color real: Blanco
                'notes' => 'Se entregó blanco en lugar de nogal',
            ]
        ]
    ];

    $response = $this->actingAs($ctx['admin'])->postJson(route('shipments.returns.store'), $payload);
    $response->assertStatus(200);

    // Debe sumar 1 pieza física a Blanco y al inventario total
    $whiteColorStockAfter = VariantColorStock::where('product_variant_id', $ctx['variant']->id)
        ->where('color_id', $ctx['color2']->id)
        ->first();
    expect($whiteColorStockAfter->stock)->toBe($whiteColorStockBefore->stock + 1);
    expect($whiteColorStockAfter->available_stock)->toBe($whiteColorStockBefore->available_stock + 1);

    $variantAfter = $ctx['variant']->fresh();
    expect($variantAfter->stock)->toBe(11);
});

test('shipment cancellation is strictly blocked if shipment has associated returns rule b4 bis', function () {
    $ctx = createTestDeliveryContext();

    // Registrar devolución asociada a este embarque
    $saleReturn = SaleReturn::create([
        'folio' => 'DEV-2026-9999',
        'client_id' => $ctx['client']->id,
        'user_id' => $ctx['admin']->id,
        'authorized_by' => 'Gerencia',
        'returned_at' => now(),
    ]);

    SaleReturnItem::create([
        'sale_return_id' => $saleReturn->id,
        'sale_detail_id' => $ctx['detail']->id,
        'shipment_id' => $ctx['shipment']->id,
        'quantity' => 1,
        'reason_type' => 'dano_transporte',
        'disposition' => 'danada',
    ]);

    // Intentar cancelar el embarque
    $response = $this->actingAs($ctx['admin'])->patch(route('shipments.cancel', $ctx['shipment']->id));
    $response->assertSessionHas('error');
    $errorMsg = session('error');
    expect($errorMsg)->toContain('No se puede cancelar un embarque que tiene devoluciones asociadas');

    // El embarque debe permanecer en su status entregado
    expect($ctx['shipment']->fresh()->status)->toBe('entregado');
});
