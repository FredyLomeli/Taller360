# 🧠 CONTEXTO TÉCNICO — TALLER 360
**Versión Real:** 2.6 | **Auditado directamente contra el código fuente (zip del proyecto):** 25 de julio 2026
**Para:** Retomar desarrollo con IA o desarrollador nuevo sin perder contexto.
**IMPORTANTE:** A diferencia de la versión anterior de este documento (que auditaba solo texto/reportes previos), esta versión se verificó línea por línea contra controladores, modelos, migraciones, rutas y componentes Vue reales. Compartir siempre este archivo al iniciar una nueva sesión.

---

## 0.1 Acuerdos de la reunión con cliente (04 ago 2026) — pendientes de construir

Estos 5 puntos salieron de una reunión con el cliente y ya tienen diseño técnico acordado con el desarrollador. Van en orden de prioridad para la siguiente ronda de código.

### A. Supervisor con permisos completos en Producción, Almacén y Embarques
**Decisión:** Supervisor deja de ser "sin módulo" y pasa a tener los mismos permisos que Admin en esos tres módulos específicos — no en Ventas/Kanban ni Configuración salvo que se indique lo contrario. Cambio: agregar `supervisor` a los grupos de middleware `role:admin,produccion` (Producción) y `role:admin,inventario` (Embarques), y dar acceso de escritura en Productos/Inventario donde hoy es de solo lectura.

### B. Bug — pedido "entregado" cancelado cae en limbo (`enviado` invisible)
**Causa raíz confirmada:** `ShipmentController::cancel()` revierte la etapa leyendo `SaleHistory::latest()->from_stage`, y para un pedido que llegó a `entregado`, esa transición previa fue `enviado → entregado` — revierte a `enviado`, etapa que el Kanban actual ya no muestra (`Sales/Index.vue` solo maneja `pedido/confirmado/producción/cancelado`). Contribuye el hecho de que `store()` crea un `SaleHistory` manual duplicado sin `from_stage`, además del automático de `SaleObserver`.
**Decisión de arreglo (acordada):** reemplazar la lectura de historial por un recálculo en vivo, igual al patrón que ya usa `closeOrderIfComplete()` — al cancelar, si quedan piezas sin entregar, la etapa siempre vuelve a `producción` (ya sea que falte fabricar o que ya esté en stock esperando reembarque), nunca a un valor histórico. Adicionalmente, quitar el `SaleHistory::create()` manual y duplicado de `store()`, dejando que `SaleObserver` sea la única fuente de verdad del historial de etapas.

### C. Órdenes de Trabajo — producción sin pedido + pausa de remanentes parciales
**Necesidad del cliente:** (1) puede producir por anticipado sin que exista un pedido todavía (conoce la demanda de temporada), y (2) cuando un envío parcial deja piezas sin fabricar, no quiere que el sistema las marque automáticamente como urgentes en el taller — quiere decidir él cuándo se fabrica el remanente.

**Diseño acordado — nueva tabla `work_orders`:**
```
id, product_variant_id (FK), quantity_requested (int),
target_date (date, nullable), status (string: 'abierta'|'cerrada', default:'abierta'),
origin_sale_detail_id (FK → sale_details, nullable — si nace de un remanente parcial),
notes (text, nullable), created_by (FK → users), timestamps
```
- Aparece en el Plan de Producción mezclada con las necesidades de pedidos normales (mismo query, unión de dos fuentes).
- Al cerrarla se registra cuánto se terminó realmente y esas piezas entran directo a `product_variants.stock` — mismo mecanismo que ya usa `production_completions`, pero sin requerir una venta.
- `production_completions.sale_detail_id` pasa a ser nullable; se agrega `work_order_id` (FK, nullable) — una fila de avance pertenece a una u otra fuente, nunca a ambas.

