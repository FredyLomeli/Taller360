# 🧠 CONTEXTO TÉCNICO — TALLER 360
**Versión Real:** 2.5 | **Auditado:** Junio 2026
**IMPORTANTE:** Este archivo refleja el código REAL auditado, no la documentación anterior.

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

## 2. Estructura de Base de Datos (Schema Real — Confirmado por Migraciones)

### 👤 `users`
```
id, name, email (unique), password, role (string, default:'vendedor'),
email_verified_at, remember_token, timestamps
+ tablas: password_reset_tokens, sessions
```
- `role` es un string directo en la tabla, NO una tabla separada de roles.
- Valores válidos: `'admin'` | `'vendedor'`

### 👥 `clients`
```
id, name, business_name (nullable), price_tier (int, default:1),
email (unique), phones,
street_address (nullable), neighborhood (nullable), city (nullable),
state (nullable), delegation (nullable), zip_code (nullable),
references (text, nullable), timestamps
```
- `price_tier` 1-5 → se muestra como Listas A, B, C, D, E en el frontend
- `email` tiene unique constraint — el seeder genera emails `@system.local` para clientes sin email real (se ocultan visualmente en `Clients/Index.vue`)

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
> ⚠️ NO existe campo `color` en esta tabla. El color es atributo de la venta.

### 🎨 `product_variants`
```
id, product_id (FK → products, cascade delete),
material (string), sku (nullable), stock (int, default:0),
price_1 (decimal, obligatorio), price_2..price_5 (decimal, nullable),
timestamps
```
- Un producto tiene N variantes (una por material).
- El stock SOLO se descuenta al pasar a `'enviado'`.

### 💼 `sales`
```
id, user_id (FK → users), client_id (FK → clients, nullable),
total (decimal), paid_amount (decimal, default:0),
change_amount (decimal, default:0), payment_method (string, default:'Efectivo'),
signature (longText, nullable — base64 de la firma digital),
stage (enum: pedido|confirmado|produccion|enviado|entregado|cancelado, default:'pedido'),
promised_date (date, nullable), is_partial_shipping (boolean, default:false),
timestamps
```
- `paid_amount` se acumula con cada abono registrado en `sale_payments`.
- `is_paid` NO es columna — es accessor calculado: `paid_amount >= total`.
- Si `paid_amount > 0` al crear, el sistema auto-avanza a `'confirmado'`.

### 📋 `sale_details`
```
id, sale_id (FK → sales, cascade), product_variant_id (FK → product_variants),
product_name (snapshot del nombre al vender), quantity (int),
chosen_color (nullable), custom_notes (text, nullable),
additional_cost (decimal, default:0),
discount_percent (int, default:0), unit_price (decimal), subtotal (decimal),
timestamps
```
- `product_name` guarda el nombre como snapshot para que no cambie si el producto se edita.

### 📜 `sale_histories`
```
id, sale_id (FK → sales, cascade), user_id (FK → users),
from_stage (nullable), to_stage, notes (text, nullable), timestamps
```
- Se crea un registro por cada cambio de etapa.

### 💳 `sale_payments`
```
id, sale_id (FK → sales, cascade), user_id (FK → users),
amount (decimal), payment_method (string),
reference (nullable), paid_at (timestamp), timestamps
```
- Bitácora de abonos POSTERIORES al anticipo inicial.
- El anticipo inicial queda en `sales.paid_amount` al crear la venta.

### ⚙️ `settings`
```
id, key (unique), value (text, nullable), timestamps
```
Claves usadas en el sistema:
- `company_name`, `company_rfc`, `company_address`, `company_phone`
- `company_logo` (ruta relativa al disco public)
- `notification_emails` (separados por coma)
- `allow_negative_stock` (string `'1'` o `'0'`)
- `ticket_footer_text`

---

## 3. Relaciones Eloquent (Confirmadas por Modelos)

```
User          → hasMany(Sale)
Client        → hasMany(Sale)
Category      → hasMany(Product)
Product       → belongsTo(Category)
              → hasMany(ProductVariant)  [cascade delete]
ProductVariant→ belongsTo(Product)
Sale          → belongsTo(User)
              → belongsTo(Client)  [nullable]
              → hasMany(SaleDetail)
              → hasMany(SaleHistory)->latest()  [relación: 'history']
              → hasMany(SalePayment)->latest()  [relación: 'payments']
              → getIsPaidAttribute()  [accessor: paid_amount >= total]
SaleDetail    → belongsTo(Sale)
              → belongsTo(ProductVariant, 'product_variant_id')  [relación: 'variant' o 'productVariant']
SaleHistory   → belongsTo(User)
SalePayment   → belongsTo(Sale)
              → belongsTo(User)
Setting       → getValue($key, $default)  [método estático]
              → setValue($key, $value)    [método estático]
              → getAll()                  [método estático → collection]
```

---

## 4. Rutas (`routes/web.php`) — Confirmadas

