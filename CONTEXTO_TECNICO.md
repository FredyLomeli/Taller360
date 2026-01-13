# 🧠 CONTEXTO TÉCNICO - TALLER 360 (POS SYSTEM)
**Fecha de actualización:** 14 Enero 2026
**Repositorio:** https://github.com/FredyLomeli/Taller360

## 1. 🛠 Stack Tecnológico
* **Backend:** Laravel 12 (PHP 8.2+)
* **Frontend:** Vue 3 (Composition API `<script setup>`)
* **Adaptador:** Inertia.js (SPA Monolítico)
* **Estilos:** Tailwind CSS
* **Librerías Clave:**
    * `sweetalert2`: Alertas UI.
    * `lodash`: Función `debounce` para búsqueda.
    * `laravel-lang/common`: Idioma Español.

## 2. 🗄️ Esquema de Base de Datos (Schema)

### Tabla: `product_variants` (Inventario Real)
* **Relación:** `product_id` (Padre) -> `onDelete('cascade')`.
* **Identificadores:** `sku` (nullable), `material`, `color`.
* **Precios (Multi-nivel):**
    * `price_1` (decimal 10,2) - Precio Público.
    * `price_2` a `price_5` (decimal 10,2 nullable) - Mayoreo/Distribuidor.
* **Inventario:** `stock` (integer, default 0).

### Tabla: `sales` (Cabecera de Venta)
* **Actores:** `user_id` (Vendedor), `client_id` (Cliente, nullable).
* **Financiero:**
    * `total` (decimal).
    * `paid_amount` (Cuánto pagó, útil para cambio/abonos).
    * `change_amount` (Cambio entregado).
* **Estado:**
    * `payment_method`: 'Efectivo', 'Tarjeta', 'Transferencia' (default 'Efectivo').
    * `status`: 'pagado', 'pendiente', 'cancelado' (default 'pagado').

### Tabla: `sale_details` (Detalle de Venta)
* **Relación:** `sale_id`, `product_variant_id`.
* **Snapshot (Histórico):**
    * `product_name`: Se guarda texto fijo por si se borra el catálogo.
    * `quantity`: Cantidad vendida.
    * `unit_price`: Precio al momento de la venta.
    * `subtotal`: (quantity * unit_price).
    * `discount_percent`: % de descuento aplicado (default 0).

### Tabla: `clients`
* `name`, `business_name`, `price_tier` (int 1-5), `phones`.

## 3. 🚦 Reglas de Negocio (Backend)

1.  **Guardia de Eliminación (Integridad Referencial):**
    * En `ProductController` y `ClientController`.
    * **Regla:** Antes de borrar, verificar existencia en `sale_details` o `sales`.
    * **Acción:** Si existe historial, retornar error `back()->withErrors(...)`. NO BORRAR.

2.  **Buscador Historial (SaleController):**
    * Filtra dinámicamente por `id` (Folio) O `client.name`.
    * Paginación activa (`paginate(10)`).

## 4. 🖥️ Lógica de Interfaz (Frontend)

1.  **Sanitización de Inputs (Precios/Stock):**
    * **Ubicación:** `Products/Create.vue` y `Edit.vue`.
    * **Lógica:** Al evento `@blur` y `submit`, cualquier valor vacío/inválido en precios o stock se convierte automáticamente a `0`. Evita error SQL `General error: 1366 Incorrect decimal value`.

2.  **Buscador Asíncrono (AJAX):**
    * **Ubicación:** `Sales/Index.vue`.
    * **Lógica:** Uso de `watch` + `lodash/debounce` (500ms) para filtrar sin recargar página.

3.  **Visualización de Colores:**
    * `ProductCard.vue` mapea nombres de color ("Chocolate") a Hex (`#5D4037`).

## 5. 📍 Hoja de Ruta (Pendientes)

1.  **Optimización Buscadores POS:** Migrar carga de Productos/Clientes de `all()` a búsqueda por servidor (AJAX) para evitar lentitud con muchos registros.
2.  **Selector Paginación:** UI para elegir "Ver 50 registros".
3.  **Dashboard:** Implementar KPIs.