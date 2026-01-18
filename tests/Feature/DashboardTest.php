<?php

use App\Models\User;
use App\Models\Sale;
use Inertia\Testing\AssertableInertia;

test('el administrador ve los KPIs globales y la tabla de vendedores', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    
    // Venta pagada de $1000
    Sale::factory()->create(['total' => 1000, 'paid_amount' => 1000, 'created_at' => now()]);

    $this->actingAs($admin)
         ->get(route('dashboard'))
         ->assertInertia(fn (AssertableInertia $page) => $page
             ->component('Dashboard')
             ->where('isAdmin', true)
             // TRUCO: Usamos una función para validar el valor numérico sin importar si es texto o número
             ->where('kpis.income', fn ($val) => (float)$val === 1000.0) 
             ->where('kpis.tickets', 1)
             ->has('sellersStats') 
             ->has('lowStockProducts')
         );
});

test('el vendedor ve SOLO sus propios numeros y NO ve estadisticas globales', function () {
    $vendedor = User::factory()->create(['role' => 'vendedor']);
    $otroVendedor = User::factory()->create(['role' => 'vendedor']);

    // Venta del vendedor ($200)
    Sale::factory()->create(['user_id' => $vendedor->id, 'paid_amount' => 200, 'created_at' => now()]);
    
    // Venta de OTRO ($5000)
    Sale::factory()->create(['user_id' => $otroVendedor->id, 'paid_amount' => 5000, 'created_at' => now()]);

    $this->actingAs($vendedor)
         ->get(route('dashboard'))
         ->assertInertia(fn (AssertableInertia $page) => $page
             ->component('Dashboard')
             ->where('isAdmin', false)
             // Validamos numéricamente
             ->where('kpis.income', fn ($val) => (float)$val === 200.0)
             ->missing('sellersStats')
             ->missing('lowStockProducts')
         );
});

test('el administrador puede filtrar por rango de fechas', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    
    $ayer = now()->subDay();
    Sale::factory()->create(['created_at' => $ayer, 'paid_amount' => 500]);
    Sale::factory()->create(['created_at' => now(), 'paid_amount' => 100]);

    $this->actingAs($admin)
         ->get(route('dashboard', [
             'start_date' => $ayer->format('Y-m-d'),
             'end_date' => $ayer->format('Y-m-d')
         ]))
         ->assertInertia(fn (AssertableInertia $page) => $page
             // Validamos numéricamente
             ->where('kpis.income', fn ($val) => (float)$val === 500.0)
             ->where('filters.start_date', $ayer->format('Y-m-d'))
         );
});