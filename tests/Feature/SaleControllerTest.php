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
        $sale = Sale::factory()->create(['stage' => 'pedido']);

        $response = $this->actingAs($vendedor)->patch("/sales/{$sale->id}/stage", [
            'stage' => 'confirmado'
        ]);

        $response->assertRedirect(); // usually back()
        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'stage' => 'confirmado'
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
            'id' => $sale->id,
            'stage' => 'pedido'
        ]);
    }
}
