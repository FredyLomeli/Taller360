# 🧠 CONTEXTO TÉCNICO — TALLER 360
**Versión Real:** 2.6 | **Auditado:** Julio 2026
**Para:** Retomar desarrollo con IA o desarrollador nuevo sin perder contexto.
**IMPORTANTE:** Este archivo refleja el código REAL auditado. Compartirlo siempre al iniciar una nueva sesión.

---

## 1. Stack Tecnológico

| Capa | Tecnología | Notas |
|------|-----------|-------|
| Backend | Laravel 12 (PHP 8.2+) | MVC + Eloquent ORM |
| Frontend | Vue 3 (`<script setup>`) | Composition API |
| Puente | Inertia.js | No hay API REST; los controladores devuelven `Inertia::render()` |
| Estilos | Tailwind CSS | Sin CSS custom salvo scrollbars y print |
| BD | MySQL / MariaDB InnoDB | |
| PDFs | barryvdh/laravel-dompdf | |
| Firma Digital | vue-signature-pad | Captura firma del cliente en el POS |
| Alertas | sweetalert2 | Confirmaciones y toasts en todo el sistema |
| Utilidades JS | lodash (debounce/throttle) | Búsquedas con delay |
| Hosting objetivo | Neubox (Hosting Compartido / cPanel) | |

> **Arquitectura clave:** Inertia.js conecta Laravel con Vue sin API REST. Los controladores devuelven `Inertia::render('Carpeta/Vista', $datos)`. Los componentes Vue reciben los datos como `props`. No hay rutas `/api/` para uso normal del frontend.

---

## 2. Estructura de Base de Datos (Schema Real Completo)

### 👤 `users`
```
id, name, email (unique), password,
role (string: 'admin'|'vendedor'|'produccion'|'inventario'|'supervisor'|'financiero', default:'vendedor'),
email_verified_at, remember_token, timestamps
```
- `role` es un string directo en la tabla, NO una tabla separada de roles.
- 6 roles válidos definidos en `UserController::VALID_ROLES`.

### 👥 `clients`
```
id, name, business_name (nullable), price_tier (int 1-5, default:1),
email (unique), phones,
street_address, neighborhood, city, state, delegation, zip_code (todos nullable),
references (text, nullable), timestamps
```
- `price_tier` 1-5 → se muestra como Listas A, B, C, D, E en el frontend.
- El seeder genera emails `@system.local` para clientes sin email real (se ocultan en UI).

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
- El stock se descuenta al confirmar un embarque (`shipments`), no al cambiar el stage de la venta.

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

### ⚙️ `settings`
```
id, key (unique), value (text, nullable), timestamps
```
Claves usadas: `company_name`, `company_rfc`, `company_address`, `company_phone`, `company_logo`, `notification_emails`, `allow_negative_stock`, `ticket_footer_text`.

### 🛠️ `production_completions` (NUEVO v2.6)
```
id, sale_detail_id (FK → sale_details),
quantity_completed (int),
user_id (FK → users),
completed_at (timestamp),
timestamps
```
- Registra cuando el taller termina una pieza física.
- NO cambia el `stage` global del pedido — solo acumula piezas listas en bodega.
- Al completar, suma al `stock` de `product_variants` (inventario de producto terminado).

### 🚚 `shipments` (NUEVO v2.6)
```
id, driver_name (string), license_plate (string), destination (string),
status (enum: en_transito|entregado, default:'en_transito'),
shipped_at (timestamp), delivered_at (timestamp, nullable),
user_id (FK → users),
timestamps
```
- Entidad que agrupa la carga física de una camioneta en un viaje.
- Al confirmar entrega (`status = 'entregado'`), evalúa si cada pedido involucrado puede cerrarse.

### 📦 `sale_deliveries` (NUEVO v2.6)
```
id, shipment_id (FK → shipments, cascade),
sale_detail_id (FK → sale_details),
quantity_delivered (int),
timestamps
```
- Tabla pivote con cantidad. Permite envíos parciales del mismo pedido en distintos viajes.
- Ejemplo: un pedido de 5 roperos puede ir 2 en el primer viaje y 3 en el segundo.

---

## 3. Relaciones Eloquent

```
User          → hasMany(Sale), hasMany(Shipment), hasMany(ProductionCompletion)
Client        → hasMany(Sale)
Category      → hasMany(Product)
Product       → belongsTo(Category) → hasMany(ProductVariant) [cascade delete]
ProductVariant→ belongsTo(Product)
Sale          → belongsTo(User), belongsTo(Client)
              → hasMany(SaleDetail)
              → hasMany(SaleHistory)->latest()  ['history']
              → hasMany(SalePayment)->latest()  ['payments']
              → getIsPaidAttribute()  [accessor: paid_amount >= total]
SaleDetail    → belongsTo(Sale)
              → belongsTo(ProductVariant, 'product_variant_id')
              → hasMany(ProductionCompletion)
              → hasMany(SaleDelivery)
SaleHistory   → belongsTo(User)
SalePayment   → belongsTo(Sale), belongsTo(User)
Shipment      → belongsTo(User)
              → hasMany(SaleDelivery)
SaleDelivery  → belongsTo(Shipment), belongsTo(SaleDetail)
ProductionCompletion → belongsTo(SaleDetail), belongsTo(User)
Setting       → getValue(), setValue(), getAll()  [métodos estáticos]
```

---

## 4. Rutas (`routes/web.php`)

### Zonas de acceso por rol

