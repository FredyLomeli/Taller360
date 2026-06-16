# 🗺️ GUÍA DE RUTA — TALLER 360
**Actualizada:** Junio 2026 — Auditoría de código + visión de negocio ampliada

---

## Estado actual en una línea
> El sistema base (ventas, POS, inventario simple, pagos) está sólido. Lo que sigue es construir la capa de **producción semanal, trazabilidad por pieza, embarques, dashboards especializados y catálogo público** — el negocio real es más rico que lo que el sistema modela hoy.

---

## Mapa completo de fases

```
🔴 FASE 1 — Deuda técnica crítica          (1-2 semanas)
🟠 FASE 2 — Producción semanal y embarques  (el bloque más grande, varias semanas)
🟡 FASE 3 — Dashboards especializados       (depende de datos de Fase 2)
🟢 FASE 4 — Catálogo público + link cliente (puede ir en paralelo a Fase 2/3)
🔵 FASE 5 — Precios dinámicos por flete     (depende de Fase 4, diseño abierto)
🟣 FASE 6 — Reportes PDF                    (actividad final)
```

**Regla práctica:** completa Fase 1 siempre primero. Después, Fase 2 y Fase 4 pueden trabajarse en paralelo si tienes ayuda, porque no dependen una de la otra.

---

# 🔴 FASE 1 — Deuda Técnica Crítica

## TAREA 1.1 — Fix Bug Logout "Inception"
**Tiempo estimado:** 10 minutos | **Archivo:** `resources/js/bootstrap.js`

### Qué hace este bug
Cuando la sesión expira, Laravel devuelve 419 e Inertia intenta renderizar el login dentro de la página actual.

### Fix
```javascript
// Agregar al final de bootstrap.js
axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response?.status === 419) {
            window.location.href = '/login';
        }
        return Promise.reject(error);
    }
);
```

### Cómo probar
Borra la cookie de sesión desde DevTools, haz clic en algo, debe redirigir limpio a `/login`.

---

## TAREA 1.2 — Edición de Usuarios
**Tiempo estimado:** 1-2 horas | **Archivos:** `UserController.php` + `Users/Edit.vue` (nuevo)

### Paso 1 — Métodos en el controlador
```php
use Illuminate\Validation\Rules;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;

public function edit(User $user)
{
    return Inertia::render('Users/Edit', ['user' => $user]);
}

public function update(Request $request, User $user)
{
    $validated = $request->validate([
        'name'  => 'required|string|max:255',
        'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
        'role'  => 'required|in:admin,vendedor,produccion',
        'password' => $request->filled('password')
            ? ['confirmed', Rules\Password::defaults()]
            : ['nullable'],
    ]);

    $user->update([
        'name'     => $validated['name'],
        'email'    => $validated['email'],
        'role'     => $validated['role'],
        'password' => $request->filled('password')
            ? Hash::make($validated['password'])
            : $user->password,
    ]);

    return redirect()->route('users.index')->with('success', 'Usuario actualizado.');
}
```
> Nota: ya incluye `produccion` como rol válido, pensando en la Tarea 1.4.

### Paso 2 — Vista `Users/Edit.vue`
Igual a `Create.vue` pero con datos precargados y password opcional.

### Paso 3 — Link en `Users/Index.vue`
```html
<Link :href="route('users.edit', user.id)" class="text-blue-600 hover:text-blue-800 font-bold text-xs uppercase mr-3">
    Editar
</Link>
```

---

## TAREA 1.3 — Limpiar HTML en `Sales/Index.vue`
**Tiempo estimado:** 15 minutos
Revisar el modal de detalle y corregir el `</div>` mal ubicado en la sección de botones. No afecta funcionalidad, pero conviene dejarlo limpio antes de seguir construyendo encima.

---

## TAREA 1.4 — Tercer Rol: "Producción"
**Tiempo estimado:** 1 día completo (toca varias vistas)

### Qué hace esta tarea
Crea un perfil de usuario que solo ve cantidades, fechas y especificaciones técnicas — nunca dinero.

