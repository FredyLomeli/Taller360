# 📋 BACKLOG MAESTRO - TALLER 360 (v2.2)
**Estado:** En Desarrollo Activo
**Fecha de Actualización:** 11 Febrero 2026 (Post-Sprint Sales Index)

---

## ✅ 1. COMPLETADO HOY (Sprint V2.2)
*Estas tareas ya fueron programadas, probadas y están en el código base.*

* **🛠️ Backend Core V2:**
    * [x] Migración de Base de Datos (Stages, Clientes full, Eliminación Color variante).
    * [x] Modelos Actualizados (`Sale`, `SaleDetail`, `ProductVariant`).
    * [x] Controladores Adaptados (`SaleController`, `ClientController`).
    * [x] Rutas Web limpias (`Route::resource`).
* **🛒 Nuevo POS (Order Builder):**
    * [x] Diseño visual de catálogo con categorías y favoritos.
    * [x] Lógica de **Colores Visuales** (Bolitas JS).
    * [x] Agrupación de items con Notas y Costos Extras.
    * [x] Validaciones "Duras" (No negativos, No descuentos > 50%).
    * [x] Modal de Checkout restaurado (Diseño V1 + Lógica V2).
* **📋 Tablero de Pedidos (`Sales/Index.vue`):**
    * [x] Sistema de Tabs reactivos (Pendientes, Producción, Enviados).
    * [x] Semaforización de estatus y resumen de detalles (Color/Notas) en tabla.
    * [x] Botones de acción para cambio de `stage` vía Inertia.

---

## 🔥 2. PRIORIDAD INMEDIATA (Lo que sigue)
*Funciones críticas rotas o faltantes para operar el flujo completo.*

### 1. 📄 Detalle del Pedido (`Sales/Show.vue`) - **[SIGUIENTE PASO]**
* **Requerimiento:** Visualizar la "Hoja de Pedido" completa con los nuevos campos (Notas, Colores) para que el almacén sepa qué surtir.
* **Extra:** Botón de impresión rápida para el taller.

### 2. 📦 Formulario de Productos (`Products/Create.vue` y `Edit`)
* **Estado Actual:** ⚠️ **ROTO**. El formulario viejo sigue pidiendo "Color" en las variantes.
* **Requerimiento:**
    * Eliminar campo "Color" del formulario.
    * Agregar Checkbox "Favorito".
    * Asegurar que guarde solo Material y Precios.

---

## 🐛 3. BUGS DE PRODUCCIÓN (Deuda Técnica)
*Errores visuales o funcionales pendientes de corregir.*

1.  **🔄 Logout "Inception":** Al cerrar sesión, la Landing Page carga dentro del modal. (Fix: Usar `window.location` en lugar de Inertia link).
2.  **📄 PDF Sin Imágenes:** Los reportes PDF muestran "X" roja en imágenes. (Fix: Inyectar ruta absoluta del servidor).
3.  **💵 Formato Moneda (Global):** Asegurar que todos los inputs usen `lang="en-US"` para evitar problemas con comas/puntos.

---

## 🚀 4. FUTURO CERCANO (Fase 2.5+)

1.  **🏭 Dashboard de Producción:** Vista Kanban (Tarjetas arrastrables) para el taller.
2.  **📦 Ajuste de Inventario Independiente:** Módulo para altas de stock y compras separado del flujo de producción.
3.  **🚚 Envíos Parciales:** Capacidad de despachar ítems de una misma venta en diferentes momentos.
4.  **📊 Reportes Excel:** Exportación de ventas y movimientos.