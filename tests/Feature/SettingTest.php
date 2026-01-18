<?php

use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('un administrador puede actualizar la informacion de la empresa', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    
    // Simulamos enviar el formulario de configuración
    $this->actingAs($admin)->post(route('settings.update'), [
        'company_name' => 'Muebles Finos SA',
        'company_phone' => '555-9999',
        'allow_negative_stock' => '0', // Probamos cambiar la config de stock
    ])->assertRedirect(); // O assertStatus(200) dependiendo de tu controlador

    // Verificamos que en la BD se hayan actualizado las claves
    $this->assertDatabaseHas('settings', ['key' => 'company_name', 'value' => 'Muebles Finos SA']);
    $this->assertDatabaseHas('settings', ['key' => 'allow_negative_stock', 'value' => '0']);
});

test('un administrador puede subir un logotipo nuevo', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    
    // Falsificamos el disco 'public' para no llenar tu carpeta real de basura
    Storage::fake('public');
    $logo = UploadedFile::fake()->image('nuevo_logo.jpg');

    $this->actingAs($admin)->post(route('settings.update'), [
        'company_logo' => $logo,
        // Mandamos otros datos requeridos para que no falle la validación
        'company_name' => 'Test Company' 
    ]);

    // Verificamos que el archivo se guardó en el disco falso
    // Nota: Ajusta 'logos/' si tu controlador lo guarda en otra subcarpeta
    // Laravel suele guardar con un hash, así que verificamos que exista algun archivo en la carpeta.
    $this->assertTrue(count(Storage::disk('public')->allFiles('logos')) > 0);
    
    // Verificamos que la BD tenga la ruta
    $setting = Setting::where('key', 'company_logo')->first();
    expect($setting->value)->not->toBeNull();
});

test('un vendedor NO puede acceder a la configuracion', function () {
    $vendedor = User::factory()->create(['role' => 'vendedor']);
    
    $this->actingAs($vendedor)
         ->get(route('settings.index'))
         ->assertStatus(403); // Prohibido
         
    $this->actingAs($vendedor)
         ->post(route('settings.update'), ['company_name' => 'Hacker'])
         ->assertStatus(403);
});