### Paso 1 — Middleware
`CheckRole.php` ya soporta cualquier string de rol porque compara dinámicamente. No requiere cambio. Solo hay que usarlo en las rutas nuevas:
```php
// web.php
Route::middleware('role:produccion')->group(function () {
    Route::get('/produccion/plan', [ProductionController::class, 'index'])->name('production.plan');
    // más rutas de producción aquí cuando existan (Fase 2)
});
```

### Paso 2 — Ocultar dinero en vistas compartidas
En cada vista donde un usuario de producción podría entrar (ej. `Sales/Show.vue` si llega a verlo), envolver las secciones financieras:
```html
<div v-if="$page.props.auth.user.role !== 'produccion'">
    <!-- Total, pagos, saldo pendiente -->
</div>
```
> Aquí es donde `HandleInertiaRequests.php` compartiendo `auth.user` globalmente se vuelve muy útil — no hay que pasar el rol como prop manualmente en cada controlador.

### Paso 3 — Sidebar condicional
En el layout principal, mostrar/ocultar links de navegación según `auth.user.role`:
```javascript
const role = computed(() => usePage().props.auth.user.role);
```

### Cómo probar
Crear un usuario con rol `produccion`, iniciar sesión, confirmar que el Dashboard, POS y Clientes no son accesibles (403) y que el Plan de Producción se ve sin precios.

---

# 🟠 FASE 2 — Producción Semanal y Embarques

> Este es el bloque de trabajo más grande. Se recomienda dividirlo en sesiones separadas, una por sub-tarea, y no intentar todo de una sola vez.

## TAREA 2.1 — Plan de Producción Semanal

### Qué hace esta tarea
Filtrar el reporte de producción por semana usando `promised_date`, en lugar de mostrar todo el acumulado.

### Paso 1 — Controlador
```php
// ProductionController.php
public function index(Request $request)
{
    $startWeek = $request->input('start_date')
        ? Carbon::parse($request->input('start_date'))->startOfWeek()
        : Carbon::now()->startOfWeek();
    $endWeek = $startWeek->copy()->endOfWeek();

    $items = SaleDetail::whereHas('sale', function ($query) use ($startWeek, $endWeek) {
            $query->where('stage', 'produccion')
                  ->whereBetween('promised_date', [$startWeek, $endWeek]);
        })
        ->with(['productVariant.product', 'sale:id,client_id,promised_date', 'sale.client:id,name'])
        ->get();

    // ... mismo agrupamiento que ya existe

    return Inertia::render('Production/Index', [
        'productionQueue' => $grouped,
        'weekRange' => ['start' => $startWeek->format('Y-m-d'), 'end' => $endWeek->format('Y-m-d')],
    ]);
}
```

### Paso 2 — Selector de semana en la vista
Agregar controles "Semana anterior / Semana siguiente" en `Production/Index.vue`, similares al filtro de fechas que ya existe en el Dashboard.

### Cómo probar
Crear pedidos con distintas `promised_date` en semanas diferentes, confirmar que el plan solo muestra los de la semana seleccionada.

---

## TAREA 2.2 — Registro de Piezas Terminadas

### Qué hace esta tarea
Permite que producción marque, pieza por pieza, qué ya se fabricó — y eso entra al inventario aunque el pedido siga en `'produccion'`.

### Paso 1 — Migración
```bash
php artisan make:migration create_production_completions_table
```
```php
$table->id();
$table->foreignId('sale_detail_id')->constrained()->onDelete('cascade');
$table->integer('quantity_completed');
$table->foreignId('user_id')->constrained();
$table->timestamp('completed_at');
$table->timestamps();
```

### Paso 2 — Modelo `ProductionCompletion`
Relación `belongsTo(SaleDetail)` y `belongsTo(User)`.

### Paso 3 — Lógica en el controlador
Al registrar una finalización:
```php
DB::transaction(function () use ($saleDetail, $quantity, $userId) {
    ProductionCompletion::create([
        'sale_detail_id' => $saleDetail->id,
        'quantity_completed' => $quantity,
        'user_id' => $userId,
        'completed_at' => now(),
    ]);

    // Entra al inventario de producto terminado
    $saleDetail->variant->increment('stock', $quantity);
});
```

### Paso 4 — Vista para Producción
En `Production/Index.vue` (o una vista nueva `Production/Completions.vue`), por cada línea de pedido mostrar un input para capturar cuántas piezas se completaron y un botón "Registrar".

