# 🧠 CONTEXTO TÉCNICO — TALLER 360
**Versión Real:** 2.6 | **Auditado contra código real:** Julio 2026
**Para:** Retomar desarrollo con IA o desarrollador nuevo sin perder contexto.
**IMPORTANTE:** Este archivo refleja el código REAL auditado (controladores, rutas y migraciones). Compartirlo siempre al iniciar una nueva sesión.

---

## 1. Stack Tecnológico

| Capa | Tecnología | Notas |
|------|-----------|-------|
| Backend | Laravel 12 (PHP 8.2+) | MVC + Eloquent ORM |
| Frontend | Vue 3 (`<script setup>`) | Composition API |
| Puente | Inertia.js | No hay API REST; los controladores devuelven `Inertia::render()` |
| Estilos | Tailwind CSS | Sin CSS custom salvo scrollbars y print |
| BD | MySQL / MariaDB InnoDB | |
| PDFs | barryvdh/laravel-dompdf | `composer.json` fija `^3.1.1` (verificar que `composer.lock` resuelva a 3.1.2 como indica el changelog previo) |
| Firma Digital | vue-signature-pad | Captura firma del cliente en el POS |
| Alertas | sweetalert2 | Confirmaciones y toasts en todo el sistema |
| Utilidades JS | lodash (debounce/throttle) | Búsquedas con delay |
| Hosting objetivo | Neubox (Hosting Compartido / cPanel) | |

> **Arquitectura clave:** Inertia.js conecta Laravel con Vue sin API REST. Los controladores devuelven `Inertia::render('Carpeta/Vista', $datos)`. Los componentes Vue reciben los datos como `props`. No hay rutas `/api/` para uso normal del frontend.

> ⚠️ **Nota sobre `/`:** la ruta raíz (`web.php`) actualmente devuelve una vista Blade `catalogo.index` directamente (no Inertia). No se auditó el contenido de esa vista en esta sesión — verificar si es un placeholder o si la Fase 4 (Catálogo Público) ya tiene algo de avance no documentado.

---

## 2. Estructura de Base de Datos (Schema Real Completo)

### 👤 `users`
```
id, name, email (unique), password,
role (string: 'admin'|'vendedor'|'produccion'|'inventario'|'supervisor'|'financiero', default:'vendedor'),
email_verified_at, remember_token, timestamps
```
- `role` es un string directo en la tabla, NO una tabla separada de roles.
- 6 roles válidos definidos en `UserController::VALID_ROLES` (no auditado en esta sesión, confirmar que coincide con esta lista).

### 👥 `clients`
```
id, name, business_name (nullable), price_tier (int 1-5, default:1),
email (unique), phones,
street_address, neighborhood, city, state, delegation, zip_code (todos nullable),
references (text, nullable), timestamps
```
- `price_tier` 1-5 → se muestra como Listas A, B, C, D, E en el frontend.
- Confirmado en `ClientController`: `price_tier` es obligatorio (`required|integer|min:1|max:5`) tanto en `store` como en `update`.
- **`catalog_token` NO existe todavía** — confirmado, Fase 4.2 sigue sin construir.

### 🗂️ `categories`
```
id, name, timestamps
```

### 📦 `products`
```
id, category_id (FK → categories),
name, measurements (nullable), description (nullable),
image (nullable, ruta relativa al disco public),
is_favorite (boolean, default:false), timestamps
```
> ⚠️ NO existe campo `color`. El color es atributo de la venta (`sale_details.chosen_color`).

### 🎨 `product_variants`
```
id, product_id (FK → products, cascade delete),
material (string), sku (nullable), stock (int, default:0),
price_1 (decimal, obligatorio), price_2..price_5 (decimal, nullable),
timestamps
```
- Un producto tiene N variantes (una por material).
- El stock sube al registrar producción (`production_completions`) y baja al confirmar un embarque (`shipments.store`).
- ⚠️ **También baja stock el motor de etapas (`SaleController::updateStage`) al mover una venta a `stage = 'enviado'`.** Ver sección 5 — es un flujo paralelo al de embarques y es la causa raíz de la mayoría de los problemas de consistencia de inventario documentados en el Backlog.

