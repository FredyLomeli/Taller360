<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendedor_can_access_pos_but_not_admin_routes()
    {
        $vendedor = User::factory()->create(['role' => 'vendedor']);

        $response = $this->actingAs($vendedor)->get('/pos');
        $response->assertStatus(200);

        $responseAdmin = $this->actingAs($vendedor)->get('/users');
        $responseAdmin->assertForbidden();
    }

    public function test_inventario_can_access_shipments_but_not_production()
    {
        $inventario = User::factory()->create(['role' => 'inventario']);

        $response = $this->actingAs($inventario)->get('/shipments');
        $response->assertStatus(200);

        $responseProd = $this->actingAs($inventario)->get('/production-plan');
        $responseProd->assertForbidden();
    }

    public function test_admin_can_access_multiple_protected_routes()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/pos')->assertStatus(200);
        $this->actingAs($admin)->get('/shipments')->assertStatus(200);
        $this->actingAs($admin)->get('/production-plan')->assertStatus(200);
        $this->actingAs($admin)->get('/users')->assertStatus(200);
    }
    public function test_supervisor_can_access_production_and_inventory_but_not_sales()
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);

        // Rutas permitidas
        $this->actingAs($supervisor)->get('/production-plan')->assertStatus(200);
        $this->actingAs($supervisor)->get('/shipments')->assertStatus(200);
        $this->actingAs($supervisor)->get('/products')->assertStatus(200);

        // Rutas prohibidas
        $this->actingAs($supervisor)->get('/sales')->assertStatus(200);
        $this->actingAs($supervisor)->get('/configuracion')->assertForbidden();
        $this->actingAs($supervisor)->get('/users')->assertForbidden();
        $this->actingAs($supervisor)->get('/clients')->assertForbidden();
    }
}
