# 🛋️ TALLER 360 - Sistema POS Mueblería

Sistema de Punto de Venta y Administración desarrollado en **Laravel 12 + Vue 3 (Inertia.js)**.
Enfocado en la gestión de inventarios con variantes (color/material), control de ventas y administración de clientes.

---

## 🚀 ESTATUS DEL PROYECTO
**Última Actualización:** 14 Enero 2024
**Fase Actual:** Pulido de UX y Estabilidad.

### ✅ Módulos Terminados y Funcionales
1.  **Gestión de Productos (Inventario):**
    * Alta/Edición con variantes (Color, Material, SKU, Stock).
    * **Blindaje SQL:** Validación automática de campos vacíos (se convierten a 0).
    * **Visual:** Bolitas de colores reales en el listado.
    * **Seguridad:** "Guardia" en Backend que impide borrar productos si ya tienen ventas históricas.
2.  **Punto de Venta (POS):**
    * Carrito de compras reactivo.
    * Buscador de productos y clientes.
    * **Cliente Rápido:** Modal para registrar clientes nuevos sin salir de la venta (con dirección y nivel de precio).
    * **Pagos:** Modal multiformato (Efectivo, Tarjeta, Transferencia, Crédito).
    * Manejo de Stock (resta al vender, suma al cancelar).
3.  **Gestión de Clientes:**
    * CRUD completo con validaciones.
    * Bloqueo de eliminación si el cliente tiene deudas o historial.
4.  **Historial de Ventas:**
    * Listado paginado.
    * **Buscador Avanzado:** Filtrado por servidor (Folio o Nombre Cliente).
    * Cancelación de ventas (con devolución de stock automática).
5.  **Configuración:**
    * Carga de Logotipo y Datos de la Empresa (se reflejan en PDFs).
    * Configuración de correos de notificación.
6.  **UX / UI:**
    * Alertas **SweetAlert2** para confirmaciones de eliminación.
    * Mensajes de error y fechas en **Español** (Paquete de idioma instalado).

---

## 📝 LISTA DE PENDIENTES (Roadmap)

### 🔴 Prioridad Alta (Estabilidad y Rendimiento)
1.  **Optimización de Buscadores (Productos y Clientes):**
    * *Estado Actual:* Se cargan todos los registros (`all()`) en memoria al abrir el POS.
    * *Meta:* Migrar `ClientAutocomplete` y el buscador de productos a **Búsqueda AJAX** (Server-side) para soportar miles de registros sin lentitud.
2.  **Paginación Dinámica:**
    * Agregar selector "Mostrar 10, 20, 50, 100 registros" en todas las tablas.

### 🟡 Prioridad Media (Funcionalidad)
3.  **Dashboard (Pantalla de Inicio):**
    * Actualmente vacía. Agregar tarjetas de KPIs (Ventas del día, Productos bajos en stock).
4.  **Roles y Permisos:**
    * Separar rol `Administrador` (Acceso total) de `Vendedor` (Solo POS, sin acceso a borrar productos ni configuración).

### 🔵 Futuro / Deseables
5.  **Corte de Caja:** Funcionalidad para cierre de turno y arqueo de efectivo.
6.  **Cuentas por Cobrar:** Módulo para gestionar los créditos y abonos de clientes.

---

## 🛠️ Instalación y Configuración

### Requisitos Previos
* PHP 8.2+
* Node.js & NPM
* MySQL

### Pasos Rápidos
1.  Clonar repositorio: `git clone ...`
2.  Instalar dependencias PHP: `composer install`
3.  Instalar dependencias JS: `npm install`
4.  Configurar `.env` (Base de datos y `APP_LOCALE=es`).
5.  Migrar BD: `php artisan migrate`
6.  Compilar assets: `npm run dev`
7.  **Importante:** Ejecutar `php artisan storage:link` para ver las imágenes.

### Comandos Útiles
* **Limpiar caché:** `php artisan optimize:clear`
* **Publicar traducciones:** `php artisan lang:publish`