### 💼 `sales`
```
id, user_id (FK → users), client_id (FK → clients, nullable),
total (decimal), paid_amount (decimal, default:0),
change_amount (decimal, default:0), payment_method (string, default:'Efectivo'),
signature (longText, nullable — base64),
stage (enum: pedido|confirmado|produccion|enviado|entregado|cancelado, default:'pedido'),
promised_date (date, nullable), is_partial_shipping (boolean, default:false),
timestamps
```

### 📋 `sale_details`
```
id, sale_id (FK → sales, cascade), product_variant_id (FK → product_variants),
product_name (snapshot), quantity (int),
chosen_color (nullable), custom_notes (text, nullable),
additional_cost (decimal, default:0),
discount_percent (int, default:0), unit_price (decimal), subtotal (decimal),
timestamps
```

### 📜 `sale_histories`
```
id, sale_id (FK → sales, cascade), user_id (FK → users),
from_stage (nullable), to_stage, notes (text, nullable), timestamps
```

### 💳 `sale_payments`
```
id, sale_id (FK → sales, cascade), user_id (FK → users),
amount (decimal), payment_method (string),
reference (nullable), paid_at (timestamp), timestamps
```
- **Los pagos están ligados a una venta (`sale_id`), no a un cliente directamente.** El "estado de cuenta por cliente" que necesita el módulo de Finanzas (Fase 3.2) se construye agregando esta tabla + `sales.paid_amount` agrupado por `client_id` — no requiere cambio de esquema. Ver sección 9.

### ⚙️ `settings`
```
id, key (unique), value (text, nullable), timestamps
```
Claves usadas: `company_name`, `company_rfc`, `company_address`, `company_phone`, `company_logo`, `notification_emails`, `allow_negative_stock`, `ticket_footer_text`.

### 🛠️ `production_completions` (v2.6)
```
id, sale_detail_id (FK → sale_details),
quantity_completed (int),
user_id (FK → users),
completed_at (timestamp),
timestamps
```
- Registra cuando el taller termina una pieza física.
- NO cambia el `stage` global del pedido — solo acumula piezas listas en bodega.
- Al completar, suma al `stock` de `product_variants`.
- ⚠️ Esta tabla es la única fuente confiable de "cuánto se ha fabricado en total" para un pedido. **No usar `product_variants.stock` para inferir eso** — el stock se ve afectado también por embarques y por el motor de etapas. Ver bug crítico en sección 5.

### 🚚 `shipments` (v2.6)
```
id, driver_name (nullable), license_plate (nullable), destination (nullable),
status (string, default:'en_transito' — valores usados: 'en_transito','entregado'),
shipped_at (nullable timestamp), delivered_at (nullable timestamp),
notes (text, nullable),   ← existe en la migración, no estaba documentado antes
user_id (FK → users),
timestamps
```
- Entidad que agrupa la carga física de una camioneta en un viaje.
- Al confirmar entrega (`status = 'entregado'`), evalúa si cada pedido involucrado puede cerrarse (`sale.stage = 'entregado'` si ya se entregó el 100% de ese detalle).
- ⚠️ El comentario en la migración menciona `'cancelado'` como valor posible de `status`, pero **no existe ningún método de cancelación de embarque en `ShipmentController`** ni ruta para ello. La regla de negocio "si se cancela un embarque, el stock regresa" está documentada pero no implementada.

### 📦 `sale_deliveries` (v2.6)
```
id, shipment_id (FK → shipments, cascade, OBLIGATORIO no nullable),
sale_detail_id (FK → sale_details, cascade),
quantity_delivered (int),
timestamps
```
- Tabla pivote. Permite envíos parciales del mismo pedido en distintos viajes.
- ⚠️ **Solo tiene estas 3 columnas + timestamps.** No tiene `user_id` ni `delivered_at`. Ver bug crítico en sección 5 (`SaleController::storeDelivery()` intenta escribir columnas que no existen).

---

## 3. Relaciones Eloquent (confirmadas / corregidas contra código real)

