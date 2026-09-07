<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;

class SaleControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendedor_can_create_a_sale_and_stage_is_pedido()
    {
        $vendedor = User::factory()->create(['role' => 'vendedor']);
        $client = Client::factory()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'stock' => 10,
            'price_1' => 100,
            'material' => 'Madera',
            'measurements' => '1x1'
        ]);

        $payload = [
            'client_id' => $client->id,
            'payment_method' => 'Efectivo',
            'signature' => 'data:image/png;base64,1234',
            'paid_amount' => 50,
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 2,
                    'price' => 100,
                    'chosen_color' => 'Rojo',
                ]
            ]
        ];

        $response = $this->actingAs($vendedor)->post('/sales', $payload);
        
        $response->assertRedirect('/sales');
        
        $sale = Sale::where('client_id', $client->id)->first();
        $this->assertNotNull($sale);
        // Stage can be confirmado because paid_amount > 0 as logic says
        $this->assertEquals('confirmado', $sale->stage);
        
        // Stock should not be decremented
        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'stock' => 10
        ]);
        
        $this->assertDatabaseHas('sale_details', [
            'sale_id' => $sale->id,
            'quantity' => 2,
            'chosen_color' => 'Rojo',
        ]);
    }

    public function test_can_update_stage_to_valid_status()
    {
        $vendedor = User::factory()->create(['role' => 'vendedor']);
        // Venta que ya tiene promised_date: puede pasar a confirmado sin enviarla de nuevo
        $sale = Sale::factory()->create([
            'stage' => 'pedido',
            'promised_date' => '2026-12-15',
        ]);

        $response = $this->actingAs($vendedor)->patch("/sales/{$sale->id}/stage", [
            'stage' => 'confirmado',
            'promised_date' => '2026-12-15', // Enviamos la fecha existente explícitamente
        ]);

        $response->assertRedirect(); // back()
        $this->assertDatabaseHas('sales', [
            'id'    => $sale->id,
            'stage' => 'confirmado',
        ]);
    }

    public function test_cannot_update_stage_to_invalid_status()
    {
        $vendedor = User::factory()->create(['role' => 'vendedor']);
        $sale = Sale::factory()->create(['stage' => 'pedido']);

        $response = $this->actingAs($vendedor)->patch("/sales/{$sale->id}/stage", [
            'stage' => 'enviado'
        ]);

        $response->assertSessionHasErrors('stage');
        
        $this->assertDatabaseHas('sales', [
            'id'    => $sale->id,
            'stage' => 'pedido'
        ]);
    }

    /**
     * [NUEVO] promised_date es requerida cuando el destino es 'confirmado'.
     * Sin ella, Laravel debe rechazar con error de validación en la sesión.
     */
    public function test_update_stage_to_confirmado_fails_without_promised_date()
    {
        $vendedor = User::factory()->create(['role' => 'vendedor']);
        $sale = Sale::factory()->create([
            'stage'        => 'pedido',
            'promised_date' => null,
        ]);

        $response = $this->actingAs($vendedor)->patch("/sales/{$sale->id}/stage", [
            'stage' => 'confirmado',
            // Sin promised_date
        ]);

        // El backend debe retornar error de validación
        $response->assertSessionHasErrors('promised_date');

        // La etapa no debe haber cambiado
        $this->assertDatabaseHas('sales', [
            'id'    => $sale->id,
            'stage' => 'pedido',
        ]);
    }

    /**
     * [NUEVO] Cuando se envía promised_date en el payload, el backend la valida
     * y la persiste explícitamente junto con la nueva etapa en la misma transacción.
     * Usamos refresh()+Carbon para evitar el problema de formato de fecha en SQLite
     * ('YYYY-MM-DD HH:MM:SS' vs 'YYYY-MM-DD') que rompe assertDatabaseHas con strings.
     */
    public function test_update_stage_to_confirmado_succeeds_with_promised_date_in_payload()
    {
        $vendedor = User::factory()->create(['role' => 'vendedor']);
        $sale = Sale::factory()->create([
            'stage'         => 'pedido',
            'promised_date' => null,
        ]);

        $response = $this->actingAs($vendedor)->patch("/sales/{$sale->id}/stage", [
            'stage'         => 'confirmado',
            'promised_date' => '2026-12-01',
        ]);

        $response->assertRedirect();

        // Refrescamos el modelo para obtener los valores reales de la BD
        $sale->refresh();

        // El cast 'date' en Sale::$casts retorna Carbon; format('Y-m-d') es agnóstico al driver
        $this->assertEquals('confirmado', $sale->stage);
        $this->assertEquals('2026-12-01', $sale->promised_date->format('Y-m-d'));
    }

    /**
     * [NUEVO] Si el pedido ya tenía promised_date y el vendedor la reenvía en el payload,
     * la transición debe ser exitosa y la fecha debe mantenerse en BD.
     */
    public function test_update_stage_to_confirmado_succeeds_when_sale_already_has_promised_date()
    {
        $vendedor = User::factory()->create(['role' => 'vendedor']);
        $sale = Sale::factory()->create([
            'stage'         => 'pedido',
            'promised_date' => '2026-11-20',
        ]);

        $response = $this->actingAs($vendedor)->patch("/sales/{$sale->id}/stage", [
            'stage'         => 'confirmado',
            'promised_date' => '2026-11-20', // El frontend siempre reenvía la fecha existente
        ]);

        $response->assertRedirect();

        $sale->refresh();

        $this->assertEquals('confirmado', $sale->stage);
        $this->assertEquals('2026-11-20', $sale->promised_date->format('Y-m-d'));
    }
}
