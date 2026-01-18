# 🛋️ TALLER 360 - Sistema POS Mueblería

![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![Vue 3](https://img.shields.io/badge/Vue.js-3-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)
![Inertia](https://img.shields.io/badge/Inertia-Purple?style=for-the-badge&logo=inertia&logoColor=white)
![Pest PHP](https://img.shields.io/badge/Tests-Passing-success?style=for-the-badge)

Sistema de Punto de Venta (POS) y Administración robusto, diseñado específicamente para mueblerías que gestionan inventarios complejos con variantes (Color, Material, Tela).

## 🚀 ESTATUS DEL PROYECTO
**Versión:** 1.0 (Release Candidate)
**Última Actualización:** 17 Enero 2026
**Fase Actual:** Operatividad y Pruebas de Campo.

---

## ✅ Módulos Terminados y Funcionales

### 📊 Dashboard Inteligente (Nuevo 🌟)
Panel de control dinámico con lógica de negocio diferenciada por roles:
* **Modo Admin:**
    * KPIs Financieros: Ingreso Real (Caja), Crédito por Cobrar y Tickets Totales.
    * **Máquina del Tiempo:** Filtro por rango de fechas para analizar ventas históricas.
    * Ranking de Vendedores y Alertas de Stock Crítico.
* **Modo Vendedor:**
    * Vista simplificada. Solo ve sus propios ingresos y su historial reciente.
    * Privacidad de Datos: El backend bloquea el envío de estadísticas globales a usuarios no admins.

### 🛒 Punto de Venta (POS)
* **Diseño App-Like:** Altura ajustada (`h-[calc(100vh-0px)]`) sin scrollbars dobles.
* **Control de Stock:**
    * Resta automática al vender.
    * **Configurable:** Permite o bloquea ventas con stock negativo según la configuración del sistema (`allow_negative_stock`).
* **Decimales:** Solución `lang="en"` para evitar errores de moneda en navegadores en español.
* **Cliente Rápido:** Modal para registrar clientes al vuelo con validaciones de unicidad.

### 📦 Gestión de Inventario (Variantes)
* **Arquitectura Padre-Hijo:** Un producto (ej. "Ropero") tiene múltiples variantes (ej. "MDF - Chocolate", "Madera - Caoba").
* **Precios Multi-Nivel:** 5 listas de precios por variante (Público, Mayoreo, Distribuidor).
* **Integridad:** Protección "Cascade" y validación que impide borrar productos si tienen historial de ventas.

### 🛡️ Seguridad y Roles
* **Middlewares Estrictos:** Separación total entre Admin y Vendedor.
* **Vendedores:** Acceso exclusivo a POS y su historial. Bloqueo de rutas de configuración, usuarios y reportes globales.
* **Anti-Intrusos:** Registro público desactivado. Solo el Admin crea usuarios desde el panel interno.

### 🧪 Calidad y Testing (QA)
Suite de **32 Pruebas Automatizadas (Feature Tests)** usando Pest PHP que validan:
* Seguridad de rutas (Auth).
* Cálculo exacto de totales y cambios.
* Integridad del inventario.
* Privacidad del Dashboard.

---

## 📝 LISTA DE PENDIENTES (Roadmap)

### 🔴 Prioridad Inmediata (Operatividad)
* **🖨️ Impresión Térmica:** Conectar la vista HTML (ya calibrada a 80mm/227pt) al botón de imprimir del POS.
* **📦 Ajuste de Inventario:** Módulo para registrar entradas (compras) y salidas (mermas/uso interno) manualmente.
* **💰 Corte de Caja:** Comparativa de "Dinero en Sistema" vs "Dinero Físico" por vendedor.

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
    * Este comando crea tablas y **usuarios demo** (Admin + 3 Vendedores) con ventas simuladas para el Dashboard.
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