```
User          → hasMany(Sale), hasMany(Shipment), hasMany(ProductionCompletion)
Client        → hasMany(Sale)
Category      → hasMany(Product)
Product       → belongsTo(Category) → hasMany(ProductVariant) [cascade delete]
ProductVariant→ belongsTo(Product)
Sale          → belongsTo(User), belongsTo(Client)
              → hasMany(SaleDetail)
              → hasMany(SaleHistory)  ['history']
              → hasMany(SalePayment)  ['payments']
              → getIsPaidAttribute()  [accessor: paid_amount >= total]
SaleDetail    → belongsTo(Sale)
              → belongsTo(ProductVariant) — ⚠️ el método real en el código es `variant()`, NO `productVariant()`.
                Confirmado en ProductionController y ShipmentController, que usan `->with(['variant.product', ...])`
                y `$detail->variant`. Si algún desarrollador nuevo asume `productVariant`, el eager-load fallará
                silenciosamente (relación no encontrada) o lanzará error según el contexto. AJUSTAR EN CUALQUIER
                DOCUMENTO O PROMPT que diga `productVariant`.
              → hasMany(ProductionCompletion) ['completions']
              → hasMany(SaleDelivery) ['deliveries']
SaleHistory   → belongsTo(User)
SalePayment   → belongsTo(Sale), belongsTo(User)
Shipment      → belongsTo(User)
              → hasMany(SaleDelivery) ['deliveries']
SaleDelivery  → belongsTo(Shipment), belongsTo(SaleDetail)
ProductionCompletion → belongsTo(SaleDetail), belongsTo(User)
Setting       → getValue(), setValue(), getAll()  [métodos estáticos]
```

---

## 4. Rutas (`routes/web.php`) — Estado real

### Zonas de acceso por rol (confirmado en código)

| Zona | Middleware real | Rutas incluidas |
|------|-----------|----------------|
| Pública | — | `/` (catálogo Blade), login, register |
| Dashboard | `auth,verified` (todos) | `/dashboard` — el controlador decide qué mostrar según rol |
| Perfil | `auth,verified` | `/profile` |
| Ventas | `role:admin,vendedor` | `/pos`, `/sales/*`, `/clients` (crear/editar), pagos |
| Taller | `role:admin,produccion` | `/production-plan`, `/production-plan/complete`, `/production-plan/print` |
| Admin | `role:admin` | `/users`, `/products`, `/clients/{id}` (destroy), `/configuracion` |
| **Embarques** | ⚠️ **NINGUNO** — solo `auth,verified` | `/shipments/*` (index, create, store, show, confirm, print) |

> 🚨 **Hallazgo crítico:** las rutas de `/shipments/*` no están dentro de ningún grupo `role:...`. Cualquier usuario autenticado, sin importar su rol (`vendedor`, `financiero`, `inventario`, `supervisor`), puede crear y confirmar embarques hoy mismo. Debe corregirse — ver Backlog, sección de bugs críticos, y la matriz de roles propuesta en la sección 9 de este documento.

### Rutas de Embarques (confirmadas)

| Método | URI | Nombre | Descripción |
|--------|-----|---------|-------------|
| GET | `/shipments` | `shipments.index` | Historial de viajes |
| GET | `/shipments/create` | `shipments.create` | UI para armar embarque |
| POST | `/shipments` | `shipments.store` | Guardar embarque (transaccional) |
| GET | `/shipments/{id}` | `shipments.show` | Detalle del viaje |
| GET | `/shipments/{id}/print` | `shipments.print` | PDF Remisión del viaje (única, agrupa todos los pedidos del viaje — ver Backlog Fase 2.5) |
| PATCH | `/shipments/{id}/confirm` | `shipments.confirm` | Confirmar entrega y cerrar pedidos |
| — | — | — | **No existe** ruta de cancelación de embarque |

---

## 5. Reglas de Negocio Críticas

### 🚨 Flujo de Stock — DOS mecanismos activos en paralelo (bug arquitectónico)

El sistema tiene **dos caminos independientes** que descuentan/regresan stock, y ninguno sabe del otro:

