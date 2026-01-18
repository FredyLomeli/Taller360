<?php

use App\Http\Controllers\ProfileController; // <--- Faltaba importar este
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
// Controladores
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
// Facades
use Illuminate\Support\Facades\Auth;

// --- RUTA PÚBLICA (Landing Page) ---
Route::get('/', function () {
    // Si el usuario ya está logueado, lo mandamos directo al dashboard
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }
    return view('welcome');
});

// --- RUTAS AUTENTICADAS (Cualquier usuario logueado: Admin o Vendedor) ---
Route::middleware('auth')->group(function () {

    // 1. DASHBOARD
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // 2. PERFIL (Necesario para cambiar contraseña/email)
    Route::controller(ProfileController::class)->group(function () {
        Route::get('/profile', 'edit')->name('profile.edit');
        Route::patch('/profile', 'update')->name('profile.update');
        Route::delete('/profile', 'destroy')->name('profile.destroy');
    });

    // 3. PUNTO DE VENTA (POS)
    // Ruta principal para vender. Usa el index de productos pero con vista de POS.
    Route::get('/pos', [ProductController::class, 'index'])->name('sales.create');

    // 4. VENTAS (Procesar, Historial y Tickets)
    Route::controller(SaleController::class)->group(function () {
        Route::post('/sales', 'store')->name('sales.store');     // Guardar venta
        Route::get('/sales', 'index')->name('sales.index');      // Historial
        
        // Impresión y Acciones
        Route::get('/sales/{id}/ticket', 'printTicket')->name('sales.print');
        Route::get('/sales/{id}/note', 'printNote')->name('sales.printNote');
        Route::post('/sales/{id}/email', 'sendEmail')->name('sales.email');
        
        // Nota: Dejamos cancelar aquí para que el vendedor pueda corregir errores inmediatos.
        // Si quieres restringirlo, muévelo al grupo de admin abajo.
        Route::post('/sales/{id}/cancel', 'cancel')->name('sales.cancel');
    });

    // 5. CLIENTES (Lectura y Creación para Vendedores)
    // El vendedor puede crear y editar clientes, pero NO borrarlos (except 'destroy')
    Route::resource('clients', ClientController::class)->except(['destroy']);


    // ==========================================
    //      🛡️ ZONA BLINDADA (SOLO ADMIN)
    // ==========================================
    // Asegúrate que en Kernel.php el alias sea 'admin'. Si usas spatie es 'role:admin'.
    Route::middleware('role:admin')->group(function () { 

        // A. GESTIÓN DE USUARIOS (Seguridad Crítica)
        // MOVIDO AQUÍ ADENTRO para que el vendedor no pueda tocarlo.
        Route::resource('users', UserController::class);

        // B. GESTIÓN AVANZADA DE PRODUCTOS (Inventario)
        Route::controller(ProductController::class)->group(function () {
            Route::get('/inventario', 'inventory')->name('products.inventory'); // Tabla de edición
            Route::get('/productos/crear', 'create')->name('products.create');
            Route::post('/productos', 'store')->name('products.store');
            Route::get('/productos/{id}/editar', 'edit')->name('products.edit');
            Route::put('/productos/{id}', 'update')->name('products.update'); // Ojo: Usamos PUT
            Route::delete('/productos/{id}', 'destroy')->name('products.destroy'); 
            
            // Eliminar variante individual
            Route::delete('/variantes/{id}', 'destroyVariant')->name('variants.destroy');
        });

        // C. BORRAR CLIENTES (Solo admin elimina cartera)
        Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');

        // D. CONFIGURACIÓN DEL SISTEMA
        Route::controller(SettingController::class)->group(function () {
            Route::get('/configuracion', 'index')->name('settings.index');
            Route::post('/configuracion', 'update')->name('settings.update');
        });

    }); // Fin middleware admin

}); // Fin middleware auth

Route::get('/crear-symlink', function () {
    // 1. ORIGEN: Donde están los archivos reales (dentro de tu proyecto Laravel protegido)
    $target = storage_path('app/public');

    // 2. DESTINO: Donde quieres el "Acceso Directo" (Tu carpeta pública del hosting)
    // Usamos tu lógica exacta: public_path() + subir 2 niveles + entrar a public_html
    $shortcut = public_path() . '/../../public_html/storage';

    // --- BLOQUE DE DEPURACIÓN (Importante para verificar rutas) ---
    echo "<h1>Depuración de Rutas</h1>";
    echo "<b>Origen (Real):</b> " . $target . "<br>";
    echo "<b>Destino (Link):</b> " . $shortcut . "<br><br>";

    // Validamos si el destino ya existe
    if (file_exists($shortcut)) {
        return "⚠️ La carpeta o link 'storage' ya existe en public_html. Bórrala manualmente si quieres regenerarla.";
    }

    // Intentamos crear el link
    try {
        symlink($target, $shortcut);
        return "✅ ¡ÉXITO! Enlace simbólico creado. Ahora 'public_html/storage' apunta a tus archivos.";
    } catch (\Exception $e) {
        return "❌ ERROR: " . $e->getMessage();
    }
});

require __DIR__.'/auth.php';