**Diseño acordado — pausa de remanentes (`sale_details.production_hold`):**
```
sale_details.production_hold (boolean, default: false)
```
- Se activa automáticamente la primera vez que una línea recibe un envío parcial (queda pieza sin entregar Y sin fabricar todavía).
- Mientras está en `true`, `ProductionController::index()` excluye esa cantidad remanente de "pendiente de fabricar" — aparece en una sección aparte ("en espera de decisión"), no mezclada con lo urgente.
- Cualquier usuario con acceso a Producción (`role:admin,produccion,supervisor` tras el cambio A) puede "liberar a producción" — apaga el flag y/o genera un `work_order` con `origin_sale_detail_id` apuntando a esa línea.

### D. Stock mínimo por variante, solo para productos preferentes
**Decisión:** el campo nuevo vive en `product_variants`, no en `products` — el stock siempre se ha manejado por variante (confirmado: la alerta actual de Dashboard ya consulta `ProductVariant`, no `Product`). "Preferente" sigue siendo el `is_favorite` que ya existe; no se crea concepto nuevo, solo se usa como filtro de visibilidad del campo.
```
product_variants.min_stock (int, nullable)
```
- El formulario de variantes solo pide/muestra `min_stock` cuando el producto padre tiene `is_favorite = true`.
- La alerta de stock crítico del Dashboard cambia de `stock <= 5` (fijo) a `stock <= COALESCE(min_stock, 5)` — mantiene el comportamiento actual como default si un producto se marca preferente sin definir aún su mínimo por variante.
- Cambiar de temporada es simplemente desmarcar/marcar `is_favorite` — no requiere migrar ni tocar `min_stock` de variantes que ya no importan.

### E. Envío automático de la nota de venta al crear el pedido
**Hallazgo:** la lógica ya existe completa en `SaleController::sendEmail()` (correo del cliente + `settings.notification_emails` + PDF adjunto vía `SaleNoteEmail`), pero solo se dispara manualmente desde un botón en `Sales/Index.vue` — nunca se llama dentro de `store()`.
**Decisión de diseño:**
1. Extraer la lógica de `sendEmail()` a un método privado reutilizable (p. ej. `sendSaleNoteMail(Sale $sale)`), usado tanto por el envío automático como por el botón manual.
2. Nuevo `Setting`: `auto_email_on_sale` (boolean, default: `true` — habilitado desde el día uno para que el cliente empiece a probarlo).
3. Al final de `store()`, fuera de la transacción de BD (el envío de correo es I/O, no debe competir por locks), si `auto_email_on_sale` está activo: enviar el correo. Si falla (SMTP mal configurado, etc.), **no debe revertir ni bloquear la creación del pedido** — solo registrar el error.
4. El botón manual en `Sales/Index.vue` se queda intacto y funciona **sin importar el estado del interruptor** — sirve para reenviar la nota cuando haga falta, independientemente de si el envío automático está prendido o apagado.
5. `SettingController` ya lista las claves permitidas (`'notification_emails', 'allow_negative_stock', 'ticket_footer_text'`) — agregar `'auto_email_on_sale'` a esa lista.

## 0. Hallazgos de la ronda 2 de auditoría (25 jul 2026, con `UserController.php`, `package.json`, `vite.config.js`)

### ✅ Cerrado — `UserController.php` sí existe
La ronda 1 marcó como crítico que `UserController.php` no viniera en el zip pese a estar referenciado en `routes/web.php`. Confirmado con el archivo real: existe, CRUD completo (`index`, `create`, `store`, `edit`, `update`, `destroy`), con protección contra auto-eliminación (`if (auth()->id() == $user->id)`) y `VALID_ROLES = 'admin,vendedor,produccion,inventario,supervisor,financiero'` — coincide exactamente con los 6 roles usados en el resto del sistema. `User::$fillable` incluye `role`. Sin pendientes aquí.

### 🆘 Conflicto de versiones de Tailwind CSS — confirmado con `tailwind.config.js` real

`package.json` confirma **dos setups de Tailwind instalados a la vez**:
```json
"tailwindcss": "^3.2.1",        // v3 clásico
"@tailwindcss/vite": "^4.0.0",  // plugin exclusivo de v4
"postcss": "^8.4.31",
"autoprefixer": "^10.4.12"
```
`vite.config.js` confirma que **no** se usa el plugin `@tailwindcss/vite` (solo `laravel()` y `vue()` están registrados). `resources/css/app.css` confirma sintaxis v3. El proyecto corre en Tailwind v3 real; `@tailwindcss/vite ^4.0.0` es peso muerto.