**Camino A — Motor de etapas (`SaleController::updateStage`, lógica v1 heredada):**
1. Al mover una venta a `stage = 'enviado'`: resta `detail.quantity` de `product_variants.stock` para cada línea de la venta completa.
2. Al cancelar una venta que estaba en `enviado` o `entregado`: regresa el stock.

**Camino B — Embarques (`ShipmentController::store`, v2.6, lo documentado como "oficial"):**
1. Al crear un embarque: resta `item.quantity` de `product_variants.stock` por cada línea incluida en ese viaje específico (puede ser parcial).
2. Al confirmar entrega: NO toca stock (ya se restó al crear el embarque).

**Riesgo:** nada impide que ambos caminos se disparen para el mismo pedido. Si alguien mueve una venta a `enviado` desde el Kanban (Camino A) Y además se arma un embarque para esas mismas piezas (Camino B), el stock se descuenta dos veces. La documentación anterior (`CONTEXTO_TECNICO.md` v2.5) solo describía el Camino B como si fuera el único activo — **no lo es**. Se recomienda decidir un solo camino "fuente de verdad" y neutralizar el otro (ver Backlog).

### 🚨 Bug confirmado — El Plan de Producción no descuenta correctamente lo ya fabricado tras un envío parcial

Ubicación: `ProductionController::index()` (la pantalla interactiva, no el PDF).

```php
// index() NO carga esto (a diferencia de printReport(), que sí lo hace):
// ->withSum('completions as completed_quantity', 'quantity_completed')

'total_completed' => $group->sum('completed_quantity') ?? 0,   // SIEMPRE es 0 en index()
'pending_to_fabricate' => max(0, $group->sum('quantity') - ($group->sum('completed_quantity') ?? 0) - ($group->first()->variant->stock ?? 0)),
```

Como `completed_quantity` nunca se carga en `index()`, la fórmula real que ve el usuario en pantalla es efectivamente:
```
pendiente_por_fabricar = cantidad_necesaria − stock_actual
```

Y `stock_actual` **baja con cada embarque**, incluso parcial. Resultado observado y confirmado: un pedido de 10 piezas, ya fabricado al 100%, al enviarse parcialmente (ej. 3 de 10) hace que la pantalla vuelva a mostrar piezas "pendientes por fabricar" que en realidad ya existen y solo están en tránsito o en bodega esperando el siguiente viaje.

**Corrección necesaria:**
1. Agregar `->withSum('completions as completed_quantity', 'quantity_completed')` en `index()`, igual que ya existe en `printReport()`.
2. `pending_to_fabricate` debe depender **únicamente** de `quantity - completed_quantity`. El campo `in_stock` (`variant.stock`) debe dejar de restarse de esta fórmula — es un dato distinto (piezas físicamente disponibles ahora, afectadas por envíos) y debe mostrarse aparte, no mezclado, idealmente alimentando el KPI "listas para embarcar" de la Fase 3.1.

### 🚨 Bug confirmado — `storeDelivery()` está roto (columnas inexistentes)

`SaleController::storeDelivery()` (ruta `sales.deliveries.store`) intenta:
```php
SaleDelivery::create([
    'sale_detail_id' => $saleDetail->id,
    'quantity_delivered' => $request->quantity,
    'user_id' => auth()->id(),      // ❌ columna no existe en sale_deliveries
    'delivered_at' => now(),        // ❌ columna no existe en sale_deliveries
]);
```
La tabla `sale_deliveries` (migración real) solo tiene `shipment_id` (obligatorio, sin `nullable()`), `sale_detail_id`, `quantity_delivered`. Esta llamada truena en cualquier escenario. Es probable que este método sea un remanente de una versión anterior al módulo de Embarques (v2.6), reemplazado de facto por `ShipmentController::store()`, y que nunca se limpió ni se desconectó de la ruta.

### Flujo de Stock — Recomendación de documentación (una vez corregido)
1. Al crear/confirmar pedido: stock NO se toca.
2. Al marcar piezas como terminadas (`production_completions`): stock SUBE en `product_variants`.
3. Al crear embarque (`shipments.store`): stock BAJA de forma transaccional. **Este debe ser el único camino de salida de stock** — desactivar el descuento en `SaleController::updateStage`.
4. Cancelar un embarque antes de confirmar entrega: **pendiente de implementar** (no existe hoy).

