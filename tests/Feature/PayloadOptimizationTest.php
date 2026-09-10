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
}