`tailwind.config.js` confirmado — configuración **mínima**:
```js
theme: { extend: { fontFamily: { sans: ['Figtree', ...defaultTheme.fontFamily.sans] } } },
plugins: [forms], // @tailwindcss/forms ^0.5.3
```
Sin colores, espaciados, breakpoints ni `@apply` custom. La fuente Figtree se carga vía `<link>` externo a fonts.bunny.net en `resources/views/app.blade.php`, no como `@font-face` local — no requiere migración especial. **Esto significa que una migración completa a Tailwind v4 sería de bajo riesgo** si se decide hacer, y no solo quedarse en v3. Ruta exacta de migración en `GUIA_RUTA.md`.

---

## 1. Stack Tecnológico

| Capa | Tecnología | Confirmado en |
|------|-----------|-------|
| Backend | Laravel 12.62.0 (PHP 8.2+) | `composer.lock` |
| Frontend | Vue `^3.4.0` (`<script setup>`) | `package.json` |
| Puente | Inertia.js `@inertiajs/vue3 ^2.0.0` | `package.json`, sin rutas `/api/` en `routes/web.php` |
| Build | Vite `^7.0.7` + `laravel-vite-plugin ^2.0.0` + `@vitejs/plugin-vue ^6.0.7` | `package.json`, `vite.config.js` |
| Estilos | Tailwind CSS `^3.2.1` (activo) — ⚠️ `@tailwindcss/vite ^4.0.0` instalado sin usar, ver sección 0 | `package.json`, `resources/css/app.css` |
| BD | MySQL / MariaDB InnoDB | migraciones |
| PDFs | barryvdh/laravel-dompdf 3.1.2 (dompdf/dompdf 3.1.5) | `composer.lock`, confirmado exacto |
| Firma Digital | vue-signature-pad `^3.0.2` | `package.json`, `Sales/Create.vue` |
| Alertas | sweetalert2 `^11.26.17` | `package.json` |
| Utilidades JS | lodash `^4.17.21` | `package.json` |
| Hosting objetivo | Neubox (Hosting Compartido / cPanel) | `.env` de producción documentado en README |

> `vite.config.js` confirmado: solo registra los plugins `laravel()` (apuntando a `resources/css/app.css` y `resources/js/app.js`, con `refresh: true`) y `vue()` (con `transformAssetUrls` configurado). No incluye el plugin de Tailwind v4 — consistente con que el proyecto corre en v3 vía PostCSS.

> **Arquitectura clave:** Inertia.js conecta Laravel con Vue sin API REST. Los controladores devuelven `Inertia::render('Carpeta/Vista', $datos)`.

> ✅ **Confirmado sobre `/`:** la ruta raíz devuelve `view('catalogo.index')` — es una **vista Blade estática con datos hardcodeados** (categorías fijas "Roperos/Trincheros/Bases", un solo producto de ejemplo "Ropero Clásico Santa Cecilia"). No consulta `products`, `categories` ni `product_variants`. La Fase 4 (Catálogo Público) no tiene avance funcional real, solo este mockup visual.

---

## 2. Estructura de Base de Datos (Schema Real Completo — confirmado contra migraciones)

### 👤 `users`
```
id, name, email (unique), password,
role (string: 'admin'|'vendedor'|'produccion'|'inventario'|'supervisor'|'financiero', default:'vendedor'),
email_verified_at, remember_token, timestamps
```
- `role` es un string directo en la tabla, NO una tabla separada de roles.
- ⚠️ No se pudo confirmar `VALID_ROLES` porque `UserController.php` no está en el código auditado (ver hallazgo crítico, sección 0).

### 👥 `clients`
```
id, name, business_name (nullable), price_tier (int 1-5, default:1),
email (unique), phones,
street_address, neighborhood, city, state, delegation, zip_code (todos nullable),
references (text, nullable), timestamps
```
- `price_tier` 1-5 → se muestra como Listas A, B, C, D, E en el frontend.
- **`catalog_token` NO existe todavía** — Fase 4.2 sigue sin construir.