### Roles y Redirección al Login
- `admin` → `/dashboard`
- `vendedor` → `/dashboard`
- `produccion` → `/production-plan`
- `inventario`, `supervisor`, `financiero` → `/dashboard` (pantalla de bienvenida temporal — sin módulo propio todavía)

### Middleware CheckRole
- Usa parámetros variádicos (`string ...$roles`) — forma correcta de Laravel. Confirmado en código.
- `role:admin,produccion` → Laravel pasa `['admin', 'produccion']` como array automáticamente.

### Pagos (mecanismo actual, por venta — ver sección 9 para el diseño de cuenta por cliente)
- Anticipo inicial en `sales.paid_amount` al crear.
- Abonos posteriores en `sale_payments`, acumulados en `sales.paid_amount`.
- Validación: `monto_abono <= (total - paid_amount)`.
- Registrar pagos hoy vive en zona `role:admin,vendedor` — **Finanzas todavía no tiene acceso a esto** (pendiente, ver sección 9).

### PDFs en Hosting Compartido
- Variable `FILESYSTEM_PUBLIC_ROOT` en `.env` para rutas físicas absolutas.
- Fallback a `public_path('storage/...')` si no existe.

---

## 6. Componentes Vue — Estado Real

| Componente | Estado | Notas |
|-----------|--------|-------|
| `Dashboard.vue` | ✅ | KPIs por rol; pantalla bienvenida para roles sin dashboard (produccion, inventario, supervisor, financiero) |
| `Sales/Create.vue` | ✅ | POS completo |
| `Sales/Index.vue` | ✅ | Kanban |
| `Sales/Show.vue` | ✅ | Modo Oficina/Taller, abonos |
| `Production/Index.vue` | ⚠️ No auditado en esta sesión (no incluido en el zip). Confirmar si ya tiene navegación de semana (el controlador ya soporta `start_date`) y si muestra `total_completed` (hoy llega en 0 desde el backend — ver bug arriba) |
| `Products/Create.vue` | ✅ | Variantes, materiales, imagen, favorito |
| `Products/Edit.vue` | ✅ | Upsert variantes, lightbox |
| `Products/Index.vue` | ✅ | Paginación local, toggle favorito |
| `Clients/*` | ✅ (no auditado en detalle esta sesión) | |
| `Users/*` | ✅ (no auditado en detalle esta sesión) | |
| `Settings/Index.vue` | ✅ (no auditado en detalle esta sesión) | |
| `Shipments/Index.vue` | ✅ | Lista de viajes, imprimir y confirmar entrega — **pendiente: filtro por cliente en Create, no en Index** |
| `Shipments/Create.vue` | ✅ | Armar embarque; validación `Math.min()` contra negativos — **pendiente: filtro de clientes (ver Backlog Fase 2.5)** |
| `Shipments/Show.vue` | ✅ | Detalle con `chosen_color` y fechas |

---

## 7. Arquitectura de Piezas Clave

### `SaleObserver.php`
- Al **crear** venta → registra en `sale_histories` automáticamente.
- Al **actualizar** stage → registra `from_stage` / `to_stage` automáticamente.
- No tocar para lógica de stock — pero ver sección 5: `SaleController::updateStage` SÍ toca stock directamente además del Observer, son cosas distintas.

### `CheckRole.php`
- Parámetro variádico: `handle(Request $request, Closure $next, string ...$roles)`. Confirmado correcto en código real.
- Registrado en `bootstrap/app.php` como alias `'role'`.

### `HandleInertiaRequests.php`
- Comparte `auth.user` globalmente → disponible en cualquier `.vue` como `$page.props.auth.user`.

### `AppServiceProvider.php`
- Registra `SaleObserver`, fuerza HTTPS en producción, `Carbon::setLocale('es')`.

### `AuthenticatedSessionController.php`
- Login usa `redirect()->away()` (no `redirect()->intended()`) para forzar recarga completa.
- Logout también usa `redirect()->away('/')` por la misma razón.

