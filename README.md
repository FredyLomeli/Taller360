# 🛋️ TALLER 360 - Sistema POS Mueblería (v2.0)

![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![Vue 3](https://img.shields.io/badge/Vue.js-3-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)
![Inertia](https://img.shields.io/badge/Inertia-Purple?style=for-the-badge&logo=inertia&logoColor=white)
![Status](https://img.shields.io/badge/Status-Producción-blue?style=for-the-badge)

Sistema de Gestión de Pedidos y Manufactura robusto, diseñado específicamente para mueblerías que fabrican sobre pedido y requieren control detallado de especificaciones (Colores, Materiales, Notas de Taller).

## 🚀 ESTATUS DEL PROYECTO
**Versión:** 2.0 (Order & Manufacture)
**Última Actualización:** 09 Febrero 2026
**Fase Actual:** Operatividad Completa (Backend + POS).

---

## ✅ Módulos Terminados y Funcionales

### 🏭 Order Builder (Nuevo POS v2.0 🌟)
Ya no es solo un POS, es un **Constructor de Pedidos** inteligente:
* **Flujo de Manufactura:** Los pedidos nacen como "Cotización/Pedido" y no descuentan stock hasta que se envían.
* **Personalización Visual:**
    * Selector de **Colores Visuales (Bolitas)** dinámicos según el material (MDF, Madera, Melamina).
    * Notas de Taller ilimitadas ("Cortar patas 5cm", "Vidrio esmerilado").
    * Costos Adicionales por ítem (suman al total automáticamente).
* **Precios Dinámicos:** Validación que oculta precios hasta seleccionar un cliente y aplica su tarifa específica (Tier 1-5).
* **Validaciones Financieras:**
    * Control estricto de descuentos (Tope 50%).
    * Cálculo de Anticipos y Saldos Pendientes en tiempo real.
    * Bloqueo de cantidades negativas o errores de captura.

### 📦 Gestión de Inventario (Variantes Simplificadas)
* **Arquitectura Padre-Hijo:** Un producto (ej. "Ropero") tiene variantes por **Material**.
* **Precios Multi-Nivel:** 5 listas de precios por variante (Público, Mayoreo, Distribuidor).
* **Integridad:** Protección "Cascade" y validación que impide borrar productos si tienen historial de ventas.

### 🛡️ Seguridad y Roles
* **Middlewares Estrictos:** Separación total entre Admin y Vendedor.
* **Vendedores:** Acceso exclusivo a POS y su historial. Bloqueo de rutas de configuración, usuarios y reportes globales.
* **Anti-Intrusos:** Registro público desactivado. Solo el Admin crea usuarios desde el panel interno.

### 📊 Dashboard Inteligente
Panel de control dinámico con lógica de negocio diferenciada por roles:
* **Modo Admin:** KPIs Financieros, Filtro de Fechas, Ranking de Vendedores y Alertas de Stock.
* **Modo Vendedor:** Vista simplificada. Solo ve sus propios ingresos y su historial reciente.

---

## 📝 LISTA DE PENDIENTES (Roadmap v2.1)

### 🔴 Prioridad Inmediata
* **📋 Tablero de Pedidos (Kanban):** Actualizar la vista `Sales/Index` para gestionar los estados del pedido (Pendiente -> Producción -> Enviado -> Entregado).
* **📄 Hoja de Producción:** Formato de impresión especial para el taller (sin precios, solo medidas, colores y notas).
* **📦 Ajuste de Inventario:** Módulo para registrar entradas (compras) y salidas (mermas) manualmente.

### 🟡 Fase 2 (Administración)
* **Reportes Excel:** Exportación de ventas filtradas por fecha.
* **Facturación (CFDI 4.0):** Generación de XML para México.

---

## 🛠️ Instalación y Despliegue

### Requisitos
* PHP 8.2+
* Node.js & NPM
* MySQL / MariaDB

### Pasos de Instalación
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
    * Duplicar `.env.example` a `.env`.
    * Configurar base de datos.
    * Establecer `APP_LOCALE=es`.
4.  **Base de Datos y Datos de Prueba:**
    * Este comando crea tablas y **usuarios demo** (Admin + 3 Vendedores).
    ```bash
    php artisan migrate:fresh --seed
    ```
5.  **Compilar Frontend:**
    ```bash
    npm run dev
    ```

### Credenciales Demo (Seeder)
* **Admin:** `admin@admin.com` / `password`
* **Vendedor:** `vendedor1@tienda.com` / `password`

---

### Comandos de Mantenimiento
* **Correr Pruebas:** `php artisan test`
* **Limpiar Caché:** `php artisan optimize:clear`
* **Link de Imágenes:** `php artisan storage:link`