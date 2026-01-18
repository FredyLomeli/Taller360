<?php
// tests/Feature/AuthTest.php

use App\Models\User;

test('un usuario no autenticado es redirigido al login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('un administrador puede acceder al modulo de usuarios', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
         ->get(route('users.index'))
         ->assertStatus(200); // 200 OK
});

test('un vendedor NO puede acceder al modulo de usuarios', function () {
    $vendedor = User::factory()->create(['role' => 'vendedor']);

    $this->actingAs($vendedor)
         ->get(route('users.index'))
         ->assertStatus(403); // 403 Forbidden (Prohibido)
});

test('un vendedor SI puede acceder al POS', function () {
    $vendedor = User::factory()->create(['role' => 'vendedor']);
    
    // Asumiendo que tu ruta del POS es /dashboard o /sales/create
    $this->actingAs($vendedor)
         ->get('/dashboard') 
         ->assertStatus(200);
});

test('un invitado intenta entrar a rutas protegidas y es enviado al login', function () {
    // Intenta entrar al POS
    $this->get('/dashboard')->assertRedirect(route('login'));
    
    // Intenta entrar a Usuarios
    $this->get('/users')->assertRedirect(route('login'));
    
    // Intenta entrar a Configuración
    $this->get('/configuracion')->assertRedirect(route('login'));
});

test('la pagina publica (landing) es accesible sin login', function () {
    $this->get('/')->assertStatus(200);
});

test('un vendedor no puede ver el inventario administrativo', function () {
    $vendedor = User::factory()->create(['role' => 'vendedor']);
    
    // La ruta 'products.inventory' es la tabla de edición
    $this->actingAs($vendedor)
         ->get(route('products.inventory'))
         ->assertStatus(403);
});

test('un vendedor SI puede ver el listado simple de productos para vender', function () {
    $vendedor = User::factory()->create(['role' => 'vendedor']);
    
    // CAMBIO: Usamos 'sales.create' porque esa es la ruta que muestra los productos en tu sistema
    $this->actingAs($vendedor)
         ->get(route('sales.create')) 
         ->assertStatus(200);
});