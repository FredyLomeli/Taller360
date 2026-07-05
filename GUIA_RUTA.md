# 🗺️ GUÍA DE RUTA — TALLER 360
**Actualizada:** Julio 2026

---

## Estado actual en una línea
> El sistema base, el módulo de seguridad/roles y el **módulo completo de logística y embarques parciales** ya están sólidos. Lo que sigue es el filtro semanal de producción (Tarea 2.1) y los dashboards especializados (Fase 3).

---

## Mapa de fases

```
✅ FASE 0 — Seguridad y dependencias       (COMPLETADA)
✅ FASE 1 — Deuda técnica y roles          (COMPLETADA)
🟠 FASE 2 — Producción y embarques         (90% — Falta solo filtro semanal 2.1)
🟡 FASE 3 — Dashboards especializados      (Siguiente enfoque)
🟢 FASE 4 — Catálogo público + link        (Pendiente, paralelizable)
🔵 FASE 5 — Precios dinámicos por flete    (Pendiente)
🟣 FASE 6 — Reportes PDF                   (Actividad final)
```

---

## PRÓXIMA TAREA: 2.1 — Filtro Semanal en Plan de Producción
**Tiempo estimado:** 2-3 horas | **Prioridad:** Alta

### Archivos a tocar
```
app/Http/Controllers/ProductionController.php
resources/js/Pages/Production/Index.vue
```

### Paso 1 — Controlador
```php
use Carbon\Carbon;

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

    // ... mismo agrupamiento que ya existe ...

    return Inertia::render('Production/Index', [
        'productionQueue' => $grouped,
        'weekRange' => [
            'start' => $startWeek->format('Y-m-d'),
            'end'   => $endWeek->format('Y-m-d')
        ],
        'filters' => ['start_date' => $startWeek->format('Y-m-d')]
    ]);
}
```

### Paso 2 — Vista
Agregar botones "← Semana anterior" / "Semana siguiente →" en `Production/Index.vue`, similar al filtro de fechas del Dashboard:
```javascript
const goToWeek = (offset) => {
    const current = new Date(props.weekRange.start);
    current.setDate(current.getDate() + (offset * 7));
    router.get(route('production.plan'), {
        start_date: current.toISOString().split('T')[0]
    }, { preserveState: true });
};
```

### Cómo probar
Crear pedidos con `promised_date` en semanas distintas, confirmar que el plan solo muestra los de la semana seleccionada.

---

## FASE 3 — Dashboards Especializados

### 3.1 Dashboard de Producción
**Archivos nuevos:** vista Vue + sección en `DashboardController`

El controlador ya tiene la estructura `if admin / elseif vendedor / else (otros roles)`. El caso `else` hoy muestra "Bienvenido". Para `produccion` y `supervisor`, en lugar de eso mostrar:

```php
elseif (in_array($user->role, ['produccion', 'supervisor'])) {
    // Piezas en taller
    $inProduction = SaleDetail::whereHas('sale', fn($q) => $q->where('stage', 'produccion'))->count();

    // Terminadas y listas para embarcar (en production_completions pero no en sale_deliveries)
    $readyToShip = ProductionCompletion::whereDoesntHave('saleDetail.saleDeliveries')->sum('quantity_completed');

    // Fechas compromiso próximas
    $upcomingDates = Sale::where('stage', 'produccion')
        ->orderBy('promised_date')
        ->take(10)
        ->get(['id', 'promised_date', 'client_id']);

    return Inertia::render('Dashboard', [
        'isAdmin' => false,
        'isProduccion' => true,
        'kpis' => ['in_production' => $inProduction, 'ready_to_ship' => $readyToShip],
        'upcomingDates' => $upcomingDates,
        'filters' => [...]
    ]);
}
```

### 3.2 Dashboard Financiero
```php
elseif ($user->role === 'financiero') {
    // Cartera vencida
    $overdue = Sale::where('promised_date', '<', now())
        ->whereColumn('paid_amount', '<', 'total')
        ->whereNotIn('stage', ['cancelado', 'entregado'])
        ->with('client')
        ->get();

    return Inertia::render('Dashboard', [
        'isAdmin' => false,
        'isFinanciero' => true,
        'overdueAccounts' => $overdue,
        'kpis' => [...] // ingresos del periodo
    ]);
}
```

### 3.3 Sidebar condicional
En `AuthenticatedLayout.vue`, usar `$page.props.auth.user.role` para ocultar links:
```html
<!-- Solo admin y vendedor ven Ventas/POS -->
<template v-if="['admin', 'vendedor'].includes($page.props.auth.user.role)">
    <Link :href="route('sales.index')">Reporte de Ventas</Link>
    <Link :href="route('sales.create')">Punto de Venta</Link>
</template>

<!-- Solo admin ve Configuración y Usuarios -->
<template v-if="$page.props.auth.user.role === 'admin'">
    <Link :href="route('settings.index')">Configuración</Link>
    <!-- submenú usuarios... -->
</template>
```

---

## FASE 4 — Catálogo Público

### 4.1 Nuevas rutas (fuera del grupo `auth`)
```php
// En web.php, ANTES del grupo auth:
Route::get('/catalogo', [CatalogController::class, 'index'])->name('catalog.index');
Route::get('/catalogo/{category:name}', [CatalogController::class, 'byCategory'])->name('catalog.category');
Route::get('/catalogo/producto/{product}', [CatalogController::class, 'show'])->name('catalog.show');
Route::get('/catalogo/cliente/{token}', [CatalogController::class, 'byClientToken'])->name('catalog.client');
```

### 4.2 Token de cliente
```bash
php artisan make:migration add_catalog_token_to_clients_table
```
```php
$table->string('catalog_token', 32)->unique()->nullable();
// Generar en ClientController@store: 'catalog_token' => Str::random(32)
```

### 4.3 CatalogController (nunca exponer precios sin token)
```php
public function index()
{
    $products = Product::with(['category', 'variants:id,product_id,material,stock'])
        ->select('id', 'category_id', 'name', 'measurements', 'image', 'is_favorite')
        // NUNCA incluir price_1..price_5 aquí
        ->get();

    return Inertia::render('Catalog/Index', compact('products'));
}

public function byClientToken(string $token)
{
    $client = Client::where('catalog_token', $token)->firstOrFail();
    $priceField = 'price_' . $client->price_tier;

    $products = Product::with(['variants:id,product_id,material,stock,' . $priceField])->get();

    return Inertia::render('Catalog/Index', [
        'products' => $products,
        'clientTier' => $client->price_tier,
        'showPrices' => true,
    ]);
}
```

---

## Cómo usar esta guía con Claude

Al iniciar sesión nueva:
```
"Voy a trabajar en [Fase X.X - Nombre].
Contexto:" → [pegar CONTEXTO_TECNICO.md]
"Archivos relevantes:" → [pegar archivos de esa tarea]
```

Para fases grandes, abrir sesión dedicada por sub-tarea.