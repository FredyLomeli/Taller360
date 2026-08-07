# 🗺️ GUÍA DE RUTA — TALLER 360
**Actualizada:** 25 de julio 2026 — auditoría directa contra el código fuente real (zip del proyecto)

---

## Estado actual en una línea
> Los 5 bugs críticos documentados en julio (Producción, doble stock, `storeDelivery`, rol en Embarques, cancelación) están **confirmados como resueltos en el código real**, junto con la recolección en mostrador (`pickup_type`) que se creía pendiente, y el `UserController` que en la primera ronda de auditoría parecía faltante (era solo una omisión al armar el zip). Quedan dos pendientes reales de Embarques (selector multi-cliente y agrupación de notas de entrega) y un hallazgo de limpieza nuevo: `package.json` tiene dos versiones de Tailwind instaladas a la vez, aunque solo una está activa.

## ⚠️ Nota sobre versiones previas de este documento
La primera versión de `GUIA_RUTA.md` decía que los bugs #2, #3, #4 y #5 "siguen pendientes" — ya no es cierto, se verificó contra el código y los 5 están resueltos. Una segunda ronda de auditoría también cerró la duda sobre `UserController.php` (sí existe) y sumó el hallazgo de Tailwind. Si vas a retomar el proyecto con una IA nueva, comparte esta versión.

---

## Mapa de fases (reordenado por prioridad real, confirmado en código)

```
✅ FASE 0 — Seguridad y dependencias         (COMPLETADA — Laravel 12.62.0, dompdf 3.1.2 confirmados)
✅ FASE 1 — Deuda técnica y roles            (COMPLETADA)
✅ BUGS CRÍTICOS — Producción/Stock/Embarques (CONFIRMADOS RESUELTOS EN CÓDIGO)
✅ UserController                            (CONFIRMADO COMPLETO — era omisión del zip)
🎯 SPRINT CLIENTE (04 ago 2026)              (5 puntos, ver bloque dedicado abajo — máxima prioridad)
🟢 Conflicto de versiones Tailwind           (limpieza, no bloquea nada)
🟠 FASE 2 — Producción y embarques           (95% — falta toggle "ver acumulado")
🟢 FASE 2.5 — Mejoras a Embarques            (selector multi-cliente + notas agrupadas — NINGUNA construida)
🟡 FASE 2.6 — Optimización de consultas      (2 hallazgos concretos confirmados, ver abajo)
🟡 FASE 3 — Dashboards + Roles completos     (confirmado sin empezar en código)
🟢 FASE 4 — Catálogo público + link          (confirmado: mockup estático, sin conexión a BD)
🔵 FASE 5 — Precios dinámicos por flete      (diseño cerrado, sin construcción)
🟣 FASE 6 — Reportes PDF                     (actividad final)
```

---

## 🎯 Sprint acordado con cliente (04 ago 2026) — siguiente en la fila, antes que todo lo demás

Reunión con el cliente dejó 5 puntos con diseño ya cerrado (ver `CONTEXTO_TECNICO.md` sección 0.1 para el detalle completo). Este bloque pasa a ser la prioridad #1, por encima de Tailwind y de la Fase 2.5 de Embarques que ya estaban planeadas.

### Paso 1 — Envío automático de nota de venta (empezar por aquí, es el más rápido y de menor riesgo)

En `SaleController.php`, extraer el cuerpo de `sendEmail()` a un método privado:
```php
private function sendSaleNoteMail(Sale $sale): void
{
    $settings = Setting::all()->pluck('value', 'key');
    // ... mismo armado de $company, $logoPath, $pdf que ya existe en sendEmail() ...

    $emails = [];
    if ($sale->client && $sale->client->email) $emails[] = $sale->client->email;
    if (!empty($settings['notification_emails'])) {
        $emails = array_merge($emails, array_map('trim', explode(',', $settings['notification_emails'])));
    }
    $emails = array_unique(array_filter($emails));
    if (empty($emails)) return;

    try {
        Mail::to($emails)->send(new SaleNoteEmail($sale, $pdf->output()));
    } catch (\Exception $e) {
        \Log::error('Fallo envío automático de nota de venta #'.$sale->id.': '.$e->getMessage());
    }
}
```
Y en `store()`, justo después del `return DB::transaction(...)` (fuera de la transacción):
```php
$autoEmail = Setting::where('key', 'auto_email_on_sale')->value('value');
if ($autoEmail === null || filter_var($autoEmail, FILTER_VALIDATE_BOOLEAN)) {
    $this->sendSaleNoteMail($sale->fresh(['details', 'client']));
}
```
`sendEmail()` (el endpoint manual) pasa a llamar también a `sendSaleNoteMail()` para no duplicar código.

