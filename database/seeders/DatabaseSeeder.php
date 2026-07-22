<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Client;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

// Asegúrate de importar los seeders correctos
use Database\Seeders\SettingSeeder; 
use Database\Seeders\ClientSeeder;
use Database\Seeders\InventarioSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ==========================================
        // VARIABLE DE CONTROL (OPCIONALIDAD)
        // ==========================================
        // Cambia esto a 'true' SOLO si quieres llenar el sistema con ventas falsas para pruebas.
        $simularVentas = true; 
        $generarVendedor = true; 

        // 1. Configuraciones Globales
        echo "⚙️ Configurando el sistema...\n";
        $this->call(SettingSeeder::class);

        // 2. Creación de Usuarios (Tu acceso principal y vendedores iniciales)
        echo "👤 Creando usuarios...\n";
        $admin = User::firstOrCreate(['email' => 'admin@admin.com'], [
            'name' => 'Administrador Principal', 
            'password' => Hash::make('password'), 
            'role' => 'admin', 
            'email_verified_at' => now(),
        ]);

        if ($generarVendedor) {
            $vendors = [];
            for ($i = 1; $i <= 3; $i++) {
                $vendors[] = User::firstOrCreate(['email' => "vendedor{$i}@tienda.com"], [
                    'name' => "Vendedor {$i}", 
                    'password' => Hash::make('password'), 
                    'role' => 'vendedor', 
                    'email_verified_at' => now(),
                ]);
            }
            $allStaff = collect([$admin])->merge($vendors);
            echo "👤 Creando vededores...\n";
        } else {
            echo "👤 Sin crear vededores...\n";
        }

        // 3. Clientes (El tuyo que lee el CSV)
        echo "👥 Cargando Clientes...\n";
        $this->call(ClientSeeder::class);

        // 4. EL NUEVO CATÁLOGO Y VARIANTES (Tus 3 CSVs de inventario)
        echo "📦 Cargando Inventario Maestro...\n";
        $this->call(InventarioSeeder::class);
        
        // ------------- SIMULACIÓN DE VENTAS (OPCIONAL) -------------
        if ($simularVentas) {
            echo "💰 Simulando Pedidos/Ventas de prueba...\n";
            
            $clients = Client::all();
            $variants = ProductVariant::with('product')->get();
            $possibleColors = ['Chocolate', 'Nogal', 'Blanco', 'Gris', 'Cherry', 'Tzalam', 'Moka'];

            if ($variants->isNotEmpty() && $clients->isNotEmpty()) {
                DB::transaction(function () use ($allStaff, $clients, $variants, $possibleColors) {
                    for ($i = 0; $i < 50; $i++) { // Reducido a 50 para que no sea tan pesado si lo activas
                        $seller = $allStaff->random();
                        $date = Carbon::today()->subDays(rand(1, 60));
                        $client = $clients->random();

                        $stages = ['pedido', 'confirmado', 'produccion', 'enviado', 'entregado'];
                        $stage = $stages[array_rand($stages)];

                        $sale = Sale::create([
                            'user_id' => $seller->id,
                            'client_id' => $client->id,
                            'total' => 0,
                            'stage' => $stage,
                            'created_at' => $date,
                            'updated_at' => $date,
                            'paid_amount' => 0,
                            'promised_date' => $date->copy()->addDays(15),
                        ]);

                        $totalSale = 0;
                        $itemsCount = rand(1, 3);

                        for ($j = 0; $j < $itemsCount; $j++) {
                            $variant = $variants->random();
                            $unitPrice = $variant->price_1 > 0 ? $variant->price_1 : fake()->randomFloat(2, 2000, 8000); 
                            $qty = rand(1, 2);
                            
                            $hasAdicional = fake()->boolean(20);
                            $additionalCost = $hasAdicional ? rand(100, 500) : 0;
                            $notes = $hasAdicional ? 'Adicional: Jaladeras cromadas' : null;
                            
                            $subtotal = ($unitPrice * $qty) + $additionalCost;

                            SaleDetail::create([
                                'sale_id' => $sale->id,
                                'product_variant_id' => $variant->id,
                                'product_name' => $variant->product->name . ' (' . $variant->material . ' ' . $variant->measurements . ')',
                                'quantity' => $qty,
                                'chosen_color' => $possibleColors[array_rand($possibleColors)],
                                'custom_notes' => $notes,
                                'additional_cost' => $additionalCost,
                                'unit_price' => $unitPrice,
                                'subtotal' => $subtotal,
                                'created_at' => $date,
                                'updated_at' => $date,
                            ]);
                            $totalSale += $subtotal;
                        }

                        $paidAmount = 0;
                        if ($stage === 'confirmado') $paidAmount = $totalSale * 0.50; 
                        elseif ($stage === 'entregado' || $stage === 'enviado') $paidAmount = $totalSale;

                        $sale->update(['total' => $totalSale, 'paid_amount' => $paidAmount]);
                    }
                });
            } else {
                echo "⚠️ Faltan datos para simular ventas (clientes o variantes vacíos).\n";
            }
        } else {
            echo "⏭️ Omitiendo simulación de ventas (entorno limpio).\n";
        }
        
        echo "✅ ¡Base de datos de Taller 360 reestructurada y lista!\n";
    }
}