### 🗂️ `categories`
```
id, name, timestamps
```

### 📦 `products`
```
id, category_id (FK → categories),
name, description (nullable),
image (nullable, ruta relativa al disco public),
is_favorite (boolean, default:false), timestamps
```
> ⚠️ NO existe campo `color` ni `measurements` (movido a `product_variants`, confirmado en migración). El color es atributo de la venta (`sale_details.chosen_color`).

### 🎨 `product_variants`
```
id, product_id (FK → products, cascade delete),
measurements (string, obligatorio), material (string, obligatorio),
stock (int, default:0), sku (nullable), stock_notes (nullable),
price_1 (decimal, obligatorio), price_2..price_5 (decimal, nullable),
timestamps
```
- Un producto tiene N variantes (material + medida).
- El stock sube al registrar producción (`production_completions`, confirmado en `ProductionController::storeCompletion`) y baja al confirmar un embarque (`ShipmentController::store`, confirmado con `lockForUpdate`).
- ✅ **Confirmado resuelto:** el motor de etapas del Kanban (`SaleController::updateStage`) **ya no toca stock**. Embarques es el único mecanismo de salida. (Antes era un bug crítico documentado; ver sección 5.)

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
⚠️ `promised_date` solo se captura al crear el pedido (`SaleController::store()`). **Confirmado: no existe forma de editarla después** — pendiente real, no se toca en `updateStage()` ni en ningún otro método.

### 📋 `sale_details`
```
id, sale_id (FK → sales, cascade), product_variant_id (FK → product_variants),
product_name (snapshot, incluye medida y material), quantity (int),
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
- Los pagos están ligados a `sale_id`, no a un cliente directamente. El "estado de cuenta por cliente" (Fase 3.2, Finanzas) se construye agregando esta tabla + `sales.paid_amount` por `client_id` — no requiere cambio de esquema.
- Registrado por `SalePaymentController::store()`, confirmado con validación de deuda (`amount <= total - paid_amount`) y transacción atómica.

### ⚙️ `settings`
```
id, key (unique), value (text, nullable), timestamps
```
Claves usadas: `company_name`, `company_rfc`, `company_address`, `company_phone`, `company_logo`, `notification_emails`, `allow_negative_stock`, `ticket_footer_text`.
> 🆕 Acordado, pendiente de construir: `auto_email_on_sale` (boolean, default `true`) — interruptor del envío automático de la nota de venta al crear el pedido. Ver punto E en la sección 0.1.

### 🛠️ `production_completions` (v2.6)
```
id, sale_detail_id (FK → sale_details),
quantity_completed (int),
user_id (FK → users),
completed_at (timestamp),
timestamps
```
- Registra cuando el taller termina una pieza física. NO cambia el `stage` global del pedido — solo acumula piezas listas en bodega y suma al `stock` de `product_variants`.
- Es la única fuente confiable de "cuánto se ha fabricado en total" para un pedido — no usar `product_variants.stock` para eso, porque el stock también se ve afectado por embarques.

### 🚚 `shipments` (v2.6)
```
id, driver_name (nullable), license_plate (nullable), destination (nullable),
status (string, default:'en_transito' — valores usados: 'en_transito','entregado','cancelado'),
shipped_at (nullable), pickup_type (string, default:'flota_propia'),
delivered_at (nullable), notes (text, nullable),
user_id (FK → users), timestamps
```
- ✅ **Confirmado:** `pickup_type` (`'flota_propia'` / `'recoleccion_cliente'`) existe en la migración y está **implementado completo** en backend y frontend (ver sección 5, ya no es un pendiente).
- ✅ **Confirmado:** `cancel()` existe en `ShipmentController` — el valor `'cancelado'` del `status` sí se usa en código real, ya no es solo un comentario de migración sin implementar.

### 📦 `sale_deliveries` (v2.6)
```
id, shipment_id (FK → shipments, cascade, obligatorio, no nullable),
sale_detail_id (FK → sale_details, cascade),
quantity_delivered (int),
timestamps
```
- Tabla pivote. Permite envíos parciales del mismo pedido en distintos viajes.
- Solo tiene estas 3 columnas + timestamps. El antiguo `SaleController::storeDelivery()` que intentaba escribir `user_id`/`delivered_at` (columnas inexistentes aquí) **ya no existe en el código** — método, ruta y botón del frontend fueron eliminados.

---

## 3. Relaciones Eloquent (confirmadas contra código real)

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
SaleDetail    → belongsTo(Sale)
              → belongsTo(ProductVariant) — el método real es `variant()`, NO `productVariant()`.
                Confirmado en ProductionController y ShipmentController (`->with(['variant.product', ...])`).
                ⚠️ Distinto de la columna cruda: la FK en el JSON hacia el frontend es `product_variant_id`
                (nombre real de la columna en `sale_details`), NO `variant_id`.
              → hasMany(ProductionCompletion) ['completions']
              → hasMany(SaleDelivery) ['deliveries']
SaleHistory   → belongsTo(User)
SalePayment   → belongsTo(Sale), belongsTo(User)
Shipment      → belongsTo(User)
              → hasMany(SaleDelivery) ['deliveries']
SaleDelivery  → belongsTo(Shipment), belongsTo(SaleDetail)
ProductionCompletion → belongsTo(SaleDetail), belongsTo(User)
Setting       → getValue(), setValue(), getAll() [métodos estáticos]
```

