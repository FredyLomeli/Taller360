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
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\WorkOrderController;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Web Routes (Taller 360 v2.0)
|--------------------------------------------------------------------------
*/

// --- RUTA PÚBLICA (Catálogo / Landing Page) ---
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\LandingController;

Route::get('/', [LandingController::class, 'index'])->name('landing');
Route::get('/catalogo', [CatalogController::class, 'index'])->name('catalog.index');

// --- RUTAS AUTENTICADAS ---
Route::middleware(['auth', 'verified'])->group(function () {

    // 1. DASHBOARD (Accesible para todos los roles autenticados.
    //    El controlador decide qué mostrar según el rol.
    //    Cada rol tendrá su propia vista en la Fase 3 de la hoja de ruta.)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // 2. PERFIL
    Route::controller(ProfileController::class)->group(function () {
        Route::get('/profile', 'edit')->name('profile.edit');
        Route::patch('/profile', 'update')->name('profile.update');
        Route::delete('/profile', 'destroy')->name('profile.destroy');
    });

    // ==========================================
    //    💰 ZONA DE VENTAS (ADMIN + VENDEDOR)
    // ==========================================
    Route::middleware('role:admin,vendedor')->group(function () {

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
            
            // Estado Detallado
            Route::post('/sale-details/{detail}/detallado', 'sendToDetallado')->name('sale-details.detallado');

            // Impresión y Acciones Legacy
            Route::get('/sales/{id}/ticket', 'printTicket')->name('sales.print');
            Route::get('/sales/{id}/note', 'printNote')->name('sales.printNote');
            Route::post('/sales/{id}/email', 'sendEmail')->name('sales.email');

        });

        // 5. CLIENTES (Vendedores pueden ver/crear/editar)
        Route::resource('clients', ClientController::class)->except(['destroy']);

        Route::post('/sales/{sale}/payment', [SalePaymentController::class, 'store'])
        ->name('sales.payment.store');

    });

    // ==========================================
    //   🏭 ZONA DE TALLER (ADMIN + PRODUCCIÓN)
    // ==========================================
    // El rol "produccion" solo necesita esta pantalla por ahora.
    // Cuando se construyan las tareas de Fase 2 (piezas terminadas, embarques),
    // sus rutas nuevas se agregan aquí mismo, dentro de este mismo grupo.
    Route::middleware('role:admin,produccion,supervisor')->group(function () {
        Route::get('/production-plan', [ProductionController::class, 'index'])->name('production.plan');
        Route::post('/production-plan/complete', [ProductionController::class, 'storeCompletion'])->name('production.complete');
        Route::get('/production-plan/print', [ProductionController::class, 'printReport'])->name('production.print');

        Route::post('/work-orders', [WorkOrderController::class, 'store'])->name('work-orders.store');
        Route::patch('/sale-details/{detail}/release-hold', [WorkOrderController::class, 'releaseHold'])->name('sale-details.release-hold');
    });

    // ==========================================
    //    📋 ZONA DE INVENTARIO (ADMIN + SUPERVISOR)
    // ==========================================
    Route::middleware('role:admin,supervisor')->group(function () {
        // B. GESTIÓN DE PRODUCTOS (Inventario)
        // Usamos resource para generar todas las rutas estándar:
        // products.index, create, store, edit, update, destroy
        Route::resource('products', ProductController::class);

        Route::put('products/{product}/favorite', [ProductController::class, 'toggleFavorite'])
        ->name('products.toggle-favorite');
    });

    // ==========================================
    //      🛡️ ZONA BLINDADA (SOLO ADMIN)
    // ==========================================
    Route::middleware('role:admin')->group(function () { 

        // A. GESTIÓN DE USUARIOS
        Route::resource('users', UserController::class);
        
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

    Route::middleware('role:admin,inventario,supervisor')->group(function () {
        Route::controller(ShipmentController::class)->group(function () {
            Route::get('/shipments/create','create')->name('shipments.create');
            Route::post('/shipments', 'store')->name('shipments.store');
            Route::get('/shipments', 'index')->name('shipments.index');
            Route::patch('/shipments/{id}/confirm', 'confirmDelivery')->name('shipments.confirm');
            Route::get('/shipments/{id}/print', 'printManifest')->name('shipments.print');
            Route::get('/shipments/{id}', 'show')->name('shipments.show');
            Route::patch('/shipments/{id}/cancel', 'cancel')->name('shipments.cancel'); 
        });
    });
}); // Fin middleware auth

require __DIR__.'/auth.php';