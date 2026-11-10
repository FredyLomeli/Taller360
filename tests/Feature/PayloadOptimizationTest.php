<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Sale;
use Inertia\Testing\AssertableInertia as Assert;

class PayloadOptimizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_signature_is_excluded_from_kanban_and_show_payloads()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::factory()->create();
        $sale = Sale::factory()->create([
            'client_id' => $client->id,
            'signature' => 'data:image/png;base64,massive_base64_string_here_that_should_not_be_loaded',
            'stage' => 'pedido'
        ]);

        // Test GET /sales (Kanban)
        $responseKanban = $this->actingAs($admin)->get('/sales');
        
        $responseKanban->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Index')
            ->has('sales.data.0')
            ->missing('sales.data.0.signature')
        );

        // Test GET /sales/{id} (Show)
        $responseShow = $this->actingAs($admin)->get("/sales/{$sale->id}");
        
        $responseShow->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Show')
            ->has('sale')
            ->missing('sale.signature')
        );
    }

    public function test_signature_is_hidden_from_model_array_and_json_serialization()
    {
        $sale = Sale::factory()->create([
            'signature' => 'data:image/png;base64,hidden_base64_signature'
        ]);

        $freshSale = Sale::find($sale->id);

        // Hidden from array
        $array = $freshSale->toArray();
        $this->assertArrayNotHasKey('signature', $array, 'La firma no debe estar presente en toArray().');

        // Hidden from json
        $json = json_encode($freshSale);
        $this->assertStringNotContainsString('hidden_base64_signature', $json, 'La firma no debe filtrarse en JSON.');

        // But accessible directly for PDF rendering
        $this->assertEquals('data:image/png;base64,hidden_base64_signature', $freshSale->signature, 'La firma debe ser accesible como propiedad para los PDFs.');
    }

    public function test_print_note_has_access_to_signature()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $sale = Sale::factory()->create([
            'signature' => 'data:image/png;base64,valid_signature_for_pdf'
        ]);

        $response = $this->actingAs($admin)->get("/sales/{$sale->id}/note");
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_pos_create_payload_is_strictly_pruned()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = Client::factory()->create();
        $category = \App\Models\Category::create(['name' => 'Recámaras', 'activo' => true]);
        $material = \App\Models\Material::create(['name' => 'Pino', 'activo' => true, 'color_obligatorio' => false]);
        $color = \App\Models\Color::create(['name' => 'Natural', 'hex' => '#D7CCC8', 'activo' => true]);
        $material->colors()->attach($color->id, ['activo' => true, 'orden' => 1]);

        $product = \App\Models\Product::create([
            'category_id' => $category->id,
            'name' => 'Cama Individual',
            'description' => 'Descripción muy larga que no debe viajar al POS',
        ]);
        $variant = \App\Models\ProductVariant::create([
            'product_id' => $product->id,
            'material' => 'Pino',
            'material_id' => $material->id,
            'measurements' => '1.0x1.9m',
            'sku' => 'CAMA-PINO-100',
            'price_1' => 2500,
            'stock' => 5,
        ]);

        $response = $this->actingAs($admin)->get(route('sales.create'));
        $response->assertStatus(200);

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Create')
            ->has('products.0', fn (Assert $prod) => $prod
                ->has('id')
                ->has('name')
                ->has('category')
                ->has('variants')
                ->missing('description') // Poda de campos innecesarios
                ->etc()
            )
            ->has('clients.0', fn (Assert $cli) => $cli
                ->has('id')
                ->has('name')
                ->has('price_tier')
                ->missing('street_address')
                ->missing('references')
                ->etc()
            )
        );
    }
}
