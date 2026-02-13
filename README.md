# 🛋️ TALLER 360 - Sistema POS Mueblería (v2.5)

![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![Vue 3](https://img.shields.io/badge/Vue.js-3-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)
![Inertia](https://img.shields.io/badge/Inertia-Purple?style=for-the-badge&logo=inertia&logoColor=white)
![Status](https://img.shields.io/badge/Status-Listo_para_Despliegue-success?style=for-the-badge)

Sistema de Gestión de Pedidos y Manufactura robusto, diseñado específicamente para mueblerías que fabrican sobre pedido. Integra control de producción, monitoreo financiero en tiempo real y gestión inteligente de inventarios.

## 🚀 ESTATUS DEL PROYECTO
**Versión:** 2.5 (Ciclo Financiero & Producción Completo)
**Última Actualización:** 13 Febrero 2026
**Fase Actual:** Pre-Producción (Listo para Hosting Compartido / Neubox).

---

## ✅ Módulos Terminados y Funcionales

### 🏭 Plan Maestro de Producción (NUEVO v2.5 🌟)
Módulo estratégico para el Jefe de Taller que elimina la necesidad de revisar pedido por pedido:
* **Agrupación Inteligente:** Consolida todos los pedidos en estatus "Producción" agrupándolos por **Tipo de Mueble + Material**.
* **Explosión de Insumos:** Muestra el total exacto a fabricar (ej. "10 Roperos Chocolate, 2 Blancos") en una sola tarjeta visual.
* **Interfaz Limpia:** Vista optimizada para impresión o tablet, sin datos financieros, enfocada 100% en manufactura.

### 💰 Gestión de Abonos y Liquidación (NUEVO v2.5 🌟)
Cierre del ciclo financiero post-venta:
* **Bitácora de Pagos:** Registro de abonos parciales (Efectivo, Transferencia, etc.) posteriores al anticipo.
* **Validación Financiera:** El sistema impide registrar abonos mayores a la deuda pendiente.
* **Historial:** Tabla detallada de fecha, método y referencia de cada pago recibido en el detalle de la venta.

### 📄 Detalle de Venta Híbrido (NUEVO v2.5 🌟)
Visualizador de pedidos con "Switch de Contexto" (`Sales/Show.vue`):
* **Modo Oficina:** Muestra precios, totales, saldos pendientes y botones de cobranza.
* **Modo Taller:** Oculta toda la información financiera y transforma la pantalla en una **Orden de Trabajo** con notas técnicas, colores y materiales.

### 🖨️ Motor de Impresión & Hosting (OPTIMIZADO)
* **PDFs Blindados:** Generación de Notas de Venta y Tickets compatibles con Hostings Compartidos (cPanel/Neubox).
* **Manejo de Imágenes:** Lógica personalizada para leer logotipos desde rutas físicas (`FILESYSTEM_PUBLIC_ROOT`) evitando bloqueos HTTP.
* **Seguridad:** Forzado de esquema HTTPS en producción (`AppServiceProvider`).

### 📊 Dashboard Estratégico
Panel de control "One-Page" diseñado para monitoreo sin scroll, adaptado al rol del usuario:
* **KPIs Financieros y Operativos:** Visualización inmediata de Ingreso Cobrado, Pedidos en Taller, Listos para Entrega y Crédito por Cobrar.
* **Filtros Temporales:** Selector de rangos de fecha que recalcula métricas al instante.
* **Alertas de Stock Inteligentes:** Monitoreo automático de variantes con stock bajo (< 5 piezas) solo en productos "Favoritos".
* **Tabla de Rendimiento:** Leaderboard de vendedores con avatares dinámicos.

### 🏭 Order Builder (POS v2.0)
Constructor de Pedidos inteligente orientado a manufactura:
* **Personalización Visual:** Selector de colores dinámicos y notas de taller ilimitadas por partida.
* **Precios Dinámicos:** Aplicación automática de tarifas (Tier 1-5) según el cliente seleccionado.
* **Validaciones:** Control de descuentos, anticipos y saldos pendientes.

### 📋 Tablero de Producción (Kanban)
Gestión del ciclo de vida del mueble con lógica de inventario estricta:
* **Flujo:** Cotización -> Confirmado -> Producción -> Enviado -> Entregado -> Cancelado.
* **Gestión de Stock:**
    * Descuento automático al pasar a **"Enviado"**.
    * Retorno automático de stock si se **"Cancela"** un pedido enviado.
* **Timeline:** Auditoría completa de quién movió el pedido y cuándo.

### 📦 Gestión de Inventario & Datos
* **Arquitectura:** Producto (Padre) -> Variantes (Material: MDF, Melamina).
* **Importación Masiva (Seeders Blindados):**
    * Generación de emails únicos para clientes legacy.
    * Limpieza de precios (`$2,500.00` -> `2500.00`) y asignación automática de variantes.
    * Mapeo automático de listas de precios (A, B, C -> 1, 2, 3).

### 🛡️ Seguridad y Roles
* **Middlewares Estrictos:** Separación total entre Admin y Vendedor.
* **Vendedores:** Acceso exclusivo a POS y su historial personal.
* **Admin:** Acceso total a métricas globales, configuración y gestión de usuarios.

---

## 📝 LISTA DE PENDIENTES (Roadmap)

### 🟡 Fase 3 (Administración Avanzada)
* **📉 Reporte de Corte de Caja:** Exportación de ingresos diarios desglosados por método de pago.
* **📦 Ajuste de Inventario Manual:** Interfaz para registrar compras de material y mermas sin pasar por ventas.
* **✉️ Notificaciones Automáticas:** Envio de correo al cliente cuando su pedido pasa a "Enviado".
* **Facturación (CFDI 4.0):** Generación de XML para México.

---

## 🛠️ Instalación y Carga de Datos

### Requisitos
* PHP 8.2+
* Node.js & NPM
* MySQL / MariaDB

### A. Instalación Desarrollo Local
1.  **Clonar repositorio:**
    ```bash
    git clone [https://github.com/FredyLomeli/Taller360.git](https://github.com/FredyLomeli/Taller360.git)
    ```
2.  **Instalar dependencias:**
    ```bash
    composer install
    npm install
    ```
3.  **Configurar Entorno:**
    * Duplicar `.env.example` a `.env` y configurar BD.
4.  **Migración:**
    ```bash
    php artisan migrate:fresh --seed
    ```

### B. Configuración Producción (Hosting Compartido / Neubox)
En el archivo `.env` del servidor:
```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=[https://tudominio.com](https://tudominio.com)
# Ruta física para PDFs (Evita error de imagen rota)
FILESYSTEM_PUBLIC_ROOT=/home/usuario/public_html/storage