| Método | URI | Nombre | Acceso | Descripción |
|--------|-----|---------|--------|-------------|
| GET | `/` | — | Público | Redirect a dashboard si autenticado, sino welcome |
| GET | `/dashboard` | `dashboard` | Auth | KPIs Admin o personal Vendedor |
| GET/PATCH/DELETE | `/profile` | `profile.*` | Auth | Perfil del usuario logueado |
| GET | `/pos` | `sales.create` | Auth | POS — nuevo pedido |
| GET | `/sales` | `sales.index` | Auth | Tablero Kanban |
| POST | `/sales` | `sales.store` | Auth | Guardar pedido |
| GET | `/sales/{sale}` | `sales.show` | Auth | Detalle híbrido Oficina/Taller |
| PATCH | `/sales/{sale}/stage` | `sales.update-stage` | Auth | Cambiar etapa |
| GET | `/sales/{id}/ticket` | `sales.print` | Auth | PDF ticket |
| GET | `/sales/{id}/note` | `sales.printNote` | Auth | PDF nota de venta |
| POST | `/sales/{id}/email` | `sales.email` | Auth | Enviar nota por correo |
| POST | `/sales/{sale}/payment` | `sales.payment.store` | Auth | Registrar abono |
| GET | `/production-plan` | `production.plan` | Auth | Plan de taller |
| Resource | `/clients` | `clients.*` | Auth (destroy: Admin) | CRUD Clientes |
| Resource | `/products` | `products.*` | **Admin** | CRUD Productos |
| PUT | `/products/{product}/favorite` | `products.toggle-favorite` | **Admin** | Toggle favorito |
| Resource | `/users` | `users.*` | **Admin** | CRUD Usuarios |
| GET/POST | `/configuracion` | `settings.*` | **Admin** | Configuración |

---

## 5. Reglas de Negocio Críticas (Confirmadas en Código)

### Stock
- Nunca se toca al crear o confirmar pedido.
- Se **decrementa** solo al pasar a `'enviado'` (`SaleController@updateStage`).
- Si pasa de `'enviado'` o `'entregado'` a `'cancelado'`, el stock **regresa**.
- Si `allow_negative_stock = '1'` en settings, permite enviar aunque stock < cantidad.

### Pagos
- Al crear venta: `paid_amount` = anticipo capturado en el POS.
- Si `paid_amount > 0`, el sistema auto-avanza de `'pedido'` a `'confirmado'`.
- Abonos posteriores: se registran en `sale_payments` y se acumulan en `sale.paid_amount`.
- Validación: `monto_abono <= (sale.total - sale.paid_amount)` — bloqueado si excede.
- Todo en `DB::transaction` — atómico.

### Roles
- `'admin'`: acceso total, incluyendo productos, usuarios, configuración y plan de producción.
- `'vendedor'`: POS, Kanban (solo sus ventas), clientes (sin borrar).
- El Dashboard filtra automáticamente por rol.

### Auto-confirmación al crear pedido
- En `SaleController@store`: si `paid_amount > 0` → `stage = 'confirmado'` automáticamente.
- Esto NO estaba documentado anteriormente.

### PDFs en Hosting Compartido
- Variable `FILESYSTEM_PUBLIC_ROOT` en `.env` define la ruta física absoluta.
- Si no existe o el archivo no está ahí, fallback a `public_path('storage/...')`.
- Solo necesaria en producción (Neubox). En local no se configura.

---

## 6. Componentes Vue — Estado Real

| Componente | Ruta | Estado | Notas |
|-----------|------|--------|-------|
| `Dashboard.vue` | `/dashboard` | ✅ Completo | KPIs, filtros fecha, stock crítico, rendimiento vendedores |
| `Sales/Create.vue` | `/pos` | ✅ Completo | POS con firma digital, colores por material, modal cliente |
| `Sales/Index.vue` | `/sales` | ✅ Completo | Kanban + modal detalle inline + historial colapsable |
| `Sales/Show.vue` | `/sales/{id}` | ✅ Completo | Modo Oficina/Taller, historial pagos, modal abono |
| `Production/Index.vue` | `/production-plan` | ✅ Completo | Explosión de insumos, desglose por color, CSS print |
| `Products/Create.vue` | `/products/create` | ✅ Completo | Variantes dinámicas, materiales por categoría, imagen, favorito |
| `Products/Edit.vue` | `/products/{id}/edit` | ✅ Completo | Upsert variantes, lightbox imagen, modal animado |
| `Products/Index.vue` | `/products` | ✅ Completo | Paginación local, toggle favorito, stock coloreado |
| `Clients/Create.vue` | `/clients/create` | ✅ Completo | Formulario dirección completa, tier de precio |
| `Clients/Edit.vue` | `/clients/{id}/edit` | ✅ Completo | Idéntico a Create pero con datos precargados |
| `Clients/Index.vue` | `/clients` | ✅ Completo | Búsqueda throttle, paginación servidor, borrado protegido |
| `Users/Create.vue` | `/users/create` | ✅ Completo | Nombre, email, rol, contraseña |
| `Users/Index.vue` | `/users` | ✅ Completo | Lista con badge de rol, borrado con SweetAlert2 |
| `Users/Edit.vue` | — | ❌ NO EXISTE | No se puede editar usuario existente |
| `Settings/Index.vue` | `/configuracion` | ✅ Completo | Logo con preview, toggle stock negativo, correos notif |

