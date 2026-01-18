<?php
// tests/Feature/UserTest.php

use App\Models\User;

test('crear un usuario NO cierra la sesión del administrador actual', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    
    // 1. Iniciamos sesión como Admin
    $this->actingAs($admin);

    // 2. Creamos al Vendedor Juan
    $response = $this->post(route('users.store'), [
        'name' => 'Vendedor Juan',
        'email' => 'juan@tienda.com',
        'role' => 'vendedor',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertRedirect(route('users.index'));

    // 3. LA PRUEBA DE FUEGO:
    // Verificar que el usuario autenticado SIGUE SIENDO el Admin, no Juan.
    expect(auth()->id())->toBe($admin->id);
    
    // Y que Juan sí se creó en la BD
    $this->assertDatabaseHas('users', ['email' => 'juan@tienda.com']);
});