| Zona | Middleware | Rutas incluidas |
|------|-----------|----------------|
| Pública | — | `/`, login, register |
| Dashboard | `auth` (todos) | `/dashboard` — el controlador decide qué mostrar según rol |
| Perfil | `auth` | `/profile` |
| Ventas | `role:admin,vendedor` | `/pos`, `/sales/*`, `/clients`, pagos |
| Taller | `role:admin,produccion` | `/production-plan`, `/shipments/*` |
| Admin | `role:admin` | `/users`, `/products`, `/configuracion` |

### Rutas de Embarques (NUEVO v2.6)

| Método | URI | Nombre | Descripción |
|--------|-----|---------|-------------|
| GET | `/shipments` | `shipments.index` | Historial de viajes |
| GET | `/shipments/create` | `shipments.create` | UI para armar embarque |
| POST | `/shipments` | `shipments.store` | Guardar embarque (transaccional) |
| GET | `/shipments/{id}` | `shipments.show` | Detalle del viaje |
| GET | `/shipments/{id}/print` | `shipments.print` | PDF Remisión del viaje |
| PATCH | `/shipments/{id}/confirm` | `shipments.confirm` | Confirmar entrega y cerrar pedidos |

---

## 5. Reglas de Negocio Críticas

### Flujo de Stock (ACTUALIZADO v2.6)
1. Al crear/confirmar pedido: stock NO se toca.
2. Al marcar piezas como terminadas (`production_completions`): stock SUBE en `product_variants`.
3. Al crear embarque (`shipments.store`): stock BAJA de forma transaccional.
4. Si se cancela un embarque antes de confirmar entrega: stock regresa.

### Roles y Redirección al Login
- `admin` → `/dashboard`
- `vendedor` → `/dashboard`
- `produccion` → `/production-plan`
- `inventario`, `supervisor`, `financiero` → `/dashboard` (pantalla de bienvenida temporal)

### Middleware CheckRole
- Usa parámetros variádicos (`string ...$roles`) — forma correcta de Laravel.
- `role:admin,produccion` → Laravel pasa `['admin', 'produccion']` como array automáticamente.
- ⚠️ Usar `string $roles` (sin `...`) solo recibe el primer valor — bug conocido y ya corregido.

### Pagos
- Anticipo inicial en `sales.paid_amount` al crear.
- Abonos posteriores en `sale_payments`, acumulados en `sales.paid_amount`.
- Validación: `monto_abono <= (total - paid_amount)`.

### PDFs en Hosting Compartido
- Variable `FILESYSTEM_PUBLIC_ROOT` en `.env` para rutas físicas absolutas.
- Fallback a `public_path('storage/...')` si no existe.

---

## 6. Componentes Vue — Estado Real

| Componente | Estado | Notas |
|-----------|--------|-------|
| `Dashboard.vue` | ✅ | KPIs por rol; pantalla bienvenida para roles sin dashboard |
| `Sales/Create.vue` | ✅ | POS completo |
| `Sales/Index.vue` | ✅ | Kanban; HTML limpiado (div duplicado corregido) |
| `Sales/Show.vue` | ✅ | Modo Oficina/Taller, abonos |
| `Production/Index.vue` | ✅ | Plan de taller; falta filtro semanal |
| `Products/Create.vue` | ✅ | Variantes, materiales, imagen, favorito |
| `Products/Edit.vue` | ✅ | Upsert variantes, lightbox |
| `Products/Index.vue` | ✅ | Paginación local, toggle favorito |
| `Clients/Create.vue` | ✅ | Dirección completa, tier |
| `Clients/Edit.vue` | ✅ | |
| `Clients/Index.vue` | ✅ | Búsqueda throttle, paginación servidor |
| `Users/Create.vue` | ✅ | 6 roles en select |
| `Users/Edit.vue` | ✅ | Password opcional, protección email propio |
| `Users/Index.vue` | ✅ | Badge de color por rol |
| `Settings/Index.vue` | ✅ | Logo con preview, toggle stock negativo |
| `Shipments/Index.vue` | ✅ | Lista de viajes, imprimir y confirmar entrega |
| `Shipments/Create.vue` | ✅ | Armar embarque; validación `Math.min()` contra negativos |
| `Shipments/Show.vue` | ✅ | Detalle con `chosen_color` y fechas |

---

## 7. Arquitectura de Piezas Clave

### `SaleObserver.php`
- Al **crear** venta → registra en `sale_histories` automáticamente.
- Al **actualizar** stage → registra `from_stage` / `to_stage` automáticamente.
- No tocar para lógica de stock — eso vive en `ShipmentController`.

### `CheckRole.php`
- Parámetro variádico: `handle(Request $request, Closure $next, string ...$roles)`.
- Registrado en `bootstrap/app.php` como alias `'role'`.

### `HandleInertiaRequests.php`
- Comparte `auth.user` globalmente → disponible en cualquier `.vue` como `$page.props.auth.user`.

### `AppServiceProvider.php`
- Registra `SaleObserver`, fuerza HTTPS en producción, `Carbon::setLocale('es')`.

### `AuthenticatedSessionController.php`
- Login usa `redirect()->away()` (no `redirect()->intended()`) para forzar recarga completa y evitar que Inertia intercepte la transición con peticiones XHR intermedias.
- Logout también usa `redirect()->away('/')` por la misma razón.

---

## 8. Paquetes Instalados

```
PHP: barryvdh/laravel-dompdf, laravel-lang/common
JS: vue-signature-pad, sweetalert2, lodash
```

---

## 9. Variables de Entorno

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

## 10. Cómo Compartir Contexto con IA al Retomar

Al iniciar sesión nueva:
1. Comparte este archivo (`CONTEXTO_TECNICO.md`)
2. Comparte los archivos específicos de la tarea a trabajar
3. Describe qué quieres hacer

Para tareas grandes de Fase 2 en adelante, abre sesiones separadas por sub-tarea.