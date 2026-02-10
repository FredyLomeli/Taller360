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
        // Reglas de Materiales por Categoría
        $rulesCategoryMaterial = [
            'Roperos'               => ['MDF', 'Madera', 'Melamina'],
            'Trinchers'             => ['Madera', 'MDF'],
            'Cómodas y Lokers'      => ['MDF', 'Melamina'],
            'Recámaras y comedores' => ['MDF', 'Madera', 'Melamina'],
            'Bases'                 => ['MDF', 'Madera'],
        ];

        // Colores posibles (Ahora solo para elegir al azar en la venta, no en variante)
        $possibleColors = ['Chocolate', 'Nogal', 'Blanco', 'Gris', 'Cherry', 'Tzalam', 'Moka'];

        echo "👤 Creando usuarios...\n";
        $admin = User::firstOrCreate(['email' => 'admin@admin.com'], [
            'name' => 'Administrador Principal', 'password' => Hash::make('password'), 'role' => 'admin', 'email_verified_at' => now(),
        ]);

        $vendors = [];
        for ($i = 1; $i <= 3; $i++) {
            $vendors[] = User::firstOrCreate(['email' => "vendedor{$i}@tienda.com"], [
                'name' => "Vendedor {$i}", 'password' => Hash::make('password'), 'role' => 'vendedor', 'email_verified_at' => now(),
            ]);
        }
        $allStaff = collect([$admin])->merge($vendors);

        echo "📦 Creando Catálogo...\n";
        foreach (array_keys($rulesCategoryMaterial) as $catName) {
            Category::firstOrCreate(['name' => $catName]);
        }
        $categories = Category::all();

        for ($i = 0; $i < 100; $i++) {
            $category = $categories->random();
            $product = Product::factory()->create([
                'category_id' => $category->id,
                'name' => rtrim($category->name, 's') . ' Modelo ' . fake()->word(),
                'is_favorite' => fake()->boolean(20) // 20% de probabilidad de ser favorito
            ]);

            $allowedMaterials = $rulesCategoryMaterial[$category->name] ?? ['MDF'];
            
            // Creamos variantes solo por MATERIAL
            foreach ($allowedMaterials as $material) {
                $price1 = fake()->randomFloat(2, 1500, 12000);
                ProductVariant::create([
                    'product_id' => $product->id,
                    'material' => $material,
                    // YA NO HAY COLOR AQUÍ
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

        echo "👥 Creando Clientes...\n";
        Client::factory(50)->create(); // Factory debe estar actualizado con campos obligatorios

        echo "💰 Simulando Pedidos/Ventas...\n";
        $clients = Client::all();
        $variants = ProductVariant::with('product')->get();

        DB::transaction(function () use ($allStaff, $clients, $variants, $possibleColors) {
            for ($i = 0; $i < 200; $i++) {
                $seller = $allStaff->random();
                $date = Carbon::today()->subDays(rand(1, 60));
                $client = $clients->random();

                // Decidir estado aleatorio
                $stages = ['pedido', 'confirmado', 'produccion', 'enviado', 'entregado'];
                $stage = $stages[array_rand($stages)];

                $sale = Sale::create([
                    'user_id' => $seller->id,
                    'client_id' => $client->id,
                    'total' => 0,
                    'stage' => $stage, // Usamos stage en vez de status
                    'created_at' => $date,
                    'updated_at' => $date,
                    'paid_amount' => 0,
                    'promised_date' => $date->copy()->addDays(15),
                ]);

                $totalSale = 0;
                $itemsCount = rand(1, 3);

                for ($j = 0; $j < $itemsCount; $j++) {
                    $variant = $variants->random();
                    $unitPrice = $variant->price_1;
                    $qty = rand(1, 2);
                    
                    // Lógica de Adicionales
                    $hasAdicional = fake()->boolean(30);
                    $additionalCost = $hasAdicional ? rand(100, 500) : 0;
                    $notes = $hasAdicional ? 'Adicional: Jaladeras cromadas' : null;
                    
                    $subtotal = ($unitPrice * $qty) + $additionalCost;

                    SaleDetail::create([
                        'sale_id' => $sale->id,
                        'product_variant_id' => $variant->id,
                        'product_name' => $variant->product->name . ' (' . $variant->material . ')',
                        'quantity' => $qty,
                        'chosen_color' => $possibleColors[array_rand($possibleColors)], // Color se elige AQUÍ
                        'custom_notes' => $notes,
                        'additional_cost' => $additionalCost,
                        'unit_price' => $unitPrice,
                        'subtotal' => $subtotal,
                        'created_at' => $date,
                        'updated_at' => $date,
                    ]);
                    $totalSale += $subtotal;
                }

                // Lógica de Pagos según Estado
                $paidAmount = 0;
                if ($stage === 'pedido') $paidAmount = 0;
                elseif ($stage === 'confirmado') $paidAmount = $totalSale * 0.50; // Anticipo
                elseif ($stage === 'entregado') $paidAmount = $totalSale; // Liquidado

                $sale->update(['total' => $totalSale, 'paid_amount' => $paidAmount]);
            }
        });
        echo "✅ Base de datos reestructurada lista.\n";
    }
}