---

## 8. Paquetes Instalados

```
PHP: barryvdh/laravel-dompdf, laravel-lang/common
JS: vue-signature-pad, sweetalert2, lodash
```

---

## 9. Matriz de Roles y Permisos (Propuesta — a implementar)

6 roles: `admin`, `supervisor`, `vendedor`, `inventario`, `produccion`, `financiero`.

| Módulo | Admin | Supervisor | Vendedor | Inventarios | Producción | Finanzas |
|---|---|---|---|---|---|---|
| Dashboard | Selector: Ventas/Producción/Financiero/Todo (Fase 3.3) | Solo lectura, vista global | Su propio dashboard | Vista de inventario/stock | Vista de producción (Fase 3.1) | Vista financiera / cartera (Fase 3.2) |
| POS / Crear pedido | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ |
| Ventas (Kanban, mover etapa) | ✅ todas | 👁️ ver todas | ✅ solo las suyas | ❌ | ❌ | 👁️ ver todas |
| Clientes (CRUD) | ✅ completo | 👁️ ver | ✅ crear/editar | ❌ | ❌ | 👁️ ver + estado de cuenta |
| Plan de Producción | ✅ | 👁️ ver | ❌ | ❌ | ✅ ver + registrar fabricado | ❌ |
| Embarques (crear/confirmar) | ✅ | 👁️ ver | ❌ | ✅ crear/confirmar | 👁️ ver (opcional) | 👁️ ver en tránsito + entregados |
| Pagos/Abonos (`sale_payments`) | ✅ | ❌ | ✅ (de sus ventas) | ❌ | ❌ | ✅ registrar, por cliente |
| Reporte Cartera/Cobranza | ✅ | 👁️ ver | ❌ | ❌ | ❌ | ✅ |
| Productos/Inventario (CRUD) | ✅ | 👁️ ver | 👁️ ver (para POS) | ✅ editar stock/variantes | 👁️ ver | ❌ |
| Usuarios | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Configuración | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |

**Decisiones de diseño detrás de esta matriz:**
- **Embarques pasa a ser dominio de Inventarios** (no de Producción). Razón: `shipments.store` descuenta `product_variants.stock` — es una operación de salida de almacén, no de manufactura. Producción se limita a fabricar y reportar piezas terminadas.
- **Supervisor es de solo lectura** en todo el sistema operativo (Ventas, Producción, Embarques, Cartera) — rol de supervisión transversal, sin capacidad de crear/editar/mover nada. Hoy no tiene ningún acceso real (cae a pantalla de bienvenida); esta matriz define su alcance desde cero.
- **Finanzas no puede crear pedidos.** Solo interactúa con lo que ya existe: ve ventas, ve embarques (en tránsito y entregados) y registra pagos/abonos ligados a la cuenta del cliente.

### 🔒 Matriz de Visibilidad de Precios (DECIDIDO con cliente, Julio 2026)

Directriz del cliente: **nadie ve precios excepto Admin y Finanzas — con la excepción explícita del Vendedor, que los necesita para hacer su trabajo (armar ventas en el POS, ver sus propias ventas y su propio ingreso).**

⚠️ **Nota técnica importante:** ocultar un precio en el componente Vue no es suficiente. Con Inertia, si el controlador manda el modelo completo (`ProductVariant`, `SaleDetail`, etc.) en la respuesta, los campos de precio viajan en el JSON aunque la interfaz no los pinte — visibles desde las herramientas de desarrollador del navegador. La restricción real debe hacerse en el backend, seleccionando explícitamente qué columnas se envían según el rol del usuario autenticado (`->select([...])` o transformar la respuesta antes de `Inertia::render()`), no solo con `v-if` en el frontend. Esto aplica sobre todo a `Supervisor`, `Inventarios` y `Producción`, que sí pueden llegar a tocar pantallas donde viaja el modelo de venta/producto.

