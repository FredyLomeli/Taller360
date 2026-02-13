# 🧠 CONTEXTO TÉCNICO - TALLER 360 (POS SYSTEM)
**Versión:** 2.5 (Ciclo Financiero & Producción Completo)
**Fecha de actualización:** 13 Febrero 2026
**Repositorio:** `https://github.com/FredyLomeli/Taller360`

## 1. 🛠 Stack Tecnológico
* **Backend:** Laravel 12 (PHP 8.2+).
* **Frontend:** Vue 3 (Composition API `<script setup>`) + Inertia.js.
* **Estilos:** Tailwind CSS.
* **Base de Datos:** MySQL / MariaDB (InnoDB).
* **Infraestructura:** Compatible con Hosting Compartido (cPanel/Neubox) mediante `FILESYSTEM_PUBLIC_ROOT`.
* **Librerías:** `barryvdh/laravel-dompdf`, `sweetalert2`, `lodash`, `laravel-lang`.

---

## 2. 🗄️ Estructura de Base de Datos (Schema Exacto)

Esta referencia es la **única** autorizada para consultas SQL/Eloquent.

### 👤 Usuarios y Clientes
* **`users`**: `id`, `name`, `email` (unique), `password`, `role` (enum: 'admin', 'vendedor'), `email_verified_at`, timestamps.
* **`clients`**:
    * `id`, `name` (Nombre corto), `business_name` (Razón Social).
    * `price_tier` (int 1-5) -> *Mapeado visualmente como Listas A, B, C, D, E*.
    * **Contacto:** `email` (unique - *Generado auto si no existe*), `phones` (string).
    * **Dirección:** `street_address`, `neighborhood`, `city`, `state`, `delegation`, `zip_code`.
    * `references` (text).

### 📦 Inventario & Producción
* **`categories`**: `id`, `name`, timestamps.
* **`products`**:
    * `id`, `category_id` (FK), `name` (Modelo), `description`.
    * `measurements` (string), `image` (string).
    * **`is_favorite`** (boolean) -> *Vital para el widget de Stock Bajo en Dashboard*.
* **`product_variants`**:
    * `id`, `product_id` (FK -> cascade).
    * **`material`** (string) -> *Ej: MDF, Melamina, Madera*.
    * `sku` (string, nullable).
    * `stock` (int) -> *Se descuenta estrictamente al pasar a etapa 'Enviado'*.
    * `price_1` (Público - Obligatorio).
    * `price_2` a `price_5` (Listas de Precios - Opcionales).

### 💰 Ventas y Pedidos (Core)
* **`sales`**:
    * `id`, `user_id` (FK), `client_id` (FK - nullable).
    * `total` (decimal), `paid_amount` (decimal - *Acumulado*), `change_amount` (decimal).
    * `payment_method` (string).
    * **`stage`** (enum): `'pedido'`, `'confirmado'`, `'produccion'`, `'enviado'`, `'entregado'`, `'cancelado'`.
    * `promised_date` (date), `is_partial_shipping` (bool).
* **`sale_details`**:
    * `id`, `sale_id` (FK), `product_variant_id` (FK).
    * `product_name` (Snapshot), `quantity` (int).
    * **`chosen_color`** (string) -> *Atributo dinámico de venta*.
    * **`custom_notes`** (text), **`additional_cost`** (decimal).
    * `unit_price`, `subtotal`, `discount_percent`.
* **`sale_histories`**:
    * `id`, `sale_id` (FK), `user_id` (FK).
    * `from_stage` (string), `to_stage` (string).
    * `notes` (text).
    * timestamps -> *Base para reportes de tiempos de producción*.

### 💳 Pagos y Cobranza (NUEVA TABLA v2.5)
* **`sale_payments`**:
    * `id`, `sale_id` (FK), `user_id` (FK).
    * `amount` (decimal), `payment_method` (string).
    * `reference` (string), `paid_at` (datetime).
    * *Propósito:* Bitácora detallada de abonos posteriores al anticipo.