Agregar `auto_email_on_sale` al `$fillable`/whitelist de `SettingController` y un toggle en `Settings/Index.vue`, default `true`.

### Paso 2 — Supervisor con permisos completos

En `routes/web.php`:
```php
// Antes:
Route::middleware('role:admin,produccion')->group(function () { ... });
Route::middleware('role:admin,inventario')->group(function () { ... });

// Después:
Route::middleware('role:admin,produccion,supervisor')->group(function () { ... });
Route::middleware('role:admin,inventario,supervisor')->group(function () { ... });
```
Revisar también el grupo de Productos (`role:admin`) — agregar `supervisor` ahí también para que tenga CRUD completo de inventario, no solo lectura.

### Paso 3 — Bug del pedido en limbo

En `ShipmentController::cancel()`, reemplazar:
```php
if (in_array($sale->stage, ['entregado', 'enviado'])) {
    $transition = SaleHistory::where('sale_id', $sale->id)
        ->whereIn('to_stage', ['entregado', 'enviado'])
        ->latest()->first();
    $revertStage = $transition->from_stage ?? 'produccion';
    $sale->update(['stage' => $revertStage]);
}
```
por un recálculo en vivo (mismo patrón que `closeOrderIfComplete()`, pero a la inversa):
```php
if (in_array($sale->stage, ['entregado', 'enviado'])) {
    // Tras regresar el stock de este embarque, ¿queda algo sin entregar?
    $stillPending = $sale->details()->get()->contains(function ($d) {
        $delivered = $d->deliveries()
            ->whereHas('shipment', fn($q) => $q->where('status', '!=', 'cancelado'))
            ->sum('quantity_delivered');
        return $delivered < $d->quantity;
    });

    $sale->update(['stage' => $stillPending ? 'produccion' : $sale->stage]);
}
```
(Ojo: este bloque corre dentro del `foreach ($shipment->deliveries as $delivery)`, así que hay que evitar recalcular la sale completa en cada iteración si el embarque toca varias líneas del mismo pedido — mejor mover este recálculo fuera del loop, una vez por `sale_id` único, antes de cerrar la transacción.)

También quitar el `SaleHistory::create()` manual duplicado en `store()` (líneas ~133-141) — dejar que `SaleObserver::updated()` sea la única fuente de historial de cambios de etapa. Si se quiere conservar la nota descriptiva del embarque ("📦 Envío #X: N unidades..."), moverla a un campo `notes` opcional que el Observer pueda recibir, o crear una tabla de bitácora separada — no duplicar `SaleHistory`.

### Paso 4 — Stock mínimo por variante

```bash
php artisan make:migration add_min_stock_to_product_variants_table
```
```php
$table->integer('min_stock')->nullable()->after('stock');
```
En `DashboardController`, cambiar:
```php
$lowStockProducts = ProductVariant::where('stock', '<=', 5)
```
por:
```php
$lowStockProducts = ProductVariant::whereColumn('stock', '<=', DB::raw('COALESCE(min_stock, 5)'))
```
En `Products/Create.vue`/`Edit.vue`: mostrar el input de `min_stock` por variante solo cuando `form.is_favorite === true` (mismo patrón condicional que ya usan para otros campos dependientes).

### Paso 5 — Órdenes de Trabajo (el más grande, dejarlo al final)