### Cómo probar
Marcar 3 de 5 roperos de un pedido como terminados, confirmar que el stock de esa variante sube en 3, y que el pedido sigue en `stage = produccion`.

---

## TAREA 2.3 — Envíos Parciales (Trazabilidad por Línea)

### Qué hace esta tarea
Permite que una venta se envíe en partes, rastreando cuánto de cada línea ya salió.

### Paso 1 — Migración
```bash
php artisan make:migration add_shipped_quantity_to_sale_details_table
```
```php
$table->integer('shipped_quantity')->default(0);
```

### Paso 2 — Regla de negocio nueva
Una venta se considera completamente enviada cuando, para cada `sale_detail`, `shipped_quantity == quantity`. Si solo algunas líneas están completas, la venta queda marcada con `is_partial_shipping = true` (campo que ya existe en la tabla `sales` pero no se usa).

```php
// Método helper en el modelo Sale
public function isFullyShipped(): bool
{
    return $this->details->every(fn($d) => $d->shipped_quantity >= $d->quantity);
}
```

### Paso 3 — Actualizar `updateStage` en `SaleController`
Antes de marcar `stage = 'enviado'` completo, verificar si es parcial:
```php
if ($sale->isFullyShipped()) {
    $sale->update(['stage' => 'enviado', 'is_partial_shipping' => false]);
} else {
    $sale->update(['is_partial_shipping' => true]); // El stage puede quedarse en 'produccion' o pasar a un estado intermedio
}
```

> **Nota de diseño:** aquí conviene decidir si se agrega un nuevo valor al enum `stage` (ej. `'envio_parcial'`) o si basta con la bandera `is_partial_shipping`. Se recomienda agregar el valor al enum para que sea visible en el Kanban como su propia columna.

### Cómo probar
Pedido con 5 piezas, enviar 2, confirmar que `is_partial_shipping = true` y el pedido no pasa a `'enviado'` completo hasta enviar las 3 restantes.

---

## TAREA 2.4 — Embarques como Entidad Propia

### Qué hace esta tarea
Agrupa piezas de varias ventas distintas en un solo viaje de la camioneta.

### Paso 1 — Migraciones
```bash
php artisan make:migration create_shipments_table
php artisan make:migration create_shipment_items_table
```
```php
// shipments
$table->id();
$table->timestamp('shipped_at');
$table->string('driver_name')->nullable();
$table->text('notes')->nullable();
$table->foreignId('user_id')->constrained(); // quién armó el embarque
$table->timestamps();

// shipment_items (pivote)
$table->id();
$table->foreignId('shipment_id')->constrained()->onDelete('cascade');
$table->foreignId('sale_detail_id')->constrained();
$table->integer('quantity');
$table->timestamps();
```

### Paso 2 — Modelos
`Shipment` con `hasMany(ShipmentItem)`. `ShipmentItem` con `belongsTo(Shipment)` y `belongsTo(SaleDetail)`.

### Paso 3 — Controlador `ShipmentController`
- `create()` → muestra piezas disponibles para embarcar (las que tienen `production_completions` registradas y `shipped_quantity < quantity`)
- `store()` → crea el embarque, sus items, y actualiza `shipped_quantity` en cada `sale_detail` correspondiente (reutilizar la lógica de la Tarea 2.3)

### Paso 4 — Vista `Shipments/Create.vue`
Lista de piezas listas para embarcar, con checkbox/cantidad para seleccionar cuáles van en este viaje. Al confirmar, genera el embarque.

### Paso 5 — Ticket de embarque imprimible
Similar al `Production/Index.vue` pero mostrando qué va en ESTE viaje específico, para que el chofer lo lleve impreso.

### Cómo probar
Tomar piezas de 2 ventas distintas, armar un embarque, confirmar que `shipped_quantity` se actualiza en ambas ventas y que el ticket imprime correctamente.

---

# 🟡 FASE 3 — Dashboards Especializados

## TAREA 3.1 — Dashboard de Producción
**Depende de Fase 2 completa** (necesita `production_completions` y `shipments` para tener datos reales).