---

## 4. Rutas (`routes/web.php`) — Estado real confirmado

| Zona | Middleware real | Rutas incluidas |
|------|-----------|----------------|
| Pública | — | `/` (catálogo Blade estático), login, register |
| Dashboard | `auth,verified` (todos) | `/dashboard` — el controlador decide qué mostrar según rol |
| Perfil | `auth,verified` | `/profile` |
| Ventas | `role:admin,vendedor` | `/pos`, `/sales/*`, `/clients` (crear/editar), pagos |
| Taller | `role:admin,produccion` | `/production-plan`, `/production-plan/complete`, `/production-plan/print` |
| Admin | `role:admin` | `/users` (✅ `UserController` confirmado completo), `/products`, `/clients/{id}` (destroy), `/configuracion` |
| **Embarques** | ✅ `role:admin,inventario` | `/shipments/*` (index, create, store, show, confirm, print, **cancel**) |

> ✅ **Confirmado resuelto:** las rutas de `/shipments/*` ya están restringidas a `admin,inventario`. Antes era un bug crítico (cualquier usuario autenticado podía crear/confirmar embarques); ya no es el caso.

### Rutas de Embarques (confirmadas completas)

| Método | URI | Nombre | Descripción |
|--------|-----|---------|-------------|
| GET | `/shipments` | `shipments.index` | Historial de viajes |
| GET | `/shipments/create` | `shipments.create` | UI para armar embarque (soporta `?client_ids[]=`) |
| POST | `/shipments` | `shipments.store` | Guardar embarque (transaccional, con `lockForUpdate`) |
| GET | `/shipments/{id}` | `shipments.show` | Detalle del viaje |
| GET | `/shipments/{id}/print` | `shipments.print` | PDF Remisión — **no agrupa por cliente/pedido, es un listado plano** (ver sección 5) |
| PATCH | `/shipments/{id}/confirm` | `shipments.confirm` | Confirmar entrega y cerrar pedidos (revisa TODAS las líneas) |
| PATCH | `/shipments/{id}/cancel` | `shipments.cancel` | ✅ Cancelar embarque y regresar stock — confirmado implementado |

---

## 5. Reglas de Negocio — Estado Real (25 jul 2026)

### ✅ RESUELTO — Doble mecanismo de descuento de stock

`SaleController::updateStage()` confirmado: su `validate()` solo acepta `pedido,confirmado,produccion,cancelado` — ya no permite mover a mano a `enviado` o `entregado`, y no toca stock en absoluto. `ShipmentController::store()` es ahora el único punto que descuenta stock (con `ProductVariant::lockForUpdate()` para evitar condiciones de carrera entre líneas o usuarios concurrentes), y `ShipmentController::cancel()` es el único que lo regresa.

