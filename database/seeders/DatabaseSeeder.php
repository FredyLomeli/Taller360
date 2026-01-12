<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Client;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. USUARIO ADMIN
        User::factory()->create([
            'name' => 'Admin POS',
            'email' => 'admin@admin.com',
            'password' => bcrypt('12345678'),
        ]);

        // 2. CLIENTES (Con niveles de precio)
        Client::create([
            'name' => 'Venta Mostrador', 
            'business_name' => 'Público General',
            'price_tier' => 1,
            'email' => 'ventas@pos.com',
            'phones' => 'Sin dato',
            'street_address' => 'Local',
            'city' => 'Tepa',
            'zip_code' => '47600'
        ]);

        Client::create([
            'name' => 'Mueblería El Sol (Mayoreo)', 
            'price_tier' => 3, 
            'email' => 'sol@muebles.com',
            'city' => 'Guadalajara'
        ]);

        // 3. CATEGORÍAS (Tu lista completa)
        $catRoperos = Category::create(['name' => 'Roperos']);
        Category::create(['name' => 'Trinchers']);
        Category::create(['name' => 'Cómodas y Lokers']);
        Category::create(['name' => 'Recámaras y Comedores']);
        Category::create(['name' => 'Bases']);

        // 4. PRODUCTO DE PRUEBA
        $ropero = Product::create([
            'category_id' => $catRoperos->id,
            'name' => 'Ropero California',
            'measurements' => '180x120cm',
            'description' => 'Con luna y cajones.',
        ]);

        // 5. VARIANTES (Usando lógica de Precio 1-5)
        ProductVariant::create([
            'product_id' => $ropero->id,
            'material' => 'MDF',
            'color' => 'Chocolate',
            'price_1' => 2500.00, // Precio Público
            'price_2' => 2400.00,
            'price_3' => 2200.00, // Mayoreo
            'price_4' => 2100.00,
            'price_5' => 1900.00,
            'stock' => 10,
            'sku' => 'ROP-MDF-CHO'
        ]);
    }
}
