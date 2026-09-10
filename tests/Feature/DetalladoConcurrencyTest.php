<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DetalladoConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_concurrent_detallado_requests_do_not_exceed_available_stock()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $client = Client::factory()->create();

        $product = Product::factory()->create();
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'material' => 'MDF',
            'measurements' => '1.0',
            'price_1' => 100,
            'stock' => 10,
            'reserved_stock' => 9, // available_stock = 1
        ]);

        $sale = Sale::create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'total' => 100,
            'stage' => 'produccion'
        ]);

        $detail = SaleDetail::create([
            'sale_id' => $sale->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'Mesa',
            'quantity' => 2,
            'unit_price' => 100,
            'subtotal' => 200,
        ]);

        // We simulate concurrency by calling the endpoint twice in sequence,
        // The first call should succeed, leaving available_stock = 0
        // The second call should fail with a validation exception
        
        $response1 = $this->actingAs($user)->post(route('sale-details.detallado', $detail->id), [
            'quantity' => 1
        ]);

        $response1->assertSessionHasNoErrors();
        $this->assertEquals(10, $variant->fresh()->reserved_stock);

        $response2 = $this->actingAs($user)->post(route('sale-details.detallado', $detail->id), [
            'quantity' => 1
        ]);

        $response2->assertSessionHasErrors('error');
        $this->assertEquals(10, $variant->fresh()->reserved_stock);
    }
}
