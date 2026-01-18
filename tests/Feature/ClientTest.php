<?php
// tests/Feature/ClientTest.php

use App\Models\User;
use App\Models\Client;
use App\Models\Sale;
use Inertia\Testing\AssertableInertia;

test('se puede crear un cliente nuevo', function () {
    $user = User::factory()->create();
    
    $this->actingAs($user)->post(route('clients.store'), [
        'name' => 'Juan Perez',
        'email' => 'juan@test.com',
        'phone' => '1234567890',
        'address' => 'Calle Falsa 123',
        'price_tier' => 1
    ])->assertRedirect();

    $this->assertDatabaseHas('clients', ['email' => 'juan@test.com']);
});

test('no se puede crear cliente con email repetido', function () {
    $user = User::factory()->create();
    Client::factory()->create(['email' => 'juan@test.com']); // Ya existe

    $response = $this->actingAs($user)->post(route('clients.store'), [
        'name' => 'Otro Juan',
        'email' => 'juan@test.com', // Repetido
    ]);

    $response->assertSessionHasErrors('email');
});

test('se puede eliminar un cliente SIN ventas', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $client = Client::factory()->create();

    $this->actingAs($user)->delete(route('clients.destroy', $client->id));

    $this->assertDatabaseMissing('clients', ['id' => $client->id]);
});

test('NO se puede eliminar un cliente CON ventas históricas', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $client = Client::factory()->create();
    
    // Simulamos una venta asociada a este cliente
    Sale::factory()->create(['client_id' => $client->id]);

    $this->actingAs($user)->delete(route('clients.destroy', $client->id));

    // El cliente debe seguir existiendo en la BD
    $this->assertDatabaseHas('clients', ['id' => $client->id]);
});

test('se cargan todos los clientes para el buscador rapido del POS', function () {
    $user = User::factory()->create();
    
    // Creamos 10 clientes
    Client::factory()->count(10)->create();

    // Entramos al POS
    $this->actingAs($user)
         ->get(route('sales.create'))
         ->assertInertia(fn (AssertableInertia $page) => $page
             ->has('clients', 10) // Deben llegar los 10
         );
});