### ✅ RESUELTO — Plan de Producción no descontaba lo ya fabricado

`ProductionController::index()` y `printReport()` confirmados con:
```php
->withSum('completions as completed_quantity', 'quantity_completed')
...
'pending_to_fabricate' => max(0, $totalNeeded - $totalCompleted), // ya no resta stock
```
Badge de 4 estados confirmado en `Production/Index.vue` (sin fabricar / parcial / listo para embarcar / fabricado y ya embarcado). Navegación de semana (`« Ant.` / `Sig. »`) confirmada implementada. ⚠️ El toggle "Ver todo acumulado" **no existe** en el código — sigue pendiente.

### ✅ RESUELTO — `storeDelivery()` roto

El método, la ruta `sales.deliveries.store` y el botón correspondiente en `Sales/Show.vue` **ya no existen** en el código.

### ✅ RESUELTO — Sin forma de cancelar un embarque

`ShipmentController::cancel()` confirmado, con las reglas de negocio documentadas respetadas en código:
- Flota propia: cancelable solo mientras `en_transito`, no una vez `entregado`.
- Recolección en mostrador: cancelable incluso después de `entregado` (es la única forma de revertir un error de captura, ya que nace directo en ese estado).
- Regresa stock con `increment()`, revierte el `stage` del pedido al `from_stage` real tomado de `sale_histories` (no un valor fijo adivinado).
- Los `withSum('deliveries as delivered_quantity', ...)` en `ShipmentController::create()`, `SaleController::show()` y `closeOrderIfComplete()` confirmados con `whereHas('shipment', fn($q) => $q->where('status', '!=', 'cancelado'))` — un embarque cancelado no vuelve a contarse como entregado.

### ✅ IMPLEMENTADO COMPLETO — Recolección en mostrador vs. flota propia (`pickup_type`)

A diferencia de lo que indicaba el Backlog anterior (marcado como "backend listo, falta selector en frontend"), **está confirmado completo en ambos lados**:
- Backend: `ShipmentController::store()` crea el `Shipment` directo en `status = 'entregado'` con `delivered_at = now()` cuando `pickup_type === 'recoleccion_cliente'`, y ejecuta `closeOrderIfComplete()` en la misma transacción.
- Frontend: `Shipments/Create.vue` confirmado con un toggle visual (`flota_propia` / `recoleccion_cliente`) que cambia el texto del formulario dinámicamente ("Chofer / Repartidor" vs. "Nombre de quien recoge").

### ⚠️ PENDIENTE (confirmado, sin cambios respecto a lo documentado) — Notas de entrega no agrupadas por pedido

`ShipmentController::printManifest()` y la plantilla `resources/views/pdf/shipment_manifest.blade.php` fueron revisados: **no hay agrupación por cliente/pedido**, es un `@foreach($shipment->deliveries as $del)` plano. Si un viaje agrupa piezas de varios clientes, la remisión los mezcla en una sola lista. Sigue pendiente construir la agrupación real.

### ⚠️ PENDIENTE (confirmado) — Filtro multi-cliente en Embarques

`ShipmentController::create()` sí soporta `client_ids[]` vía query param y filtra el `Sale::whereIn('client_id', $clientIds)`. **Pero no existe ningún selector en `Shipments/Create.vue`** que mande ese parámetro — el backend está listo, la UI no.

### ⚠️ Patrón de rendimiento — modelos completos viajando sin usarse

Confirmado ya corregido en `ShipmentController::create()`, `index()` y `show()`: usan `select()` explícito, sin `signature` (base64 pesado) ni columnas de precio para roles de Inventarios.

**Pendiente confirmado, sin corregir todavía:**
- `ProductController::index()` — `Product::with(['category','variants'])->get()` sin `select()` ni paginación: carga todos los productos con todas sus variantes completas (incluidos los 5 precios) en cada visita al catálogo interno.
- `SaleController::create()` (POS) — `Client::all()` sin límite ni `select()`, se ejecuta en cada apertura del POS.