---

## 3. 🚦 Reglas de Negocio Blindadas (Backend)

### 🛡️ Seguridad y Roles
1.  **Admin (`role: admin`):**
    * Dashboard Global (KPIs financieros, Stock, Rendimiento Vendedores).
    * Gestión total de Usuarios y Configuración.
    * Acceso al Plan Maestro de Producción.
2.  **Vendedor (`role: vendedor`):**
    * Dashboard Personal (Solo sus ventas y comisiones).
    * Acceso restringido a POS y Clientes.
    * No puede ver el Plan Maestro Global (opcional).

### 🏭 Lógica de Producción (ProductionController)
* **Agrupación Inteligente:** El sistema busca todos los `sale_details` donde `sale.stage === 'produccion'`.
* **Consolidación:** Agrupa los resultados por **Nombre de Producto + Material**.
* **Explosión de Insumos:** Suma las cantidades (`quantity`) de todos los pedidos agrupados para mostrar un solo total a fabricar (ej. "10 Roperos").

### 💰 Seguridad Financiera (SalePaymentController)
* **Validación de Deuda:** Al registrar un abono, el sistema calcula `sale.total - sale.paid_amount`. Si el abono intenta exceder esa deuda, la transacción se bloquea.
* **Transacción Atómica:** El registro en `sale_payments` y la actualización de `sale.paid_amount` ocurren dentro de una `DB::transaction`.

### 🌐 Compatibilidad Hosting Compartido
* **PDFs:** El `SaleController` detecta la variable de entorno `FILESYSTEM_PUBLIC_ROOT` para construir rutas físicas absolutas a las imágenes, evitando bloqueos HTTP.
* **HTTPS:** `AppServiceProvider` fuerza el esquema HTTPS en producción.

---

## 4. 🖥️ Lógica de Frontend (Actualizada)

### ✅ COMPLETADO
* **Dashboard Estratégico (`Dashboard.vue`):**
    * Diseño "One-Page" ajustado al viewport.
    * **KPIs:** Ingresos, Producción, Envíos, Créditos.
    * **Filtros:** Selector de fechas global.
    * **Tablas:** Rendimiento de vendedores y Stock Bajo.
* **POS / Order Builder (`Sales/Create.vue`):**
    * Catálogo visual con selectores de colores dinámicos.
    * Ticket con notas personalizadas y costos extra.
* **Tablero Kanban (`Sales/Index.vue`):**
    * Filtros por etapas.
    * Enlace al **Plan de Taller**.
    * Folio clicable para ver detalle completo.
* **Detalle de Venta Híbrido (`Sales/Show.vue`):** [NUEVO v2.5]
    * **Switch Modo Taller/Oficina:** Oculta precios y muestra especificaciones técnicas.
    * **Historial de Abonos:** Tabla de pagos recibidos.
    * **Botones de Acción:** Registrar Abono (Modal) e Imprimir.
* **Plan de Producción (`Production/Index.vue`):** [NUEVO v2.5]
    * Vista imprimible de "Explosión de Insumos" consolidada para el taller.

### ⚠️ PENDIENTE / REFACTOR
* **Formulario de Productos (`Products/Create.vue`):**
    * **Estado:** 🟡 **Funcional pero Básico**.
    * **Acción:** Falta integrar la carga de múltiples imágenes (Galería).

---

## 5. 📍 Hoja de Ruta (Backlog Priorizado)

### 🟡 Fase 3 (Administración Avanzada)
1.  **Reporte de Corte de Caja:** Exportación a Excel de ingresos diarios desglosados por método de pago.
2.  **Ajuste de Inventario Manual:** Interfaz para registrar compras de material (entradas) y mermas (salidas) sin pasar por ventas.
3.  **Notificaciones por Correo:** Envio automático al cliente cuando su pedido cambia a "Enviado".
4.  **Facturación (CFDI 4.0):** Generación de XML para México.