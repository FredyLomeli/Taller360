<?php

use App\Models\Category;
use App\Models\Client;
use App\Models\Color;
use App\Models\Material;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('sale_returns and sale_return_items tables exist with required columns', function () {
    expect(Schema::hasTable('sale_returns'))->toBeTrue();
    expect(Schema::hasColumns('sale_returns', [
        'id', 'folio', 'client_id', 'user_id', 'authorized_by', 'returned_at', 'notes', 'created_at', 'updated_at'
    ]))->toBeTrue();

    expect(Schema::hasTable('sale_return_items'))->toBeTrue();
    expect(Schema::hasColumns('sale_return_items', [
        'id', 'sale_return_id', 'sale_detail_id', 'shipment_id', 'quantity', 'reason_type',
        'disposition', 'color_id', 'chosen_color', 'notes', 'created_at', 'updated_at'
    ]))->toBeTrue();
});

test('sale return and return items can be created with relationships', function () {
    $user = User::factory()->create(['role' => 'inventario']);
    $client = Client::factory()->create(['name' => 'Mueblería Central']);

    $category = Category::create(['name' => 'Roperos', 'activo' => true]);
    $material = Material::create(['name' => 'Melamina', 'activo' => true, 'stock_por_color' => true]);
    $category->materials()->attach($material->id);

    $color = Color::create(['name' => 'Parota', 'hex' => '#8B5A2B', 'activo' => true]);
    $material->colors()->attach($color->id, ['activo' => true, 'orden' => 1]);

    $product = Product::create(['category_id' => $category->id, 'name' => 'Ropero 4 Puertas']);
    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'material' => 'Melamina',
        'material_id' => $material->id,
        'measurements' => '200x180',
        'price_1' => 7000,
        'stock' => 10
    ]);

    $sale = Sale::factory()->create(['client_id' => $client->id, 'stage' => 'entregado']);
    $detail = SaleDetail::create([
        'sale_id' => $sale->id,
        'product_variant_id' => $variant->id,
        'product_name' => 'Ropero 4 Puertas',
        'quantity' => 2,
        'unit_price' => 7000,
        'subtotal' => 14000,
        'chosen_color' => 'Parota',
        'color_id' => $color->id,
    ]);

    $shipment = Shipment::create([
        'driver_name' => 'Carlos Conductor',
        'license_plate' => 'ABC-123',
        'destination' => 'Sucursal Norte',
        'status' => 'entregado',
        'pickup_type' => 'flota_propia',
        'user_id' => $user->id,
    ]);

    // Crear devolución
    $saleReturn = SaleReturn::create([
        'folio' => 'DEV-2026-0001',
        'client_id' => $client->id,
        'user_id' => $user->id,
        'authorized_by' => 'Lic. Roberto Morales (Gerencia)',
        'returned_at' => now(),
        'notes' => 'Devolución parcial por detalle en puerta',
    ]);

    $item = SaleReturnItem::create([
        'sale_return_id' => $saleReturn->id,
        'sale_detail_id' => $detail->id,
        'shipment_id' => $shipment->id,
        'quantity' => 1,
        'reason_type' => 'dano_transporte',
        'disposition' => 'danada',
        'color_id' => $color->id,
        'chosen_color' => 'Parota',
        'notes' => 'Golpe en esquina inferior derecha',
    ]);

    // Verificar relaciones
    expect($saleReturn->client->id)->toBe($client->id);
    expect($saleReturn->user->id)->toBe($user->id);
    expect($saleReturn->items)->toHaveCount(1);
    expect($saleReturn->items->first()->id)->toBe($item->id);

    expect($item->saleReturn->id)->toBe($saleReturn->id);
    expect($item->saleDetail->id)->toBe($detail->id);
    expect($item->shipment->id)->toBe($shipment->id);
    expect($item->colorRelation->id)->toBe($color->id);

    expect($detail->returnItems)->toHaveCount(1);
    expect($shipment->returnItems)->toHaveCount(1);

    // Verificar cascade delete
    $saleReturn->delete();
    expect(SaleReturnItem::find($item->id))->toBeNull();
});
