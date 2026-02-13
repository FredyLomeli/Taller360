<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
// Controladores
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\SalePaymentController;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Web Routes (Taller 360 v2.0)
|--------------------------------------------------------------------------
*/

// --- RUTA PÚBLICA (Landing Page) ---
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return view('welcome');
});

// --- RUTAS AUTENTICADAS ---
Route::middleware(['auth', 'verified'])->group(function () {

    // 1. DASHBOARD
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // 2. PERFIL
    Route::controller(ProfileController::class)->group(function () {
        Route::get('/profile', 'edit')->name('profile.edit');
        Route::patch('/profile', 'update')->name('profile.update');
        Route::delete('/profile', 'destroy')->name('profile.destroy');
    });

    // 3. PUNTO DE VENTA (POS / Nuevo Pedido)
    // Usamos SaleController@create para el POS
    Route::get('/pos', [SaleController::class, 'create'])->name('sales.create');

    // 4. VENTAS Y PEDIDOS
    Route::controller(SaleController::class)->group(function () {
        Route::get('/sales', 'index')->name('sales.index');      // Historial
        Route::post('/sales', 'store')->name('sales.store');     // Guardar Pedido
        Route::get('/sales/{sale}', 'show')->name('sales.show'); // Ver Detalle
        
        // MOTOR DE ESTADOS (Mover pedido: Pedido -> Producción -> Enviado)
        Route::patch('/sales/{sale}/stage', 'updateStage')->name('sales.update-stage');
        
        // Impresión y Acciones Legacy
        Route::get('/sales/{id}/ticket', 'printTicket')->name('sales.print');
        Route::get('/sales/{id}/note', 'printNote')->name('sales.printNote');
        Route::post('/sales/{id}/email', 'sendEmail')->name('sales.email');
    });

    // 5. CLIENTES (Vendedores pueden ver/crear/editar)
    Route::resource('clients', ClientController::class)->except(['destroy']);

    Route::get('/production-plan', [ProductionController::class, 'index'])->name('production.plan');

    Route::post('/sales/{sale}/payment', [SalePaymentController::class, 'store'])
    ->name('sales.payment.store');

    // ==========================================
    //      🛡️ ZONA BLINDADA (SOLO ADMIN)
    // ==========================================
    Route::middleware('role:admin')->group(function () { 

        // A. GESTIÓN DE USUARIOS
        Route::resource('users', UserController::class);

        // B. GESTIÓN DE PRODUCTOS (Inventario)
        // Usamos resource para generar todas las rutas estándar:
        // products.index, create, store, edit, update, destroy
        Route::resource('products', ProductController::class);

        Route::put('products/{product}/favorite', [ProductController::class, 'toggleFavorite'])
        ->name('products.toggle-favorite');
        
        // Eliminación de variante individual (AJAX)
        // Nota: Agrega este método 'destroyVariant' en ProductController si no existe, 
        // o usa la lógica de actualización del update()
        // Route::delete('/variantes/{id}', [ProductController::class, 'destroyVariant'])->name('variants.destroy');

        // C. BORRAR CLIENTES (Privilegio Admin)
        Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');

        // D. CONFIGURACIÓN
        Route::controller(SettingController::class)->group(function () {
            Route::get('/configuracion', 'index')->name('settings.index');
            Route::post('/configuracion', 'update')->name('settings.update');
        });

    }); // Fin middleware admin

}); // Fin middleware auth

require __DIR__.'/auth.php';