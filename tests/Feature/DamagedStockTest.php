<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\ProductVariant;

class DamagedStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_supervisor_can_mark_stock_as_damaged_and_creates_log()
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock' => 7,
            'damaged_stock' => 0,
        ]);

        $this->assertEquals(7, $variant->available_stock);

        $response = $this->actingAs($supervisor)->post(route('damaged-stock.store'), [
            'product_variant_id' => $variant->id,
            'type' => 'marcar_danada',
            'quantity' => 1,
            'notes' => 'Esquina rota en bodega',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $variant->refresh();
        $this->assertEquals(7, $variant->stock); // Sigue teniendo 7 físicas
        $this->assertEquals(1, $variant->damaged_stock); // 1 dañada
        $this->assertEquals(6, $variant->available_stock); // Solo 6 disponibles

        $this->assertDatabaseHas('damaged_stock_logs', [
            'product_variant_id' => $variant->id,
            'user_id' => $supervisor->id,
            'type' => 'marcar_danada',
            'quantity' => 1,
            'notes' => 'Esquina rota en bodega',
        ]);
    }

    public function test_cannot_mark_more_damaged_stock_than_available()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock' => 5,
            'reserved_stock' => 2, // Disponibles = 3
            'damaged_stock' => 0,
        ]);

        $this->assertEquals(3, $variant->available_stock);

        $response = $this->actingAs($admin)->post(route('damaged-stock.store'), [
            'product_variant_id' => $variant->id,
            'type' => 'marcar_danada',
            'quantity' => 4, // Intenta dañar 4 habiendo solo 3 disponibles
            'notes' => 'Intento inválido',
        ]);

        $response->assertSessionHasErrors('quantity');

        $variant->refresh();
        $this->assertEquals(0, $variant->damaged_stock);
        $this->assertEquals(3, $variant->available_stock);
    }

    public function test_can_mark_damaged_piece_as_repaired()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock' => 7,
            'damaged_stock' => 2,
        ]);

        $this->assertEquals(5, $variant->available_stock);

        $response = $this->actingAs($admin)->post(route('damaged-stock.store'), [
            'product_variant_id' => $variant->id,
            'type' => 'reparada',
            'quantity' => 1,
            'notes' => 'Se lijó y barnizó en taller',
        ]);

        $response->assertSessionHasNoErrors();

        $variant->refresh();
        $this->assertEquals(7, $variant->stock);
        $this->assertEquals(1, $variant->damaged_stock);
        $this->assertEquals(6, $variant->available_stock);

        $this->assertDatabaseHas('damaged_stock_logs', [
            'product_variant_id' => $variant->id,
            'type' => 'reparada',
            'quantity' => 1,
            'notes' => 'Se lijó y barnizó en taller',
        ]);
    }

    public function test_baja_definitiva_decrements_both_stock_and_damaged_stock()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock' => 7,
            'damaged_stock' => 1,
        ]);

        $this->assertEquals(6, $variant->available_stock);

        $response = $this->actingAs($admin)->post(route('damaged-stock.store'), [
            'product_variant_id' => $variant->id,
            'type' => 'baja_definitiva',
            'quantity' => 1,
            'notes' => 'Pérdida total irreparable',
        ]);

        $response->assertSessionHasNoErrors();

        $variant->refresh();
        $this->assertEquals(6, $variant->stock); // Descontó del físico total
        $this->assertEquals(0, $variant->damaged_stock); // Descontó de dañadas
        $this->assertEquals(6, $variant->available_stock); // Siguen 6 disponibles

        $this->assertDatabaseHas('damaged_stock_logs', [
            'product_variant_id' => $variant->id,
            'type' => 'baja_definitiva',
            'quantity' => 1,
        ]);
    }

    public function test_unauthorized_roles_cannot_modify_damaged_stock()
    {
        $vendedor = User::factory()->create(['role' => 'vendedor']);
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $response = $this->actingAs($vendedor)->post(route('damaged-stock.store'), [
            'product_variant_id' => $variant->id,
            'type' => 'marcar_danada',
            'quantity' => 1,
        ]);

        $response->assertForbidden();
    }
}
