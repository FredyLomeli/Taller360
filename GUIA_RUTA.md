# 🗺️ GUÍA DE RUTA — TALLER 360
**Actualizada:** Julio 2026 (post-auditoría + sesión de implementación en vivo)

---

## Estado actual en una línea
> Se implementó y probó en vivo el Bug #1 (Plan de Producción) más 4 bugs nuevos encontrados en Embarques al probar (inventario negativo, validación de stock compartido en el frontend, filtro de clientes que no se ejecutaba, y carga innecesaria de firma/precios). Los bugs originales #2, #3, #4 y #5 (doble descuento de stock, `storeDelivery` roto, rol en Embarques, cancelación) **siguen pendientes**. Detalle completo en `BACKLOG.md`, sección "Sesión de Implementación en Vivo".

## Flujo de Git para lo que sigue (lanzamiento: miércoles)
- Trabajar en una rama separada para el resto de estos fixes (`fix/produccion-embarques-inventario` o similar), aunque no sea la costumbre — son cambios de dinero/inventario a días de producción, y una rama da margen para probar el checklist completo antes de tocar `master`.
- Separar en commits por bug (no un commit único del día) — si algo falla en la prueba final, se puede revertir un commit puntual sin afectar el resto.
- Después del lanzamiento, retomar el hábito normal de trabajar en `master` si así lo prefiere el equipo — esto aplica solo a esta ventana de cambios críticos.

---

## Mapa de fases (reordenado por prioridad real)

```
✅ FASE 0 — Seguridad y dependencias         (COMPLETADA)
✅ FASE 1 — Deuda técnica y roles            (COMPLETADA)
🟠 FASE 2 — Producción y embarques           (95% — filtro semanal YA está)
🆘 BUGS CRÍTICOS — Producción/Stock/Embarques (SIGUIENTE ENFOQUE — antes de todo lo demás)
🟢 FASE 2.5 — Mejoras a Embarques            (filtro cliente + notas individuales)
🟡 FASE 3 — Dashboards + Roles completos     (depende de que los bugs estén resueltos)
🟢 FASE 4 — Catálogo público + link          (verificar avance ya existente en '/')
🔵 FASE 5 — Precios dinámicos por flete      (Pendiente)
🟣 FASE 6 — Reportes PDF                     (Actividad final)
```

---

## PRÓXIMA TAREA (revisada): Corregir los 3 bugs críticos de Producción/Stock

**Tiempo estimado:** 3-5 horas | **Prioridad:** Alta — bloquea Fase 3

### Bug #1 — Plan de Producción no descuenta correctamente lo ya fabricado

**Archivo:** `app/Http/Controllers/ProductionController.php`, método `index()`

**Paso 1 — Cargar el histórico de fabricación (falta esta línea, ya existe en `printReport()`):**
```php
$items = SaleDetail::whereHas('sale', function ($query) use ($endWeek) {
        $query->where('stage', 'produccion')
              ->where(function($q) use ($endWeek) {
                  $q->whereDate('promised_date', '<=', $endWeek)
                    ->orWhereNull('promised_date');
              });
    })
    ->withSum('completions as completed_quantity', 'quantity_completed')   // ← AGREGAR
    ->with(['variant.product', 'sale:id,client_id,promised_date', 'sale.client:id,name'])
    ->get()
    ->sortBy(function ($item) {
        return $item->sale->promised_date ?? '9999-12-31';
    });
```

**Paso 2 — Corregir la fórmula de pendientes (ya no debe restar stock):**
```php
->map(function ($group) {
    $totalNeeded = $group->sum('quantity');
    $totalCompleted = $group->sum('completed_quantity') ?? 0;

    return [
        'name' => $group->first()->product_name,
        'material' => $group->first()->variant->material ?? 'Estándar',
        'total_quantity' => $totalNeeded,
        'breakdown' => $group->groupBy('chosen_color'),
        'orders' => /* igual que antes */,
        'details' => $group,
        'total_needed' => $totalNeeded,
        'total_completed' => $totalCompleted,
        'in_stock' => $group->first()->variant->stock ?? 0,   // dato aparte, informativo
        // YA NO se resta in_stock aquí:
        'pending_to_fabricate' => max(0, $totalNeeded - $totalCompleted),
    ];
});
```

