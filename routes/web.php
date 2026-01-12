<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
// Controladores
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\SettingController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {

    Route::controller(ProfileController::class)->group(function () {
        Route::get('/profile', 'edit')->name('profile.edit');
        Route::patch('/profile', 'update')->name('profile.update');
        Route::delete('/profile', 'destroy')->name('profile.destroy');
    });

    Route::controller(ProductController::class)->group(function () {
        // Usamos Route::get normal porque necesitamos procesar datos primero
        Route::get('/productos', 'index')->name('products.index');
        // Ruta para ver el formulario
        Route::get('/productos/crear', 'create')->name('products.create');
        Route::get('/productos/{id}/editar', 'edit')->name('products.edit');
        // Ruta para guardar
        Route::post('/productos', 'store')->name('products.store');
        // Ruta para el Panel de Administración de Inventario
        Route::get('/inventario', 'inventory')->name('products.inventory');
        // Funciones de productos
        Route::post('/productos/{id}', 'update')->name('products.update');
        Route::delete('/productos/{id}', 'destroy')->name('products.destroy'); 
        Route::delete('/variantes/{id}', 'destroyVariant')->name('variants.destroy');
    });

    // RUTAS DE CLIENTES (Resource crea automáticamente index, create, store, edit, update, destroy)
    Route::resource('clients', ClientController::class);
    
    Route::controller(SaleController::class)->group(function () {
        Route::post('/sales', 'store')->name('sales.store');
        Route::get('/sales', 'index')->name('sales.index');
        Route::get('/sales/{id}/ticket', 'printTicket')->name('sales.print');
        Route::get('/sales/{id}/note', 'printNote')->name('sales.printNote');
        Route::post('/sales/{id}/email', 'sendEmail')->name('sales.email');
        Route::post('/sales/{id}/cancel', 'cancel')->name('sales.cancel');
    });

    Route::controller(SettingController::class)->group(function () {
        Route::get('/configuracion', 'index')->name('settings.index');
        Route::post('/configuracion', 'update')->name('settings.update');
    });
    

});








require __DIR__.'/auth.php';
