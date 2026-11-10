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
use App\Models\SaleHistory;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Shipment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function setupCascadeContext()
{
    $admin = User::factory()->create(['role' => 'admin']);
    $client = Client::factory()->create(['name' => 'Cliente Cascada SA']);

    $category = Category::create(['name' => 'Oficina', 'activo' => true]);
    $material = Material::create(['name' => 'Madera', 'activo' => true, 'stock_por_color' => false]);
    $category->materials()->attach($material->id);

    $color = Color::create(['name' => 'Cedro', 'hex' => '#8B5A2B', 'activo' => true]);

    $product = Product::create(['category_id' => $category->id, 'name' => 'Escritorio Ejecutivo']);
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'material' => 'Madera',
        'material_id' => $material->id,
        'measurements' => '1.60x0.80',
        'price_1' => 7000,
        'stock' => 0,
        'damaged_stock' => 0,
        'reserved_stock' => 0,
    ]);

    $sale = Sale::factory()->create([
        'client_id' => $client->id,
        'stage' => 'entregado',
        'promised_date' => now()->toDateString(),
    ]);

    $detail = SaleDetail::create([
        'sale_id' => $sale->id,
        'product_variant_id' => $variant->id,
        'product_name' => 'Escritorio Ejecutivo',
        'quantity' => 2,
        'unit_price' => 7000,
        'subtotal' => 14000,
        'chosen_color' => 'Cedro',
        'color_id' => $color->id,
    ]);

    $shipment = Shipment::create([
        'driver_name' => 'Manuel Logística',
        'license_plate' => 'CAS-123',
        'destination' => 'Oficinas Centro',
        'status' => 'entregado',
        'pickup_type' => 'flota_propia',
        'user_id' => $admin->id,
    ]);

    $delivery = SaleDelivery::create([
        'shipment_id' => $shipment->id,
        'sale_detail_id' => $detail->id,
        'quantity_delivered' => 2,
    ]);

    return compact('admin', 'client', 'color', 'product', 'variant', 'sale', 'detail', 'shipment', 'delivery');
}

test('sale return reverts sale stage from entregado to produccion and adds history log', function () {
    $ctx = setupCascadeContext();

    $payload = [
        'client_id' => $ctx['client']->id,
        'authorized_by' => 'Jefe de Planta',
        'notes' => 'Devolución para reparación en taller',
        'items' => [
            [
                'sale_detail_id' => $ctx['detail']->id,
                'shipment_id' => $ctx['shipment']->id,
                'quantity' => 1,
                'reason_type' => 'dano_transporte',
                'disposition' => 'danada',
                'notes' => 'Pata rota en entrega',
            ]
        ]
    ];

    $response = $this->actingAs($ctx['admin'])->postJson(route('shipments.returns.store'), $payload);
    $response->assertOk();

    // El pedido debe revertir su stage a 'produccion' porque ahora tiene 1 pieza pendiente de entrega
    $saleFresh = $ctx['sale']->fresh();
    expect($saleFresh->stage)->toBe('produccion');

    // Debe existir registro en sale_histories
    $history = SaleHistory::where('sale_id', $ctx['sale']->id)
        ->where('notes', 'like', '%Devolución%')
        ->first();
    expect($history)->not->toBeNull();
    expect($history->to_stage)->toBe('produccion');
});