Requiere sesión dedicada por su tamaño — migración de 3 tablas/columnas, un controlador nuevo (`WorkOrderController`), cambios en `ProductionController::index()` para unir dos fuentes de datos, y una vista nueva. Ver el detalle completo de diseño en `BACKLOG.md` punto 5 y `CONTEXTO_TECNICO.md` sección 0.1-C antes de empezar — vale la pena revisar juntos el mockup de la UI antes de tocar el backend, dado que cambia cómo se ve el Plan de Producción día a día para el taller.

---

## PRÓXIMA TAREA #6: Resolver el conflicto de versiones de Tailwind

**Tiempo estimado:** 15 min si se queda en v3, ~1 h si se migra a v4 (confirmado, riesgo bajo) | **Prioridad:** Baja-media (limpieza, no bloquea nada hoy)

Con `tailwind.config.js` ya confirmado, tu configuración es mínima: solo la fuente Figtree (cargada por `<link>` externo, no `@font-face` local) y el plugin `@tailwindcss/forms`. Sin colores, breakpoints ni `@apply` custom. Esto baja el riesgo de migrar a v4 de "medio" a "bajo, confirmado".

**Opción A — quedarse en v3 (más rápido, cero riesgo):**
```bash
npm uninstall @tailwindcss/vite
```

**Opción B — completar la migración a v4 (recomendada si hay 1 hora disponible, dado lo simple de tu config):**

1. `resources/css/app.css` — reemplazar:
   ```css
   @tailwind base;
   @tailwind components;
   @tailwind utilities;
   ```
   por:
   ```css
   @import "tailwindcss";
   @plugin "@tailwindcss/forms";

   @theme {
       --font-sans: "Figtree", ui-sans-serif, system-ui, sans-serif;
   }
   ```
   (v4 usa `@plugin` dentro del CSS en vez de `plugins: [forms]` en el config, y `@theme` en vez de `theme.extend`.)

2. `vite.config.js` — agregar el plugin:
   ```js
   import tailwindcss from '@tailwindcss/vite';
   // ...
   plugins: [
       tailwindcss(),
       laravel({ input: ['resources/css/app.css', 'resources/js/app.js'], refresh: true }),
       vue({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } }),
   ],
   ```

3. `package.json` — quitar `tailwindcss` (v3), `postcss`, `autoprefixer` de `devDependencies` (v4 no los necesita); confirmar que `@tailwindcss/forms` siga siendo compatible con v4 (en general lo es, revisar el changelog del plugin si el build falla).

4. Borrar `tailwind.config.js` — en v4 ya no se usa (todo vive en `@theme` dentro de `app.css`).

5. Probar visualmente: v4 cambió la paleta de colores por defecto a espacio OKLCH — mismos nombres de clase (`bg-gray-100`, `text-red-600`), tono ligeramente distinto. Revisar especialmente el POS, Kanban, y los PDFs generados con dompdf (dompdf no procesa Tailwind directamente, pero si algún PDF usa clases inline heredadas de un componente Vue, confirmar que se vea igual).

Dado que la config es tan simple, esta migración ya no requiere una ventana grande de prueba como se pensaba antes — es viable hacerla en una sesión normal de desarrollo.

---