### Roles y Redirección al Login
- `admin`, `vendedor` → `/dashboard`
- `produccion` → `/production-plan`
- `inventario`, `supervisor`, `financiero` → `/dashboard` (pantalla de bienvenida temporal — sin módulo propio todavía, confirmado en `DashboardController`)

### Modo Oficina / Modo Taller (`Sales/Show.vue`)
Confirmado: `is_production_mode` sigue siendo un prop controlado solo por el query param `?production=` en el frontend (`const productionMode = ref(props.is_production_mode || false)`). **No hay lógica de backend que lo fuerce por rol** — sigue pendiente forzarlo para `supervisor`, `inventario` y `produccion` independientemente del query param.

---

## 6. Componentes Vue — Estado Real (confirmado contra código)

| Componente | Estado | Notas |
|-----------|--------|-------|
| `Dashboard.vue` | ✅ | KPIs por rol; pantalla bienvenida para roles sin dashboard propio |
| `Sales/Create.vue` | ✅ | POS completo |
| `Sales/Index.vue` | ✅ | Kanban — ya no permite mover a mano a `enviado`/`entregado` |
| `Sales/Show.vue` | ✅ | Modo Oficina/Taller (por query param, no forzado por rol aún), abonos |
| `Production/Index.vue` | ✅ | Navegación de semana y badge de 4 estados confirmados. Falta toggle "ver acumulado". `formatDate` sin usar ya **no existe** (código limpio, bug previamente documentado ya no aplica) |
| `Products/Create.vue` / `Edit.vue` | ✅ | Variantes, materiales, medidas, imagen, favorito |
| `Products/Index.vue` | ⚠️ | Funcional, pero `ProductController::index()` no pagina server-side |
| `Clients/*`, `Users/*`, `Settings/Index.vue` | ✅ | `Users/*` confirmado funcional contra `UserController.php` real (CRUD completo, `VALID_ROLES` coincide con los 6 roles del sistema) |
| `Shipments/Index.vue` | ✅ | Lista de viajes, imprimir, confirmar, **cancelar** (badge de 3 estados: en tránsito/entregado/cancelado) |
| `Shipments/Create.vue` | ✅ | Armar embarque, validación de stock compartido entre líneas del mismo formulario, **toggle flota propia / recolección en mostrador**. Falta: selector multi-cliente |
| `Shipments/Show.vue` | ✅ | Detalle con `chosen_color`, fechas y piezas a bordo |

---

## 7. Arquitectura de Piezas Clave

### `SaleObserver.php`
- Al **crear** venta → registra en `sale_histories` automáticamente.
- Al **actualizar** stage → registra `from_stage` / `to_stage` automáticamente.
- No toca stock — eso ahora vive únicamente en `ShipmentController`.

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

## 8. Paquetes Instalados (confirmado con `composer.lock` y `package.json` reales)

```
PHP: barryvdh/laravel-dompdf ^3.1.1 (resuelve a 3.1.2), laravel-lang/common,
     laravel/framework ^12.0 (resuelve a 12.62.0)

JS — dependencies (runtime):
     lodash ^4.17.21, sweetalert2 ^11.26.17, vue-signature-pad ^3.0.2

JS — devDependencies (build/tooling):
     @inertiajs/vue3 ^2.0.0, @tailwindcss/forms ^0.5.3, @tailwindcss/vite ^4.0.0 (⚠️ sin usar, ver sección 0),
     @vitejs/plugin-vue ^6.0.7, autoprefixer ^10.4.12, axios ^1.18.0, concurrently ^9.0.1,
     laravel-vite-plugin ^2.0.0, postcss ^8.4.31, tailwindcss ^3.2.1, vite ^7.0.7, vue ^3.4.0
```

---

## 9. Matriz de Roles y Permisos (Propuesta — parcialmente implementada, confirmado contra rutas reales)

6 roles: `admin`, `supervisor`, `vendedor`, `inventario`, `produccion`, `financiero`.

