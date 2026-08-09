<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Category;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_low_stock_alert_logic()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create();

        // 1. Variante NO favorita con stock=4 y min_stock=null (NO alerta)
        $noFavProduct = Product::factory()->create(['category_id' => $category->id, 'is_favorite' => false]);
        $noFavVariant = ProductVariant::factory()->create(['product_id' => $noFavProduct->id, 'stock' => 4, 'min_stock' => null]);

        // 2. Variante favorita con stock=10 y min_stock=12 (Sí alerta)
        $favProduct1 = Product::factory()->create(['category_id' => $category->id, 'is_favorite' => true]);
        $favVariant1 = ProductVariant::factory()->create(['product_id' => $favProduct1->id, 'stock' => 10, 'min_stock' => 12]);

        // 3. Variante favorita con stock=4 y min_stock=null (Sí alerta, porque <= 5)
        $favProduct2 = Product::factory()->create(['category_id' => $category->id, 'is_favorite' => true]);
        $favVariant2 = ProductVariant::factory()->create(['product_id' => $favProduct2->id, 'stock' => 4, 'min_stock' => null]);

        // 4. Variante favorita con stock=6 y min_stock=4 (No alerta, porque 6 > 4)
        $favProduct3 = Product::factory()->create(['category_id' => $category->id, 'is_favorite' => true]);
        $favVariant3 = ProductVariant::factory()->create(['product_id' => $favProduct3->id, 'stock' => 6, 'min_stock' => 4]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertStatus(200);
        
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('lowStockProducts', 2) // Solo favVariant1 y favVariant2
            ->where('lowStockProducts.0.id', fn ($id) => in_array($id, [$favVariant1->id, $favVariant2->id]))
            ->where('lowStockProducts.1.id', fn ($id) => in_array($id, [$favVariant1->id, $favVariant2->id]))
        );
    }
}