**Paso 3 — En `Production/Index.vue`:** mostrar `in_stock` como una etiqueta separada ("En bodega, listo para embarcar: X"), no como parte del cálculo de "pendiente por fabricar".

### Bug #2 — Desactivar el descuento de stock duplicado en el Kanban

**Archivo:** `app/Http/Controllers/SaleController.php`, método `updateStage()`

Quitar (o dejar comentado con explicación) los bloques que descuentan/regresan stock al cambiar a `enviado`/`cancelado`:
```php
// ELIMINAR — el stock ahora se maneja exclusivamente en ShipmentController::store()
// CASO A: Salida de Almacén (Enviado) -> RESTAR STOCK
// if ($newStage === 'enviado' ...) { ... }

// CASO B: Cancelación de un pedido YA enviado -> DEVOLVER STOCK
// if ($newStage === 'cancelado' ...) { ... }
```
Dejar que `updateStage()` solo cambie `stage` (el Observer se encarga del historial). El único lugar que debe tocar `product_variants.stock` es `ShipmentController`.

⚠️ **Antes de borrar este código, confirmar con el equipo si todavía hay flujos operativos donde una venta pasa a `enviado` sin pasar por el módulo de Embarques.** Si los hay, hay que migrar ese flujo a Embarques primero, o el negocio se quedará sin forma de sacar esas piezas del inventario.

### Bug #3 — Eliminar o redirigir `storeDelivery()`

**Archivo:** `app/Http/Controllers/SaleController.php` + `routes/web.php`

- [ ] Verificar en el frontend (`Sales/Show.vue` probablemente) si hay un botón que llame a la ruta `sales.deliveries.store`.
- [ ] Si no hay ningún botón activo: eliminar el método `storeDelivery()` y la ruta.
- [ ] Si sí hay un botón activo: redirigir esa funcionalidad para que cree un `Shipment` de una sola línea en vez de escribir directo en `sale_deliveries` (reusar `ShipmentController::store()`), para no duplicar lógica de descuento de stock.

### Bugs #4 y #5 — Embarques: rol + cancelación

**Archivo:** `routes/web.php`
```php
// Envolver el bloque de Shipments (hoy sin restricción):
Route::middleware('role:admin,inventario')->group(function () {
    Route::controller(ShipmentController::class)->group(function () {
        Route::get('/shipments/create','create')->name('shipments.create');
        Route::post('/shipments', 'store')->name('shipments.store');
        Route::get('/shipments', 'index')->name('shipments.index');
        Route::patch('/shipments/{id}/confirm', 'confirmDelivery')->name('shipments.confirm');
        Route::get('/shipments/{id}/print', 'printManifest')->name('shipments.print');
        Route::get('/shipments/{id}', 'show')->name('shipments.show');
        Route::patch('/shipments/{id}/cancel', 'cancel')->name('shipments.cancel'); // NUEVO
    });
});
```

**Archivo:** `app/Http/Controllers/ShipmentController.php` — nuevo método:
```php
public function cancel($id)
{
    $shipment = Shipment::with('deliveries')->findOrFail($id);

    if ($shipment->status === 'entregado') {
        return back()->withErrors(['error' => 'No se puede cancelar un embarque ya entregado.']);
    }

    DB::transaction(function () use ($shipment) {
        foreach ($shipment->deliveries as $delivery) {
            $detail = $delivery->saleDetail;
            if ($detail && $detail->variant) {
                $detail->variant->increment('stock', $delivery->quantity_delivered);
            }
        }
        $shipment->update(['status' => 'cancelado']);
    });

    return back()->with('success', 'Embarque cancelado, stock restituido.');
}
```