| Módulo | Admin | Supervisor | Vendedor | Inventarios | Producción | Finanzas |
|---|---|---|---|---|---|---|
| Dashboard | ✅ implementado | ❌ sin módulo (pantalla bienvenida) | ✅ implementado | ❌ sin módulo | ❌ sin módulo | ❌ sin módulo |
| POS / Crear pedido | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ |
| Ventas (Kanban, mover etapa) | ✅ todas | ❌ fuera de alcance de la reunión del 04 ago | ✅ solo las suyas | ❌ | ❌ | ❌ sin acceso hoy |
| Clientes (CRUD) | ✅ completo | ❌ sin acceso hoy | ✅ crear/editar | ❌ | ❌ | ❌ sin acceso hoy |
| Plan de Producción | ✅ | 🆕 **acordado — acceso completo, mismos permisos que Admin** | ❌ | ❌ | ✅ ver + registrar fabricado | ❌ |
| Embarques (crear/confirmar/cancelar) | ✅ | 🆕 **acordado — acceso completo, mismos permisos que Admin** | ❌ | ✅ | ❌ sin acceso hoy | ❌ sin acceso hoy |
| Pagos/Abonos (`sale_payments`) | ✅ | ❌ | ✅ (de sus ventas) | ❌ | ❌ | ❌ sin acceso hoy |
| Productos/Inventario (CRUD) | ✅ | 🆕 **acordado — acceso completo, mismos permisos que Admin** | 👁️ ver (para POS) | ❌ sin acceso hoy | ❌ | ❌ |
| Usuarios | ✅ confirmado completo | ❌ | ❌ | ❌ | ❌ | ❌ |
| Configuración | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |

**Diferencia importante con la matriz "propuesta" de versiones anteriores de este documento:** antes describía el diseño *deseado* a futuro (Supervisor de solo lectura en todo). **Acuerdo de reunión (04 ago 2026):** eso cambió — el cliente ahora quiere a Supervisor con permisos de edición completos (no solo lectura) en Producción, Almacén y Embarques, al mismo nivel que Admin. Sigue sin acceso a Ventas/Kanban ni Configuración salvo que se amplíe después. `financiero` sigue sin ninguna ruta asignada — Fase 3, sin empezar.

### 🔒 Matriz de Visibilidad de Precios — estado real

Confirmado en código:
- `ShipmentController::create()` ya no manda `price_1..price_5` en el JSON (columnas explícitamente excluidas con `variant:id,product_id,material,measurements,stock`).
- `SaleController::index()` ya usa `select()` explícito sin `signature`.
- **Pendiente confirmado:** `SaleController::index()` no condiciona el `select()` por rol (hoy nadie más que admin/vendedor llega a esa ruta, así que no es explotable todavía, pero tampoco está la lógica lista para cuando se abra a Supervisor). `Sales/Show.vue` no fuerza Modo Taller por rol en el backend.

### Diseño del "Estado de Cuenta" por cliente (Finanzas) — sigue siendo diseño, no implementación

No se requiere tabla nueva. Se calcularía agregando `sale_deliveries` (join con `shipments.status = 'entregado'`) contra `sales.paid_amount` + `sale_payments.amount`, agrupado por `client_id`. **Confirmado: no hay ningún controlador ni ruta que calcule esto todavía** — es 100% diseño para la Fase 3.2.

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
1. Comparte este archivo (`CONTEXTO_TECNICO.md`).
2. Comparte los archivos específicos de la tarea a trabajar (controlador + vista Vue involucrados, y la migración si aplica). Si vas a compartir un zip completo, **excluye `vendor/`, `node_modules/`, `storage/logs`, `storage/framework` y `.git`** — no aportan nada y hacen el zip pesado.
3. Si la tarea toca el frontend/build, incluye `tailwind.config.js` y `postcss.config.js` — no se auditaron todavía (ver hallazgo del conflicto de versiones de Tailwind, sección 0).
4. Describe qué quieres hacer.

Para tareas grandes de Fase 3 en adelante, abre sesiones separadas por sub-tarea.
