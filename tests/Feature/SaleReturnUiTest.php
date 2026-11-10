<?php

use App\Models\Category;
use App\Models\Client;
use App\Models\Color;
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
use Inertia\Testing\AssertableInertia as Assert;

function setupReturnUiContext()
{
    $admin = User::factory()->create(['role' => 'admin']);
    $vendedor = User::factory()->create(['role' => 'vendedor']);
    $supervisor = User::factory()->create(['role' => 'supervisor']);
    $inventario = User::factory()->create(['role' => 'inventario']);

    $client = Client::factory()->create(['name' => 'Cliente Devoluciones SA']);

    $category = Category::create(['name' => 'Comedores', 'activo' => true]);
    $material = Material::create(['name' => 'Madera', 'activo' => true, 'stock_por_color' => false]);
    $category->materials()->attach($material->id);

    $color = Color::create(['name' => 'Nogal Clásico', 'hex' => '#4A2E18', 'activo' => true]);

    $product = Product::create(['category_id' => $category->id, 'name' => 'Mesa Roble']);
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'material' => 'Madera',
        'material_id' => $material->id,
        'measurements' => '2.0x1.0',
        'price_1' => 8000,
        'stock' => 15,
        'damaged_stock' => 0,
        'reserved_stock' => 0,
    ]);

    $sale = Sale::factory()->create([
        'client_id' => $client->id,
        'stage' => 'entregado',
    ]);

    $detail = SaleDetail::create([
        'sale_id' => $sale->id,
        'product_variant_id' => $variant->id,
        'product_name' => 'Mesa Roble',
        'quantity' => 5,
        'unit_price' => 8000,
        'subtotal' => 40000,
        'chosen_color' => 'Nogal Clásico',
        'color_id' => $color->id,
    ]);

    $shipment = Shipment::create([
        'driver_name' => 'Carlos Chofer',
        'license_plate' => 'XYZ-123',
        'destination' => 'Sucursal Norte',
        'status' => 'entregado',
        'pickup_type' => 'flota_propia',
        'user_id' => $admin->id,
    ]);

    $delivery = SaleDelivery::create([
        'shipment_id' => $shipment->id,
        'sale_detail_id' => $detail->id,
        'quantity_delivered' => 5,
    ]);

    return compact('admin', 'vendedor', 'supervisor', 'inventario', 'client', 'color', 'product', 'variant', 'sale', 'detail', 'shipment', 'delivery');
}

test('returns index page loads with returns list and stats for authorized roles', function () {
    $ctx = setupReturnUiContext();

    $saleReturn = SaleReturn::create([
        'folio' => 'DEV-2026-0001',
        'client_id' => $ctx['client']->id,
        'user_id' => $ctx['admin']->id,
        'authorized_by' => 'Jefe de Logística',
        'returned_at' => now(),
        'notes' => 'Devolución de prueba',
    ]);

    SaleReturnItem::create([
        'sale_return_id' => $saleReturn->id,
        'sale_detail_id' => $ctx['detail']->id,
        'shipment_id' => $ctx['shipment']->id,
        'quantity' => 1,
        'reason_type' => 'dano_transporte',
        'disposition' => 'danada',
    ]);

    $response = $this->actingAs($ctx['admin'])->get(route('shipments.returns.index'));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Shipments/Returns/Index')
        ->has('returns', 1)
        ->has('stats')
        ->where('stats.total_returns', 1)
        ->where('stats.total_items', 1)
        ->where('stats.damaged_items', 1)
    );

    // Inventario y supervisor también tienen acceso
    $this->actingAs($ctx['inventario'])->get(route('shipments.returns.index'))->assertOk();
    $this->actingAs($ctx['supervisor'])->get(route('shipments.returns.index'))->assertOk();
});

test('returns create page loads with eligible clients and active colors', function () {
    $ctx = setupReturnUiContext();

    $response = $this->actingAs($ctx['admin'])->get(route('shipments.returns.create'));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Shipments/Returns/Create')
        ->has('clients')
        ->has('colors')
    );
});

test('returns create page returns deliveries filtered by client_id with calculated net available', function () {
    $ctx = setupReturnUiContext();

    // Se entregaron 5 piezas. Supongamos que ya se devolvió 1 pieza previa.
    $saleReturn = SaleReturn::create([
        'folio' => 'DEV-2026-0002',
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

    $response = $this->actingAs($ctx['admin'])->get(route('shipments.returns.create', ['client_id' => $ctx['client']->id]));
    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('Shipments/Returns/Create')
        ->has('returnableDeliveries', 1)
        ->where('returnableDeliveries.0.quantity_delivered', 5)
        ->where('returnableDeliveries.0.already_returned', 1)
        ->where('returnableDeliveries.0.net_available', 4) // 5 - 1 = 4
    );
});

test('unauthorized roles cannot access returns index or create', function () {
    $ctx = setupReturnUiContext();

    $this->actingAs($ctx['vendedor'])->get(route('shipments.returns.index'))->assertForbidden();
    $this->actingAs($ctx['vendedor'])->get(route('shipments.returns.create'))->assertForbidden();
});
