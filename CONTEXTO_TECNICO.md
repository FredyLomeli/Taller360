# 🧠 CONTEXTO TÉCNICO - TALLER 360 (POS SYSTEM)
**Versión:** 1.0 (Full Context)
**Fecha de actualización:** 17 Enero 2026
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
* **`users`**: `id`, `name`, `email` (unique), `password`, `role` ('admin'|'vendedor'), `email_verified_at`, timestamps.
* **`clients`**: `id`, `name`, `email` (unique), `phone`, `address`, `price_tier` (int 1-5), timestamps.

### 📦 Inventario
* **`categories`**: `id`, `name`, timestamps.
* **`products`**: `id`, `category_id` (FK), `name`, `description`, `image_url`, timestamps.
* **`product_variants`**:
    * `id`, `product_id` (FK -> cascade).
    * `sku` (string, nullable), `material` (string), `color` (string).
    * `stock` (int).
    * `price_1` (decimal 10,2 - Público).
    * `price_2`, `price_3`, `price_4`, `price_5` (decimal 10,2 - Mayoreo).

### 💰 Ventas
* **`sales`**:
    * `id`, `user_id` (FK), `client_id` (FK).
    * `total` (decimal 10,2).
    * `paid_amount` (decimal 10,2) -> *Lo que realmente entró a caja*.
    * `change_amount` (decimal 10,2).
    * `payment_method` (string: 'Efectivo', 'Tarjeta', etc).
    * `status` (enum: 'pagado', 'pendiente', 'cancelado').
    * `created_at` (Usado para filtros de fecha).
* **`sale_details`**:
    * `id`, `sale_id` (FK).
    * `product_variant_id` (FK).
    * `product_name` (string Snapshot), `quantity` (int).
    * `unit_price` (decimal 10,2), `subtotal` (decimal 10,2).
    * `discount_percent` (int).

### ⚙️ Configuración
* **`settings`**: `key` (string, unique), `value` (text).
    * Keys actuales: `'allow_negative_stock'`, `'company_name'`, `'company_logo'`.

---

## 3. 🚦 Reglas de Negocio Blindadas (Backend)

### 🛡️ Seguridad y Roles
1.  **Admin (`role: admin`):** Acceso total. Middleware `IsAdmin`.
2.  **Vendedor (`role: vendedor`):**
    * Solo accede a POS (`/pos`), Clientes y su Historial (`/sales`).
    * **Restricción Dura:** El Dashboard filtra datos sensibles (`sellersStats`, `income` global) a `null` si el rol no es Admin.

### 💰 Flujo de Venta (SalesController)
1.  **Stock Negativo:** Antes de guardar, se verifica `Setting::get('allow_negative_stock')`. Si es `0` y falta stock, lanza Excepción.
2.  **Transacciones:** `Sale` + `SaleDetail` + `Decremento de Stock` ocurren en una transacción atómica.
3.  **Upsert:** Al editar productos, variantes omitidas se borran (soft-logic: solo si no tienen ventas, de lo contrario error de integridad).

---

## 4. 🧪 Estrategia de Testing (Suite de 32 Pruebas)
* **Comando:** `php artisan test`
* **Cobertura Actual:**
    * ✅ **Auth:** Roles y redirecciones.
    * ✅ **Sales:** Cálculo de totales, stock y cambio.
    * ✅ **Dashboard:** Visibilidad de datos por rol y filtros de fecha.
    * ✅ **Settings:** Cambio de configuración impacta lógica de venta.

---

## 5. 🖥️ Lógica de Frontend (Performance)
* **Carga Inicial:** Productos y Clientes se cargan completos en el POS. Filtrado local (JS).
* **Búsqueda AJAX:** Historial de ventas usa `lodash/debounce` para buscar en servidor.
* **Formato:** Inputs numéricos usan `lang="en"` para evitar errores de decimales.

---

## 6. 📍 Hoja de Ruta (Backlog Priorizado)

### FASE 1: Operatividad Crítica (Próximos Pasos)
1.  **🖨️ Impresión Térmica (Ticket 80mm):**
    * Conectar vista HTML existente al botón de impresión en `Sales/Index.vue` y al finalizar venta.
2.  **📦 Ajuste de Inventario:**
    * Crear módulo "Movimientos" para Entradas (Compras) y Salidas (Mermas) manuales con motivo obligatorio.
3.  **💰 Corte de Caja:**
    * Comparar `Suma(paid_amount)` vs `Efectivo Reportado` por usuario.

### FASE 2: Administración
4.  **📈 Reportes Excel:** Exportación basada en filtros del Dashboard.
5.  **💳 Facturación (CFDI 4.0):** Generación de XML para México.