| Pantalla / Dato | Admin | Finanzas | Supervisor | Vendedor | Inventarios | Producción |
|---|---|---|---|---|---|---|
| Catálogo de Productos (`price_1`..`price_5`) | ✅ | ✅ | ❌ | ❌ (no tiene acceso al CRUD de Productos, solo lo consume dentro del POS) | ❌ | ❌ |
| POS — precio unitario al armar la venta | ✅ | — | — | ✅ **necesario para operar** | — | — |
| POS — total a cobrar / cambio | ✅ | — | — | ✅ | — | — |
| Kanban de Ventas — columnas Total / Pagado | ✅ | ✅ | ❌ (solo folio, cliente, etapa, fechas) | ✅ (solo de sus propias ventas, como ya es hoy) | ❌ | ❌ |
| Detalle de Venta — Modo Oficina (con precios) | ✅ | ✅ | ❌ | ✅ (solo sus propias ventas) | ❌ (sin acceso al módulo) | ❌ (sin acceso al módulo) |
| Detalle de Venta — Modo Taller (sin precios, ya existe hoy) | — | — | — | — | ✅ si necesita ver el pedido | ✅ |
| Dashboard — Ingresos / KPIs en $ | ✅ | ✅ | ❌ | ✅ (solo su propio ingreso, como ya es hoy) | ❌ | ❌ |
| Embarques — valor de las piezas embarcadas | ✅ | ✅ | ❌ | — | ❌ (arma el viaje solo con cantidades, sin montos) | — |
| Clientes — `price_tier` (nivel A-E, sin cifra en $) | ✅ | ✅ | ✅ | ✅ (necesita saber qué lista aplica) | ❌ | ❌ |

**Ya resuelto sin cambios:** el catálogo de Productos hoy vive dentro de `role:admin` en `web.php` — ningún otro rol, incluido Vendedor, puede acceder al CRUD de Productos. Eso ya cumple la directriz tal cual. Lo que Vendedor sí necesita (y conserva) es ver el precio del producto **dentro del POS**, que es una vista distinta y ya filtrada por `price_tier` del cliente.

**Resumen de la regla final:** Vendedor es la única excepción a "solo Admin y Finanzas ven precios", y esa excepción se limita a **sus propias operaciones** (su POS, sus ventas, su ingreso) — nunca ve precios de catálogo general fuera del POS ni datos de otros vendedores. Supervisor, Inventarios y Producción no ven precios en ningún caso.

### Diseño del "Estado de Cuenta" por cliente (Finanzas)

No se requiere una tabla nueva de saldo. Se calcula agregando datos existentes, agrupados por `client_id`:

```
Deuda exigible del cliente =
    Σ (sale_deliveries.quantity_delivered × sale_details.unit_price)
    para entregas cuyo shipment.status = 'entregado'
    (join: sale_deliveries → shipments, sale_deliveries → sale_details → sales, filtrando por sales.client_id)

Total cobrado del cliente =
    Σ sales.paid_amount + Σ sale_payments.amount
    para todas las ventas de ese cliente

Saldo pendiente = Deuda exigible − Total cobrado
Antigüedad de saldo = se cuenta desde shipments.delivered_at (NO desde promised_date)
```

Esto reemplaza la definición anterior de "cartera vencida" (que comparaba `promised_date` contra `paid_amount` sin considerar si algo se había entregado realmente). La nueva definición es más correcta: **la cartera nace cuando se entrega, no cuando se vence la fecha promesa de un pedido que quizá ni se ha fabricado.**

---

## 10. Variables de Entorno

```ini
APP_ENV=local|production
APP_DEBUG=true|false
APP_URL=http://localhost|https://tudominio.com
DB_CONNECTION=mysql
DB_DATABASE=taller360

# Solo en producción (Neubox):
FILESYSTEM_PUBLIC_ROOT=/home/usuario/public_html/storage
```

---

## 11. Cómo Compartir Contexto con IA al Retomar

Al iniciar sesión nueva:
1. Comparte este archivo (`CONTEXTO_TECNICO.md`)
2. Comparte los archivos específicos de la tarea a trabajar (controlador + vista Vue involucrados, y la migración si aplica)
3. Describe qué quieres hacer

Para tareas grandes de Fase 2 en adelante, abre sesiones separadas por sub-tarea.