Nueva vista o sección sin una sola cifra de dinero:
- Piezas actualmente en producción (agrupado, igual que Production/Index pero como resumen)
- Piezas terminadas y listas para embarcar (join entre `production_completions` y `shipped_quantity < quantity`)
- Fechas compromiso próximas a vencer (`promised_date` ordenado ascendente)

## TAREA 3.2 — Dashboard Financiero
Separado del operativo. Debe incluir:
```php
// Cartera vencida
$overdue = Sale::where('promised_date', '<', now())
    ->whereColumn('paid_amount', '<', 'total')
    ->whereNotIn('stage', ['cancelado'])
    ->with('client')
    ->get();
```
Más el reporte de ingresos por rango de fechas que ya existe parcialmente en `DashboardController`.

## TAREA 3.3 — Selector de Vista para Admin
Tabs en la parte superior del Dashboard: "Ventas" / "Producción" / "Financiero" / "Todo". Solo el admin ve las 4 opciones; vendedor y producción ven solo la suya, fija, sin selector.

---

# 🟢 FASE 4 — Catálogo Público y Link por Cliente

## TAREA 4.1 — Catálogo Público
Rutas nuevas, **sin** middleware `auth`:
```php
// web.php — fuera del grupo auth
Route::get('/catalogo', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/catalogo/{category}', [CatalogController::class, 'byCategory'])->name('catalog.category');
Route::get('/catalogo/producto/{product}', [CatalogController::class, 'show'])->name('catalog.show');
```
El controlador nuevo `CatalogController` reutiliza los modelos `Product`, `Category`, `ProductVariant` pero el `select()` o el `resource`/transformación **nunca debe incluir `price_1` a `price_5`**.

Diseño visual: hero banner, categorías en tarjetas grandes, grid de productos con imagen y selector de color/material (sin precio), inspirado en el estilo de tienda de muebles que revisamos.

## TAREA 4.2 — Link Personalizado por Cliente
### Paso 1 — Migración
```bash
php artisan make:migration add_catalog_token_to_clients_table
```
```php
$table->string('catalog_token')->unique()->nullable();
```
Generar el token al crear el cliente (`Str::random(32)` o `Str::uuid()`).

### Paso 2 — Ruta y controlador
```php
Route::get('/catalogo/cliente/{token}', [CatalogController::class, 'byClientToken'])->name('catalog.client');
```
Esta vista sí incluye precios, calculados según `price_tier` del cliente encontrado por el token.

### Paso 3 — Botón en `Clients/Index.vue`
"Copiar link personalizado" que construya la URL completa y la copie al portapapeles (similar a cómo otros sistemas comparten links de invitación).

---

# 🔵 FASE 5 — Precios Dinámicos por Flete
**Diseño cerrado en conversación, construcción pendiente. No prioritaria.**

1. Agregar `unidades_por_flete` a `product_variants` o `products`.
2. Crear tabla `distance_zones` (nombre, km_min, km_max, costo_base_flete).
3. Agregar `distance_km` a `clients`, con asignación automática de zona al guardar.
4. Calcular precio final dinámicamente: `precio_base + (costo_base_flete_zona / unidades_por_flete)`.
5. Pendiente de decidir: automatización del cálculo de `distance_km` (Google Maps Distance Matrix API es la opción más directa cuando se decida automatizar).

---

# 🟣 FASE 6 — Reportes PDF
**Actividad final**, depende de que Fase 3 ya tenga los datos consolidados.
- Reporte financiero (ingresos por rango)
- Reporte de cartera vencida
- Reporte de producción semanal (PDF de lo que ya se ve en pantalla)
- Reporte de embarque (qué salió, cuándo, en qué viaje)

---

## Cómo usar esta guía con Claude

Al iniciar sesión para trabajar en una fase específica:
```
"Voy a trabajar en [Fase X.X - Nombre]. 
Contexto del proyecto:" → [pegar CONTEXTO_TECNICO.md]
"Archivos relevantes:" → [pegar los archivos que la tarea indica]
```

Para las fases grandes (2 y 4), considera abrir una sesión nueva por cada sub-tarea (2.1, 2.2, 2.3, 2.4 por separado) en lugar de intentar toda la fase de una vez — son cambios que tocan varias capas (migración, modelo, controlador, vista) y es más fácil revisar y probar por partes.