test('production plan index accounts for returns by deducting from delivered and exposing faltantes', function () {
    $ctx = setupCascadeContext();

    // Registrar devolución de 1 pieza
    $saleReturn = SaleReturn::create([
        'folio' => 'DEV-2026-8888',
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

    // La venta se coloca en produccion
    $ctx['sale']->update(['stage' => 'produccion']);

    $response = $this->actingAs($ctx['admin'])->get(route('production.plan'));
    $response->assertOk();

    // Validar que en production plan aparezca la partida y que su delivered_quantity refleje 1 neto (2 - 1 = 1)
    $response->assertInertia(function (Assert $page) use ($ctx) {
        $page->component('Production/Index')
            ->has('productionQueue.' . $ctx['variant']->id, function (Assert $group) {
                $group->where('total_delivered', 1) // 2 entregadas - 1 devuelta = 1
                    ->where('pending_to_fabricate', 1) // 2 necesarias - 1 neta entregada = 1 pendiente
                    ->etc();
            });
    });
});

test('sale show view provides returned quantity and return items history', function () {
    $ctx = setupCascadeContext();

    $saleReturn = SaleReturn::create([
        'folio' => 'DEV-2026-7777',
        'client_id' => $ctx['client']->id,
        'user_id' => $ctx['admin']->id,
        'authorized_by' => 'Director Operativo',
        'returned_at' => now(),
        'notes' => 'Auditoría de devolución',
    ]);

    SaleReturnItem::create([
        'sale_return_id' => $saleReturn->id,
        'sale_detail_id' => $ctx['detail']->id,
        'shipment_id' => $ctx['shipment']->id,
        'quantity' => 1,
        'reason_type' => 'dano_transporte',
        'disposition' => 'danada',
        'notes' => 'Pieza golpeada',
    ]);

    $response = $this->actingAs($ctx['admin'])->get(route('sales.show', $ctx['sale']->id));
    $response->assertOk();

    $response->assertInertia(function (Assert $page) {
        $page->component('Sales/Show')
            ->has('sale.details.0', function (Assert $detail) {
                $detail->where('delivered_quantity', 2)
                    ->where('returned_quantity', 1)
                    ->has('return_items', 1)
                    ->where('return_items.0.quantity', 1)
                    ->etc();
            });
    });
});

test('dashboard exposes pending returns kpi and returns list for admin', function () {
    $ctx = setupCascadeContext();

    $saleReturn = SaleReturn::create([
        'folio' => 'DEV-2026-9999',
        'client_id' => $ctx['client']->id,
        'user_id' => $ctx['admin']->id,
        'authorized_by' => 'Jefe de Taller',
        'returned_at' => now(),
    ]);

    SaleReturnItem::create([
        'sale_return_id' => $saleReturn->id,
        'sale_detail_id' => $ctx['detail']->id,
        'shipment_id' => $ctx['shipment']->id,
        'quantity' => 1,
        'reason_type' => 'falla_calidad',
        'disposition' => 'danada',
    ]);

    // Pedido en produccion
    $ctx['sale']->update(['stage' => 'produccion']);

    $response = $this->actingAs($ctx['admin'])->get(route('dashboard'));
    $response->assertOk();

    $response->assertInertia(function (Assert $page) use ($saleReturn, $ctx) {
        $page->component('Dashboard')
            ->where('isAdmin', true)
            ->where('kpis.pending_returns', 1)
            ->has('pendingReturns', 1)
            ->where('pendingReturns.0.id', $saleReturn->id)
            ->where('pendingReturns.0.folio', 'DEV-2026-9999')
            ->where('pendingReturns.0.sale_id', $ctx['sale']->id)
            ->where('pendingReturns.0.total_pieces', 1);
    });
});

test('shipment create exposes returned detail as re-shippable replacement', function () {
    $ctx = setupCascadeContext();

    // Asegurar que hay stock en almacén para re-embarcar
    $ctx['variant']->update(['stock' => 5]);

    $saleReturn = SaleReturn::create([
        'folio' => 'DEV-2026-5555',
        'client_id' => $ctx['client']->id,
        'user_id' => $ctx['admin']->id,
        'authorized_by' => 'Calidad',
        'returned_at' => now(),
    ]);

    SaleReturnItem::create([
        'sale_return_id' => $saleReturn->id,
        'sale_detail_id' => $ctx['detail']->id,
        'shipment_id' => $ctx['shipment']->id,
        'quantity' => 1,
        'reason_type' => 'dano_transporte',
        'disposition' => 'reparada',
    ]);

    // La venta se reabrió a produccion
    $ctx['sale']->update(['stage' => 'produccion']);

    $response = $this->actingAs($ctx['admin'])->get(route('shipments.create'));
    $response->assertOk();

    $response->assertInertia(function (Assert $page) use ($ctx) {
        $page->component('Shipments/Create')
            ->has('shippableSales', 1)
            ->has('shippableSales.0', function (Assert $sale) use ($ctx) {
                $sale->where('id', $ctx['sale']->id)
                    ->has('details', 1)
                    ->where('details.0.id', $ctx['detail']->id)
                    ->where('details.0.has_returned_items', true)
                    ->where('details.0.delivered_quantity', 1) // 2 entregadas - 1 devuelta = 1 neta
                    ->etc();
            });
    });
});

test('shipment print manifest shows replacement note and zero cost for return replacement', function () {
    $ctx = setupCascadeContext();

    // 1 de las 2 piezas fue devuelta
    $saleReturn = SaleReturn::create([
        'folio' => 'DEV-2026-4444',
        'client_id' => $ctx['client']->id,
        'user_id' => $ctx['admin']->id,
        'authorized_by' => 'Garantías',
        'returned_at' => now(),
    ]);

    SaleReturnItem::create([
        'sale_return_id' => $saleReturn->id,
        'sale_detail_id' => $ctx['detail']->id,
        'shipment_id' => $ctx['shipment']->id,
        'quantity' => 1,
        'reason_type' => 'dano_transporte',
        'disposition' => 'reparada',
    ]);

    // Nuevo embarque de reposición
    $replacementShipment = Shipment::create([
        'driver_name' => 'Chofer Reposición',
        'license_plate' => 'REP-999',
        'destination' => 'Domicilio Cliente',
        'status' => 'entregado',
        'pickup_type' => 'flota_propia',
        'user_id' => $ctx['admin']->id,
    ]);

    SaleDelivery::create([
        'shipment_id' => $replacementShipment->id,
        'sale_detail_id' => $ctx['detail']->id,
        'quantity_delivered' => 1,
    ]);

    // Verificar que el endpoint de impresión responde con PDF
    $response = $this->actingAs($ctx['admin'])->get(route('shipments.print', $replacementShipment->id));
    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');

    // Verificar el renderizado de la plantilla Blade con la leyenda de reposición
    $replacementShipment->load(['deliveries.saleDetail.sale.client', 'deliveries.saleDetail.returnItems', 'user']);
    $groupedDeliveries = $replacementShipment->deliveries->groupBy(fn($d) => $d->saleDetail?->sale_id ?? 0);
    $html = view('pdf.shipment_manifest', [
        'shipment' => $replacementShipment,
        'groupedDeliveries' => $groupedDeliveries,
        'company' => [],
        'logoBase64' => null,
    ])->render();

    expect($html)->toContain('REPOSICIÓN POR DEVOLUCIÓN - SIN COSTO');
    expect($html)->toContain('Bonificación por Devolución');
    expect($html)->toContain('TOTAL A COBRAR ESTE VIAJE');
});

test('confirm delivery of replacement updates sale to entregado and decrements pending returns kpi in dashboard', function () {
    $ctx = setupCascadeContext();

    // 1 de las 2 piezas fue devuelta -> venta revertida a produccion
    $saleReturn = SaleReturn::create([
        'folio' => 'DEV-2026-1111',
        'client_id' => $ctx['client']->id,
        'user_id' => $ctx['admin']->id,
        'authorized_by' => 'Control de Calidad',
        'returned_at' => now(),
    ]);

    SaleReturnItem::create([
        'sale_return_id' => $saleReturn->id,
        'sale_detail_id' => $ctx['detail']->id,
        'shipment_id' => $ctx['shipment']->id,
        'quantity' => 1,
        'reason_type' => 'dano_transporte',
        'disposition' => 'reparada',
    ]);

    $ctx['sale']->update(['stage' => 'produccion']);

    // 1. Dashboard refleja 1 devolución pendiente
    $dash1 = $this->actingAs($ctx['admin'])->get(route('dashboard'));
    $dash1->assertOk();
    $dash1->assertInertia(fn (Assert $page) => $page->where('kpis.pending_returns', 1));

    // 2. Se arma y pone en tránsito el viaje de reposición por la pieza faltante
    $repShipment = Shipment::create([
        'driver_name' => 'Chofer Reposición',
        'license_plate' => 'REP-001',
        'destination' => 'Domicilio Cliente',
        'status' => 'en_transito',
        'pickup_type' => 'flota_propia',
        'user_id' => $ctx['admin']->id,
    ]);

    SaleDelivery::create([
        'shipment_id' => $repShipment->id,
        'sale_detail_id' => $ctx['detail']->id,
        'quantity_delivered' => 1,
    ]);

    // 3. Chofer confirma entrega del viaje
    $confirmResp = $this->actingAs($ctx['admin'])->patch(route('shipments.confirm', $repShipment->id));
    $confirmResp->assertRedirect();

    // 4. El pedido ahora tiene entregado neto 2 (2 entregadas - 1 devuelta + 1 reposición = 2) y se marca como 'entregado'
    $saleFresh = $ctx['sale']->fresh();
    expect($saleFresh->stage)->toBe('entregado');

    // 5. El dashboard descuenta el contador a 0
    $dash2 = $this->actingAs($ctx['admin'])->get(route('dashboard'));
    $dash2->assertOk();
    $dash2->assertInertia(fn (Assert $page) => $page->where('kpis.pending_returns', 0));
});
