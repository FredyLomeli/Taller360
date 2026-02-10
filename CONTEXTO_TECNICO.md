# 🧠 CONTEXTO TÉCNICO - TALLER 360 (POS SYSTEM)
**Versión:** 2.1 (POS & Order Builder Completado)
**Fecha de actualización:** 10 Febrero 2026
**Repositorio:** `https://github.com/FredyLomeli/Taller360`

## 1. 🛠 Stack Tecnológico
* **Backend:** Laravel 12 (PHP 8.2+).
* **Frontend:** Vue 3 (Composition API `<script setup>`) + Inertia.js.
* **Estilos:** Tailwind CSS.
* **Base de Datos:** MySQL / MariaDB (InnoDB).
* **Testing:** Pest PHP.
* **Librerías:** `dompdf`, `sweetalert2`, `lodash`, `laravel-lang`.

---

## 2. 🗄️ Estructura de Base de Datos (Schema Exacto)

Esta referencia es la **única** autorizada para consultas SQL/Eloquent.

### 👤 Usuarios y Clientes
* **`users`**: `id`, `name`, `email` (unique), `password`, `role` (default 'vendedor'), `email_verified_at`, timestamps.
* **`clients`**:
    * `id`, `name` (Nombre corto), `business_name` (Razón Social).
    * `price_tier` (int 1-5).
    * **Contacto:** `email` (unique), `phones` (string plural: "33123, 33456").
    * **Dirección:** `street_address`, `neighborhood`, `city`, `state`, `delegation`, `zip_code`.
    * `references` (text).

### 📦 Inventario
* **`categories`**: `id`, `name`, timestamps.
* **`products`**:
    * `id`, `category_id` (FK), `name`, `description`.
    * `measurements` (string), `image` (string).
    * **`is_favorite`** (boolean) -> *Para monitoreo rápido en Dashboard*.
* **`product_variants`**:
    * `id`, `product_id` (FK -> cascade).
    * **`material`** (string), `sku` (string, nullable).
    * `stock` (int). **Nota:** El `color` se eliminó de aquí (ahora es atributo de venta).
    * `price_1` (Público - Obligatorio).
    * `price_2` a `price_5` (Mayoreo - Opcionales).

### 💰 Ventas y Pedidos (Core)
* **`sales`**:
    * `id`, `user_id` (FK), `client_id` (FK - nullable).
    * `total` (decimal), `paid_amount` (decimal), `change_amount` (decimal).
    * `payment_method` (string).
    * **`stage`** (enum): `'pedido'`, `'confirmado'`, `'produccion'`, `'enviado'`, `'entregado'`, `'cancelado'`.
    * `promised_date` (date), `is_partial_shipping` (bool).
* **`sale_details`**:
    * `id`, `sale_id` (FK), `product_variant_id` (FK).
    * `product_name` (Snapshot), `quantity` (int).
    * **`chosen_color`** (string) -> *Elegido libremente al vender*.
    * **`custom_notes`** (text), **`additional_cost`** (decimal).
    * `unit_price`, `subtotal`, `discount_percent`.
* **`sale_histories`**:
    * `id`, `sale_id` (FK), `user_id` (FK).
    * `from_stage`, `to_stage`, `notes`.

### ⚙️ Configuración
* **`settings`**: `key` (string, unique), `value` (text).
    * Keys actuales: `'allow_negative_stock'`, `'company_name'`, `'company_logo'`.

---

## 3. 🚦 Reglas de Negocio Blindadas (Backend v2.0)

### 🛡️ Seguridad y Roles
1.  **Admin (`role: admin`):** Acceso total.
2.  **Vendedor (`role: vendedor`):**
    * Acceso a POS (Nuevo Pedido), Clientes y Historial personal.
    * Restricción de Dashboard global y configuración.

### 💰 Lógica de Pedidos (OrderWorkflow)
1.  **Estados (`stage`):**
    * `pedido`: Borrador/Cotización. **NO descuenta stock**.
    * `confirmado`: Se ha dado anticipo. Pasa a cola de producción.
    * `enviado`: Salió del taller. **AQUÍ se descuenta el stock** (o se valida existencia).
2.  **Precios Dinámicos:**
    * Se selecciona automáticamente el precio (1-5) según el `price_tier` del cliente.
    * Fórmula Total: `(PrecioVariante * Cantidad) + CostoAdicional`.
    * **Validación Financiera:** No se permiten costos extra negativos ni descuentos mayores al 50%.

---

## 4. 🧪 Estrategia de Testing
* **Comando:** `php artisan test`
* **Nuevos Objetivos QA:**
    * Validar que la creación de pedido (`store`) guarde colores y notas.
    * Validar que `updateStage` a 'enviado' reste stock correctamente.
    * Validar que `promised_date` sea opcional.

---

## 5. 🖥️ Lógica de Frontend (Actualizada)
* **POS / Order Builder (`Sales/Create.vue`):** ✅ **COMPLETADO**
    * **Catálogo Visual:** Selección de colores mediante "bolitas" dinámicas según material (MDF, Madera, Melamina) definidas en JS local.
    * **Ticket Compacto:** Diseño optimizado para ver más ítems, con botón para editar "Notas/Extras" en un modal.
    * **Modal Checkout V1:** Restaurado diseño visual (encabezado verde, iconos grandes) pero con lógica V2 (Anticipo + Fecha Entrega Opcional).
    * **Validaciones:** Bloqueo de cantidades negativas y costos extra negativos.

---

## 6. 📍 Hoja de Ruta (Backlog Priorizado)

### 🚀 FASE ACTUAL: Gestión de Pedidos (Kanban)

#### 1. 📋 Tablero de Control (`Sales/Index.vue`) - **[EN PROGRESO]**
* Transformar la tabla simple en un gestor de estados.
* **Tabs:** Filtrar por `Todos`, `Pendientes`, `Producción`, `Enviados`.
* **Acciones:** Botón para avanzar etapa ("Pasar a Producción", "Enviar").
* **Visual:** Mostrar Semaforización de estatus y resumen de notas/colores en la tabla.

#### 2. 📄 Detalles del Pedido (`Sales/Show.vue`)
* Mostrar desglose completo incluyendo `chosen_color`, `notes` y `promised_date`.
* Botón para imprimir **Ticket** y **Nota de Venta**.
* **Nueva Vista:** "Hoja de Producción" (Impresión limpia para taller sin precios).

#### 3. 📦 Ajustes de Inventario
* Módulo para registrar entradas (compras) y salidas (mermas) manualmente.

### 🟡 Fase 2 (Administración)
* **Reportes Excel:** Exportación de ventas filtradas por fecha.
* **Facturación (CFDI 4.0):** Generación de XML para México.