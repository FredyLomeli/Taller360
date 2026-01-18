<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Client;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- 1. CONFIGURACIÓN DE REGLAS (MATERIALES Y COLORES) ---
        
        $rulesCategoryMaterial = [
            'Roperos'               => ['MDF', 'Madera y MDF enchapado', 'Melamina'],
            'Trinchers'             => ['Madera', 'Madera y MDF enchapado', 'MDF'],
            'Cómodas y Lokers'      => ['MDF', 'Madera y MDF enchapado', 'Melamina'],
            'Recámaras y comedores' => ['MDF', 'Madera y MDF enchapado', 'Melamina'],
            'Bases'                 => ['MDF', 'Madera'],
        ];

        $rulesMaterialColor = [
            'MDF' => ['Chocolate', 'Nogal', 'Blanco', 'Gris', 'Cherry', '258'],
            'MADERA' => ['Chocolate', 'Caoba', 'Tabaco', 'Cherry'],
            'MELAMINA' => ['Fresno andino', 'Parota', 'Nogal africano', 'Gris cenizo', 'Gris antracita', 'Tzalam', 'Moka'],
            'Madera y MDF enchapado' => ['Chocolate', 'Nogal', 'Caoba', 'Tabaco', 'Cherry']
        ];

        // --- 2. CREACIÓN DE USUARIOS (ADMIN Y VENDEDORES) ---
        
        echo "👤 Creando usuarios...\n";

        // A. Crear Admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@admin.com'],
            [
                'name' => 'Administrador Principal',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        // B. Crear 3 Vendedores
        $vendors = [];
        for ($i = 1; $i <= 3; $i++) {
            $vendors[] = User::firstOrCreate(
                ['email' => "vendedor{$i}@tienda.com"],
                [
                    'name' => "Vendedor {$i}",
                    'password' => Hash::make('password'),
                    'role' => 'vendedor',
                    'email_verified_at' => now(),
                ]
            );
        }

        // Juntamos a todos (Admin + Vendedores) para repartir las ventas aleatoriamente
        $allStaff = collect([$admin])->merge($vendors);
        
        echo "✅ Admin y 3 Vendedores creados.\n";

        // --- 3. CREACIÓN DE CATEGORÍAS ---
        
        foreach (array_keys($rulesCategoryMaterial) as $catName) {
            Category::firstOrCreate(['name' => $catName]);
        }
        $categories = Category::all();
        echo "✅ Categorías creadas.\n";

        // --- 4. CREACIÓN DE PRODUCTOS Y VARIANTES ---
        
        echo "🔄 Generando inventario...\n";

        for ($i = 0; $i < 200; $i++) {
            $category = $categories->random();
            
            $product = Product::factory()->create([
                'category_id' => $category->id,
                'name' => rtrim($category->name, 's') . ' Modelo ' . fake()->word(), 
            ]);

            $allowedMaterials = $rulesCategoryMaterial[$category->name] ?? ['MDF'];
            $numVariants = rand(2, 4);

            for ($j = 0; $j < $numVariants; $j++) {
                $material = fake()->randomElement($allowedMaterials);
                
                // Normalización de llave para color
                $matKey = strtoupper($material) === 'MDF' ? 'MDF' : (strtoupper($material) === 'MELAMINA' ? 'MELAMINA' : (strtoupper($material) === 'MADERA' ? 'MADERA' : $material));
                $allowedColors = $rulesMaterialColor[$matKey] ?? ['Chocolate'];
                $color = fake()->randomElement($allowedColors);

                $price1 = fake()->randomFloat(2, 1500, 12000);

                ProductVariant::create([
                    'product_id' => $product->id,
                    'material' => $material,
                    'color' => $color,
                    'sku' => strtoupper(substr($material, 0, 3)) . '-' . fake()->unique()->numerify('####'),
                    'stock' => fake()->numberBetween(0, 40),
                    'price_1' => $price1,
                    'price_2' => $price1 * 0.95,
                    'price_3' => $price1 * 0.90,
                    'price_4' => $price1 * 0.85,
                    'price_5' => $price1 * 0.80,
                ]);
            }
        }
        echo "✅ Productos creados.\n";

        // --- 5. CREACIÓN DE CLIENTES ---
        
        Client::factory(300)->create();
        echo "✅ Clientes creados.\n";

        // --- 6. GENERACIÓN DE VENTAS DISTRIBUIDAS ---
        
        echo "🔄 Simulando ventas distribuidas entre vendedores...\n";
        
        $clients = Client::all();
        $variants = ProductVariant::with('product')->get();

        DB::transaction(function () use ($allStaff, $clients, $variants) {
            // Generamos 500 ventas
            for ($i = 0; $i < 500; $i++) {
                
                // 1. Elegir un Vendedor al azar (o el admin) para esta venta
                $seller = $allStaff->random();

                // 2. Fecha aleatoria (80% antiguas, 20% recientes de HOY)
                // Esto asegura que haya datos en el Dashboard del día actual
                $date = (rand(1, 100) <= 80) 
                    ? Carbon::today()->subDays(rand(1, 60))->subHours(rand(1, 12)) // Últimos 2 meses
                    : Carbon::now()->subMinutes(rand(1, 400)); // Hoy

                $client = $clients->random();

                // Crear Venta (Inicialmente en 0)
                $sale = Sale::create([
                    'user_id' => $seller->id, // <--- AQUÍ ASIGNAMOS AL VENDEDOR
                    'client_id' => $client->id,
                    'total' => 0,
                    'status' => 'pagado',
                    'created_at' => $date,
                    'updated_at' => $date,
                    'paid_amount' => 0,
                    'change_amount' => 0,
                    'payment_method' => 'Efectivo'
                ]);

                $totalSale = 0;
                $itemsCount = rand(1, 3); // 1 a 3 productos por venta

                // Agregar detalles (Productos)
                for ($j = 0; $j < $itemsCount; $j++) {
                    $variant = $variants->random();
                    
                    // Precio según nivel de cliente
                    $priceField = 'price_' . $client->price_tier;
                    $unitPrice = $variant->$priceField ?? $variant->price_1;
                    
                    $qty = rand(1, 2);
                    $subtotal = $unitPrice * $qty;

                    SaleDetail::create([
                        'sale_id' => $sale->id,
                        'product_variant_id' => $variant->id,
                        'product_name' => $variant->product->name . ' (' . $variant->material . ')',
                        'quantity' => $qty,
                        'unit_price' => $unitPrice,
                        'subtotal' => $subtotal,
                        'created_at' => $date,
                        'updated_at' => $date,
                    ]);
                    
                    $totalSale += $subtotal;
                }

                // Actualizar total y pago
                // Simulamos que el 90% pagaron completo, y un 10% dejaron crédito pendiente
                $paidAmount = (rand(1, 100) <= 90) ? $totalSale : ($totalSale * 0.5); 
                
                $sale->update([
                    'total' => $totalSale, 
                    'paid_amount' => $paidAmount,
                    'status' => ($paidAmount >= $totalSale) ? 'pagado' : 'pendiente'
                ]);
            }
        });

        echo "✅ 500 Ventas generadas y distribuidas entre el equipo.\n";
        echo "🚀 ¡Base de datos lista!\n";
        echo "   Admin: admin@admin.com (password)\n";
        echo "   Vendedor 1: vendedor1@tienda.com (password)\n";
    }
}