---

## 7. Componentes Reutilizables Detectados

```
resources/js/Components/
├── ClientAutocomplete.vue   ← Buscador de clientes en el POS
├── Modal.vue                ← Modal genérico usado en Sales y POS
└── AuthenticatedLayout.vue  ← Layout principal con sidebar/nav
```

> `ClientAutocomplete.vue` es un componente propio — no documentado anteriormente. Se usa en `Sales/Create.vue` para buscar clientes con autocompletado.

---

## 8. Paquetes Instalados (Confirmados en Código)

```json
PHP (composer.json):
  "barryvdh/laravel-dompdf"   → PDFs
  "laravel-lang/common"        → Traducciones español

JS (package.json):
  "vue-signature-pad"          → Firma digital en POS (NO documentado antes)
  "sweetalert2"                → Modales y confirmaciones
  "lodash"                     → debounce (Sales/Index) y throttle (Clients/Index)
```

---

## 9. Variables de Entorno

```ini
# Estándar
APP_ENV=local|production
APP_DEBUG=true|false
APP_URL=http://localhost|https://tudominio.com
DB_CONNECTION=mysql
DB_DATABASE=taller360

# Específica del proyecto
FILESYSTEM_PUBLIC_ROOT=/ruta/fisica/al/storage
# Solo en producción (Neubox). En local NO se configura.
```

---

## 10. Piezas de Arquitectura Confirmadas (Ronda 2 de Auditoría)

### `SaleObserver.php` — Historial automático
**Esto es clave y no era obvio desde los controladores.** El historial de `sale_histories` NO se escribe manualmente en `SaleController`. Existe un Observer registrado en `AppServiceProvider::boot()` que escucha el modelo `Sale`:
- Al **crear** una venta → registra automáticamente `"Pedido creado"` con `to_stage` = stage inicial.
- Al **actualizar** una venta, si el campo `stage` cambió (`isDirty('stage')`) → registra `from_stage`, `to_stage` y la nota `"Cambio de estado automático"`.

> Implicación práctica: si en el futuro se necesita una nota personalizada en el historial (ej. "Autorizado por gerencia"), hay que modificar el Observer, no el controlador.

### `CheckRole.php` — Middleware de roles
Lógica simple: compara `$request->user()->role !== $role` y aborta con 403 si no coincide. Se usa en rutas como `Route::middleware('role:admin')`.

### `HandleInertiaRequests.php` — Props globales
Comparte `auth.user` a **todas** las vistas Vue automáticamente. Esto significa que en cualquier componente `.vue` se puede acceder al usuario logueado con `$page.props.auth.user` sin que el controlador lo pase explícitamente como prop.

### `AppServiceProvider.php` — Configuración global
- Registra el `SaleObserver` (`Sale::observe(SaleObserver::class)`).
- Fuerza HTTPS solo si `app()->environment('production')`.
- Configura `Carbon::setLocale('es')` → por eso todas las fechas del sistema salen en español.
- `Vite::prefetch(concurrency: 3)` → optimización de carga de assets.

### `SaleNoteEmail.php` — Mailable
- Adjunta el PDF **en memoria** (no se guarda en disco, se genera y adjunta directo).
- Usa vista Blade `emails.sale_note` para el cuerpo del correo.
- Asunto: `"Nota de Venta #000123"` con folio con padding de 6 dígitos.

---

## 11. Piezas NO Auditadas (Pendiente si se necesita en el futuro)

Estas piezas existen en el proyecto pero no fueron revisadas a fondo porque no son críticas para el desarrollo de features día a día:
- `database/seeders/` — Lógica de importación masiva desde CSV
- `resources/views/pdf/ticket.blade.php` y `pdf/sale_note.blade.php` — Plantillas de los PDFs
- `resources/views/emails/sale_note.blade.php` — Plantilla del correo
- `resources/js/Layouts/AuthenticatedLayout.vue` — Sidebar y navegación principal
- `routes/auth.php` — Rutas de autenticación (probablemente scaffolding estándar de Breeze)

Si en algún momento necesitas modificar el diseño del PDF, el correo, o el sidebar, comparte estos archivos en una nueva sesión.

---

## 12. Cómo Compartir Contexto con IA al Retomar

Cuando abras una nueva sesión con Claude u otra IA, comparte:
1. Este archivo (`CONTEXTO_TECNICO.md`)
2. El archivo específico que vas a modificar
3. Una descripción de qué quieres hacer

Ejemplo:
> "Voy a agregar edición de usuarios. Te comparto el contexto:"
> [pegar CONTEXTO_TECNICO.md]
> "Y los archivos a modificar:"
> [pegar Users/Index.vue + UserController.php]