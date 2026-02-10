# 📋 BACKLOG MAESTRO - TALLER 360 (v2.1)
**Estado:** En Desarrollo Activo
**Fecha de Actualización:** 10 Febrero 2026 (Post-Sprint POS)

---

## ✅ 1. COMPLETADO HOY (Sprint V2.0)
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

---

## 🔥 2. PRIORIDAD INMEDIATA (Lo que sigue)
*Funciones críticas rotas o faltantes para operar el flujo completo.*

### 1. 📋 Tablero de Pedidos (`Sales/Index.vue`) - **[SIGUIENTE PASO]**
* **Estado Actual:** Muestra una tabla vieja que no sirve para el flujo de manufactura.
* **Requerimiento:**
    * Crear pestañas (Tabs): `Pendientes` | `Producción` | `Enviados`.
    * Mostrar detalles clave en la lista (Color, Notas).
    * **Botón de Acción:** Permitir cambiar el estado (`stage`) para mover el pedido por el flujo.

### 2. 📦 Formulario de Productos (`Products/Create.vue` y `Edit`)
* **Estado Actual:** ⚠️ **ROTO**. El formulario viejo sigue pidiendo "Color" en las variantes. Si intentas crear un producto, tronará.
* **Requerimiento:**
    * Eliminar campo "Color" del formulario.
    * Agregar Checkbox "Favorito".
    * Asegurar que guarde solo Material y Precios.

### 3. 📄 Detalle del Pedido (`Sales/Show.vue`)
* **Requerimiento:** Visualizar la "Hoja de Pedido" completa con los nuevos campos (Notas, Colores) para que el almacén sepa qué surtir.

---

## 🐛 3. BUGS DE PRODUCCIÓN (Deuda Técnica)
*Errores visuales o funcionales pendientes de corregir.*

1.  **🔄 Logout "Inception":** Al cerrar sesión, la Landing Page carga dentro del modal. (Fix: Usar `window.location` en lugar de Inertia link).
2.  **📄 PDF Sin Imágenes:** Los reportes PDF muestran "X" roja en imágenes. (Fix: Inyectar ruta absoluta del servidor).
3.  **💵 Formato Moneda (Global):** Asegurar que todos los inputs usen `lang="en-US"` o máscara de dinero para evitar problemas con comas/puntos.

---

## 🚀 4. FUTURO CERCANO (Fase 2.5)

1.  **🏭 Dashboard de Producción:** Vista Kanban (Tarjetas arrastrables) para el taller.
2.  **📦 Ajuste de Inventario:** Módulo para registrar entradas (compras) manuales.
3.  **📊 Reportes Excel:** Exportación de ventas.