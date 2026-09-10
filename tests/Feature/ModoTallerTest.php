<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleDetail;
use Inertia\Testing\AssertableInertia as Assert;

class ModoTallerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->client = Client::factory()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        $this->sale = Sale::factory()->create([
            'client_id' => $this->client->id,
            'total' => 5000,
            'paid_amount' => 1000,
            'change_amount' => 0,
        ]);

        SaleDetail::create([
            'sale_id' => $this->sale->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'product_name' => 'Sillon Tela',
            'unit_price' => 2500,
            'subtotal' => 5000
        ]);
    }

    public function test_vendedor_y_admin_ven_precios_completos()
    {
        $vendedor = User::factory()->create(['role' => 'vendedor']);

        $response = $this->actingAs($vendedor)->get("/sales/{$this->sale->id}");
        
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Show')
            ->has('sale.total')
            ->has('sale.paid_amount')
            ->has('sale.details.0.unit_price')
            ->has('sale.details.0.subtotal')
            ->where('is_production_mode', false)
        );
    }

    public function test_roles_operativos_no_ven_precios_y_fuerzan_modo_taller()
    {
        $roles = ['produccion', 'inventario', 'supervisor'];

        foreach ($roles as $role) {
            $user = User::factory()->create(['role' => $role]);

            $response = $this->actingAs($user)->get("/sales/{$this->sale->id}");
            
            $response->assertInertia(fn (Assert $page) => $page
                ->component('Sales/Show')
                ->missing('sale.total')
                ->missing('sale.paid_amount')
                ->missing('sale.change_amount')
                ->missing('sale.details.0.unit_price')
                ->missing('sale.details.0.subtotal')
                ->where('is_production_mode', true)
            );
        }
    }
}