## PRÓXIMA TAREA #7: Fase 2.5 — Embarques, lo que de verdad falta
*(prioridad real más alta que la Tarea #1 de limpieza de Tailwind — hazla primero si el tiempo es limitado)*

**Tiempo estimado:** 3-4 horas | **Prioridad:** Media-alta, antes de que crezca el volumen de viajes con múltiples clientes

### 2.5.1 — Selector multi-cliente en `Shipments/Create.vue`
El backend ya soporta `client_ids[]` en `ShipmentController::create()`. Falta solo la UI:
- Agregar un `<select multiple>` o componente de chips con los clientes que tienen ventas activas (`confirmado`, `produccion`, `enviado`).
- Al seleccionar, disparar `router.get(route('shipments.create'), { client_ids: seleccionados }, { preserveState: true })`.

### 2.5.2 — Agrupar notas de entrega por pedido/cliente
**Corrección importante sobre el plan anterior:** el documento previo decía que había que "fusionar la plantilla real con una versión de referencia (`reference_shipment_manifest.blade.php`)". Ese archivo de referencia no existe en el código auditado, y el controlador (`printManifest()`) tampoco agrupa nada — es un `@foreach` plano. Hay que construirlo desde cero:

**Paso 1 — Agrupar en el controlador**, no en la plantilla:
```php
public function printManifest($id)
{
    $shipment = Shipment::with(['deliveries.saleDetail.sale.client'])->findOrFail($id);

    $groupedByClient = $shipment->deliveries->groupBy(fn($d) => $d->saleDetail->sale->client_id);

    $pdf = Pdf::loadView('pdf.shipment_manifest', compact('shipment', 'groupedByClient'));
    return $pdf->stream('remision-viaje-'.$shipment->id.'.pdf');
}
```
**Paso 2 — En la plantilla**, iterar sobre `$groupedByClient` en vez del `@foreach` plano actual, con un encabezado de sección por cliente/pedido antes de cada bloque de piezas.

---

## PRÓXIMA TAREA #8: Optimización de consultas confirmadas (Fase 2.6)

**Tiempo estimado:** 1-2 horas | **Prioridad:** Media, importante para hosting compartido

Dos hallazgos concretos y confirmados en código (no hipótesis, ya se revisó línea por línea):

**1. `ProductController::index()`:**
```php
// Hoy:
'products' => Product::with(['category', 'variants'])->orderBy('is_favorite', 'desc')->get(),

// Recomendado:
'products' => Product::with(['category:id,name', 'variants'])
    ->orderBy('is_favorite', 'desc')
    ->paginate(20) // o el tamaño que use Products/Index.vue
    ->withQueryString(),
```
Revisar `Products/Index.vue` para confirmar si ya espera un objeto paginado o una colección plana antes de cambiar esto — hoy usa "paginación local" en el frontend, que dejaría de tener sentido si se pagina en servidor.

**2. `SaleController::create()` (POS):**
```php
// Hoy:
'clients' => Client::all(),

// Recomendado, si el catálogo de clientes ya es grande:
'clients' => Client::select('id', 'name', 'business_name', 'price_tier')->orderBy('name')->get(),
```
Si el volumen de clientes sigue siendo manejable (decenas, no miles), esto puede esperar — priorizar `ProductController` primero, que carga precios completos de cada variante en cada visita.

---

## PRÓXIMA TAREA #9: Fase 3 — Dashboards especializados

**Confirmado en código: no hay ni una línea de esto todavía.** `DashboardController::index()` solo tiene ramas `admin` y `vendedor`; todo lo demás cae a la pantalla de bienvenida. Antes de empezar, decidir con el cliente el orden real entre Producción (3.1) y Financiero (3.2) — ambos dependen de datos que ya están correctos gracias a los bugs resueltos, así que no hay bloqueo técnico, solo priorización de negocio.

---

## PRÓXIMA TAREA #10: Fase 4 — Catálogo público

**Confirmado en código:** `/` devuelve una vista Blade 100% estática (`catalogo.index`), con categorías y un producto de ejemplo hardcodeados. No hay ningún avance real de esta fase — no hay que "verificar avance existente" como decía la versión anterior de este documento, hay que construir desde cero:
1. Reemplazar los bloques hardcodeados por un `CatalogoController` que consulte `Product::with('variants')->whereHas('category')->get()`.
2. Agregar las rutas públicas `/catalogo`, `/catalogo/{categoria}`, `/catalogo/producto/{id}`.
3. Nunca exponer `price_1..price_5` en esta vista pública (reservado para el link personalizado de la Fase 4.2).

---

## Notas para la siguiente sesión con IA

1. Comparte `CONTEXTO_TECNICO.md` actualizado — ya incluye el stack de frontend confirmado (`package.json`, `vite.config.js`) y el estado real de cada bug.
2. Si vuelves a compartir un zip del proyecto, **excluye `vendor/`, `node_modules/`, `.git`, `storage/logs`, `storage/framework`** — así quedó ligero y fácil de revisar completo en esta ronda.
3. Si la tarea toca estilos, incluye `tailwind.config.js` y `postcss.config.js` — no se auditaron todavía (ver Tarea #1, conflicto de versiones de Tailwind).