### Cómo probar todo el bloque de bugs
1. Crear un pedido de 10 piezas, promised_date esta semana.
2. Registrar 10 piezas fabricadas → verificar que "pendiente por fabricar" muestre 0.
3. Crear un embarque con solo 3 de esas 10 piezas.
4. Volver al Plan de Producción → confirmar que sigue mostrando 0 pendientes por fabricar (antes del fix mostraba 3 — este es el caso de prueba del bug reportado).
5. Cancelar ese embarque (bug #5) → confirmar que el stock regresa a 10.
6. Verificar que mover una venta manualmente a `enviado` desde el Kanban ya NO mueve stock (bug #2).
7. Intentar acceder a `/shipments` con un usuario `vendedor` → debe dar 403 (bug #4).

---

## FASE 2.5 — Mejoras a Embarques (solicitado Julio 2026)

### 2.5.1 — Filtro por cliente al armar embarque

**Archivo:** `app/Http/Controllers/ShipmentController.php`, método `create()`
```php
public function create(Request $request)
{
    $clientIds = $request->input('client_ids', []); // array opcional

    $salesQuery = Sale::with(['client', 'details.variant.product', 'details' => function($q) {
            $q->withSum('deliveries as delivered_quantity', 'quantity_delivered');
        }])
        ->whereIn('stage', ['confirmado', 'produccion', 'enviado']);

    if (!empty($clientIds)) {
        $salesQuery->whereIn('client_id', $clientIds);
    }

    $sales = $salesQuery->get()->filter(/* misma lógica de piezas embarcables que ya existe */)->values();

    return Inertia::render('Shipments/Create', [
        'shippableSales' => $sales,
        'filters' => ['client_ids' => $clientIds],
    ]);
}
```

**Archivo:** `resources/js/Pages/Shipments/Create.vue` — agregar un selector multi-cliente (puede ser un multiselect simple con checkbox) arriba del listado de pedidos embarcables, que dispare `router.get(route('shipments.create'), { client_ids: [...] }, { preserveState: true })`. Si no se selecciona nada, se manda vacío y se ve el listado completo (comportamiento actual, sin romper nada).

### 2.5.2 — Notas de entrega individuales por pedido

**Archivo:** `app/Http/Controllers/ShipmentController.php`
```php
public function printManifest($id)
{
    $shipment = Shipment::with(['deliveries.saleDetail.sale.client'])->findOrFail($id);

    // Agrupar las entregas del embarque por pedido (sale_id / cliente)
    $deliveriesBySale = $shipment->deliveries->groupBy(fn($d) => $d->saleDetail->sale_id);

    $orders = $deliveriesBySale->map(function ($deliveries) use ($shipment) {
        $sale = $deliveries->first()->saleDetail->sale;
        return [
            'sale' => $sale,
            'client' => $sale->client,
            'items' => $deliveries,
            'total' => $deliveries->sum(fn($d) => $d->quantity_delivered * $d->saleDetail->unit_price),
        ];
    });

    $pdf = Pdf::loadView('pdf.shipment_manifest', compact('shipment', 'orders'));
    return $pdf->stream('remision-viaje-'.$shipment->id.'.pdf');
}
```

**Archivo:** `resources/views/pdf/shipment_manifest.blade.php` — modificar para iterar `$orders` y meter un salto de página (`page-break-after: always;` en CSS de impresión) entre cada pedido, cada uno con su propio encabezado de cliente, listado de piezas, total, y un recuadro "Recibí de conformidad — Nombre y Firma: ______________".

**Resultado:** un solo PDF, una página (o más) por cliente/pedido dentro del mismo viaje, listo para que el chofer haga firmar cada entrega por separado.

---

## FASE 3 — Dashboards Especializados (una vez resueltos los bugs críticos)

### 3.1 Dashboard de Producción
Igual que antes, pero ahora usa la fórmula ya corregida de `pending_to_fabricate` (bug #1 resuelto):
```php
elseif (in_array($user->role, ['produccion', 'supervisor'])) {
    $inProduction = SaleDetail::whereHas('sale', fn($q) => $q->where('stage', 'produccion'))->count();
    $readyToShip = ProductionCompletion::whereDoesntHave('saleDetail.saleDeliveries')->sum('quantity_completed');
    $upcomingDates = Sale::where('stage', 'produccion')->orderBy('promised_date')->take(10)->get(['id', 'promised_date', 'client_id']);

    return Inertia::render('Dashboard', [
        'isAdmin' => false,
        'isProduccion' => true,
        'kpis' => ['in_production' => $inProduction, 'ready_to_ship' => $readyToShip],
        'upcomingDates' => $upcomingDates,
    ]);
}
```

### 3.2 Dashboard / Módulo Financiero (rediseñado — estado de cuenta por cliente)

```php
elseif ($user->role === 'financiero') {
    // Estado de cuenta por cliente: deuda exigible ENTREGADA menos cobrado
    $accountsByClient = Client::query()
        ->withSum(['sales as total_paid' => function ($q) {
            $q->select(DB::raw('COALESCE(SUM(paid_amount),0)'));
        }], 'paid_amount')
        ->get()
        ->map(function ($client) {
            $deliveredValue = DB::table('sale_deliveries')
                ->join('shipments', 'sale_deliveries.shipment_id', '=', 'shipments.id')
                ->join('sale_details', 'sale_deliveries.sale_detail_id', '=', 'sale_details.id')
                ->join('sales', 'sale_details.sale_id', '=', 'sales.id')
                ->where('sales.client_id', $client->id)
                ->where('shipments.status', 'entregado')
                ->selectRaw('SUM(sale_deliveries.quantity_delivered * sale_details.unit_price) as total')
                ->value('total') ?? 0;

            $paid = Sale::where('client_id', $client->id)->sum('paid_amount');

            return [
                'client' => $client->only(['id', 'name']),
                'delivered_value' => $deliveredValue,
                'paid' => $paid,
                'balance' => $deliveredValue - $paid,
            ];
        })
        ->filter(fn($row) => $row['balance'] > 0)
        ->values();

    return Inertia::render('Dashboard', [
        'isAdmin' => false,
        'isFinanciero' => true,
        'accountsReceivable' => $accountsByClient,
    ]);
}
```
> Nota: esta consulta es ilustrativa del enfoque (agregación por cliente sobre entregas confirmadas), no código listo para producción — optimizar con una sola query agregada antes de llevarlo a un dataset grande.

### 3.3 Selector de Vista para Admin
Sin cambios respecto al plan original: tabs "Ventas" / "Producción" / "Financiero" / "Todo", solo Admin ve las 4.

### 3.4 Rutas y sidebar condicionales por rol completo

```php
// web.php — nuevas zonas a agregar
Route::middleware('role:admin,inventario')->group(function () {
    // mover aquí todo el bloque de ShipmentController (ver bug #4)
});

Route::middleware('role:admin,financiero')->group(function () {
    Route::get('/cuentas-por-cobrar', [FinanceController::class, 'index'])->name('finance.index');
    Route::post('/sales/{sale}/payment', [SalePaymentController::class, 'store'])->name('sales.payment.store.finanzas');
    // Acceso de lectura a shipments para financiero: ver nota abajo
});
```

En `AuthenticatedLayout.vue`:
```html
<template v-if="['admin', 'vendedor'].includes($page.props.auth.user.role)">
    <Link :href="route('sales.index')">Reporte de Ventas</Link>
    <Link :href="route('sales.create')">Punto de Venta</Link>
</template>

<template v-if="['admin', 'inventario'].includes($page.props.auth.user.role)">
    <Link :href="route('shipments.index')">Embarques</Link>
</template>

<template v-if="['admin', 'financiero'].includes($page.props.auth.user.role)">
    <Link :href="route('finance.index')">Cuentas por Cobrar</Link>
</template>

<template v-if="$page.props.auth.user.role === 'admin'">
    <Link :href="route('settings.index')">Configuración</Link>
</template>
```

> Pendiente de decidir: si `financiero` necesita ver el índice de embarques (`shipments.index`) en modo lectura para dar seguimiento, hay que exponer esa ruta también al rol `financiero` (aunque no pueda crear/confirmar). Se puede resolver con `role:admin,inventario,financiero` en la ruta `shipments.index` específicamente, dejando `create`/`store`/`confirm`/`cancel` solo para `admin,inventario`.

---

### 3.5 Restricción de precios por rol (DECIDIDO con cliente, Julio 2026)

Regla final: solo `admin`, `financiero`, y `vendedor` **dentro de sus propias operaciones** (POS, sus ventas, su dashboard) ven precios. `supervisor`, `inventario` y `produccion` nunca los ven. Nada que cambiar en el flujo del Vendedor — sigue viendo precios en su POS exactamente como hoy.

**Archivo:** `app/Http/Controllers/SaleController.php`, método `index()` (Kanban)
```php
public function index(Request $request)
{
    $user = auth()->user();
    $hidePrices = in_array($user->role, ['supervisor']); // inventario/produccion no acceden a esta ruta

    $sales = Sale::with('client')
        ->when($hidePrices, fn($q) => $q->select('id', 'client_id', 'user_id', 'stage', 'promised_date', 'created_at'))
        ->when(!$hidePrices, fn($q) => $q->select('id', 'client_id', 'user_id', 'stage', 'promised_date', 'total', 'paid_amount', 'created_at'))
        ->get();

    return Inertia::render('Sales/Index', ['sales' => $sales, 'hidePrices' => $hidePrices]);
}
```

**Archivo:** `app/Http/Controllers/SaleController.php`, método `show()`
```php
public function show(Sale $sale)
{
    $user = auth()->user();
    // Forzar Modo Taller (sin precios) para roles que nunca deben ver montos,
    // sin importar el query param que use el frontend para el switch Oficina/Taller.
    $forceProductionMode = in_array($user->role, ['supervisor', 'inventario', 'produccion']);

    return Inertia::render('Sales/Show', [
        'sale' => $sale->load(['details.variant.product', 'client', 'history', 'payments']),
        'forceProductionMode' => $forceProductionMode,
    ]);
}
```
En `Sales/Show.vue`, si `forceProductionMode` viene en `true`, ocultar el switch Oficina/Taller y dejar fijo el modo sin precios (no permitir que el usuario lo cambie desde el frontend).

**Archivo:** `app/Http/Controllers/ShipmentController.php`, métodos `create()` y `show()`
```php
// Antes: ->with(['details.variant.product', ...])   ← trae price_1..price_5 sin querer
// Después, restringir columnas explícitamente:
->with(['details.variant:id,product_id,material,stock', 'details.variant.product:id,name,measurements,image'])
```
Esto es necesario porque Embarques es de Inventarios (bug #4, en proceso de restringirse a `role:admin,inventario`), y ese rol nunca debe recibir precios en el payload — aunque la vista no los pinte, sin este cambio viajarían en el JSON.

**Archivo:** `app/Http/Controllers/DashboardController.php` — al construir la futura rama de `supervisor` (Fase 3.1), no incluir ningún KPI en pesos, solo conteos y fechas:
```php
elseif ($user->role === 'supervisor') {
    return Inertia::render('Dashboard', [
        'isSupervisor' => true,
        'kpis' => [
            'sales_in_progress' => Sale::whereNotIn('stage', ['entregado', 'cancelado'])->count(),
            'in_production' => SaleDetail::whereHas('sale', fn($q) => $q->where('stage', 'produccion'))->count(),
            // Sin 'revenue', 'total', 'paid_amount' ni ningún campo en $
        ],
    ]);
}
```

### Cómo probar
1. Con un usuario `supervisor`: entrar al Kanban → no debe verse columna de Total/Pagado. Abrir un pedido → debe abrir directo en modo sin precios, sin poder cambiar al modo con precios.
2. Con un usuario `inventario`: entrar a `/shipments/create` → abrir el payload en las herramientas de desarrollador del navegador (pestaña Network o Vue Devtools) → confirmar que `price_1..price_5` **no aparecen en absoluto** en el JSON, no solo que estén ocultos visualmente.
3. Con un usuario `vendedor`: confirmar que el POS, su Kanban y su dashboard siguen mostrando precios exactamente igual que antes — este rol no debe notar ningún cambio.

---

## FASE 4 — Catálogo Público

⚠️ **Antes de empezar:** la ruta `/` ya devuelve `catalogo.index` (vista Blade) según `web.php` actual — auditar esa vista primero para no duplicar trabajo.

### 4.1 Nuevas rutas
```php
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
```

---

## Cómo usar esta guía con Claude

Al iniciar sesión nueva:
```
"Voy a trabajar en [Fase X.X - Nombre].
Contexto:" → [pegar CONTEXTO_TECNICO.md]
"Archivos relevantes:" → [pegar archivos de esa tarea]
```

Para los bugs críticos, comparte específicamente: `ProductionController.php`, `SaleController.php`, `ShipmentController.php`, `web.php`, y las migraciones de `shipments`/`sale_deliveries`/`production_completions`.