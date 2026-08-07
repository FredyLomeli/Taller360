# 🛋️ TALLER 360 — Sistema POS Mueblería

![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![Vue 3](https://img.shields.io/badge/Vue.js-3-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)
![Inertia.js](https://img.shields.io/badge/Inertia.js-7855FA?style=for-the-badge&logo=inertia&logoColor=white)
![TailwindCSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)
![Status](https://img.shields.io/badge/Estado-v2.6_Auditado_contra_c%C3%B3digo_real-brightgreen?style=for-the-badge)

Sistema de gestión de pedidos y manufactura diseñado específicamente para **mueblerías que fabrican sobre pedido**. Integra control de producción, ciclo financiero completo, gestión de inventario por variantes de material y **módulo de logística y embarques parciales**.

---

## 🚀 Estado del Proyecto

| Campo | Detalle |
|-------|---------|
| **Versión** | 2.6 — Módulo de Logística Integrado |
| **Última auditoría** | 25 de julio 2026 — **contra el código fuente real** (no contra reportes previos) |
| **Backend** | Los 5 bugs críticos de la auditoría de julio quedaron **confirmados como resueltos** en el código. Ver hallazgo nuevo abajo (`UserController` faltante) antes de considerarlo 100% |
| **Frontend** | Funcional; catálogo público (`/`) es un mockup estático sin datos reales — ver Fase 4 |
| **Repositorio** | https://github.com/FredyLomeli/Taller360 |

---
## 🆘 Hallazgo nuevo — conflicto de versiones de Tailwind CSS

`package.json` tiene instaladas **dos versiones incompatibles de Tailwind al mismo tiempo**:
- `tailwindcss: ^3.2.1` + `postcss` + `autoprefixer` (stack clásico de Tailwind v3).
- `@tailwindcss/vite: ^4.0.0` (el plugin de Vite exclusivo de Tailwind v4, que reemplaza a PostCSS).

`resources/css/app.css` usa la sintaxis clásica `@tailwind base; @tailwind components; @tailwind utilities;` (v3), y `vite.config.js` **no** incluye el plugin `@tailwindcss/vite`. Es decir: el proyecto **corre en v3 real**, pero tiene una dependencia de v4 instalada sin usar — probablemente un intento de migración a v4 que no se completó, o un `npm install` accidental. No rompe nada hoy, pero es peso muerto en `node_modules` y puede confundir a quien retome el proyecto pensando que ya está en v4.

---

## ✅ Los 5 bugs críticos de julio 2026 — confirmados resueltos en código

| # | Bug | Verificación en código |
|---|---|---|
| 1 | Plan de Producción no descontaba lo ya fabricado | `ProductionController::index()` y `printReport()` usan `withSum('completions as completed_quantity', ...)` y `pending_to_fabricate = quantity - completed_quantity`. ✅ |
| 2 | Doble descuento de stock (Kanban vs. Embarques) | `SaleController::updateStage()` solo acepta `pedido,confirmado,produccion,cancelado` — ya no toca stock ni esos dos estados. `ShipmentController` es el único que descuenta/regresa stock. ✅ |
| 3 | `sales.deliveries.store` roto | El método y la ruta ya no existen en el código. ✅ |
| 4 | Rutas de Embarques sin restricción de rol | `routes/web.php` las envuelve en `role:admin,inventario`. ✅ |
| 5 | Sin forma de cancelar un embarque | `ShipmentController::cancel()` implementado, con reglas de negocio (flota propia vs. recolección en mostrador) respetadas. ✅ |

Detalle técnico completo en `BACKLOG.md` y `GUIA_RUTA.md`.

---

## ✅ Módulos Funcionales

### 📊 Dashboard Estratégico
KPIs en tiempo real diferenciados por rol. Admin ve métricas globales; Vendedor ve sus propios números. Roles sin dashboard propio (`produccion`, `inventario`, `supervisor`, `financiero`) ven una pantalla de bienvenida limpia hasta que se construya su módulo en la Fase 3 — confirmado sin empezar (`DashboardController::index()` solo tiene ramas `admin`/`vendedor`).

### 🛒 POS — Order Builder
Catálogo visual con colores dinámicos por material, firma digital del cliente, modal para crear cliente sin salir del POS, notas y costos adicionales por partida, precios automáticos según tier del cliente (Listas A-E). ⚠️ `Client::all()` sin límite en `SaleController::create()` — pendiente de optimización (ver Fase 2.6).

### 📋 Tablero Kanban
Flujo `Pedido → Confirmado → Producción → Enviado → Entregado → Cancelado`. Historial automático vía `SaleObserver`. Las transiciones a `Enviado`/`Entregado` ya **no son manuales** — dependen exclusivamente del módulo de Embarques (confirmado en `SaleController::updateStage`).

### 📄 Detalle de Venta Híbrido
Switch Modo Oficina (financiero) / Modo Taller (técnico, sin precios) en una sola vista, hoy controlado por un query param del frontend. ⚠️ Pendiente: forzar Modo Taller automáticamente por rol en el backend (hoy queda a discreción del frontend).

### 💰 Ciclo de Cobranza
Abonos parciales con validación de deuda y transacción atómica (`SalePaymentController`). Auto-confirmación del pedido si se registra anticipo al crearlo.

### 🏭 Plan Maestro de Producción
Agrupación por `product_variant_id` con desglose por color, filtro semanal por `promised_date` (incluye atrasados y sin fecha) con botones de navegación de semana confirmados en `Production/Index.vue`. Badge de 4 estados de inventario confirmado. ⚠️ Falta el toggle "Ver todo acumulado" (no existe en el código).

### 🚚 Logística y Embarques (v2.6)
Control de flotilla. Confirmado en código:
- Registrar piezas terminadas (`production_completions`) sin cambiar el estado del pedido.
- Agrupar piezas de múltiples pedidos en un solo viaje (`shipments`).
- Envíos parciales por línea de detalle (`sale_deliveries`).
- Generar remisión PDF para el chofer.
- Confirmar entrega y cerrar el pedido revisando **todas** las líneas, no solo la que se entrega.
- Cancelar un embarque y regresar stock, con reglas distintas según `pickup_type`.
- **Recolección en mostrador vs. flota propia** — implementado completo, backend y frontend (el toggle en `Shipments/Create.vue` ya existe; el Backlog previo lo daba como pendiente).
- *(Pendiente real, confirmado en código)*: selector multi-cliente en `Shipments/Create.vue` (el backend ya soporta `client_ids[]`, falta la UI). Las notas de entrega individuales por pedido (`shipment_manifest.blade.php`) **no agrupan por cliente/pedido** — es un listado plano de todas las entregas del viaje.

### 📦 Gestión de Productos e Inventario
CRUD completo con variantes dinámicas por material y medida, imagen, marcado de favoritos. ⚠️ `ProductController::index()` carga **todos** los productos con **todas** sus variantes sin paginar — pendiente de optimización para hosting compartido.

### 🛡️ Seguridad y Roles
6 roles definidos: `admin`, `supervisor`, `vendedor`, `inventario`, `produccion`, `financiero`. Middleware `CheckRole` con parámetros variádicos, confirmado correcto. Matriz completa de permisos por módulo en `CONTEXTO_TECNICO.md`. Solo `admin`, `vendedor`, `produccion` e `inventario` tienen zonas de rutas completas hoy; `supervisor` y `financiero` no tienen módulo asignado en código.

### 🖨️ PDFs y Correo
Ticket de venta, nota de venta y remisión de embarque. `dompdf` confirmado en `3.1.2` vía `composer.lock`. Compatible con hosting compartido (`FILESYSTEM_PUBLIC_ROOT`). Envío de nota por correo con PDF adjunto en memoria.

### 🌐 Catálogo Público (`/`)
⚠️ Confirmado: es una vista Blade estática (`catalogo.index`) con categorías y un producto de ejemplo **hardcodeados**. No consulta `products`, `categories` ni `product_variants`. La Fase 4 no tiene avance funcional real más allá de este mockup visual.

---

## ⚠️ Pendiente (confirmado contra código, 25 jul 2026)

| Prioridad | Tarea |
|-----------|-------|
| 🟢 Limpieza | Quitar `@tailwindcss/vite` de `package.json` (no se usa, el proyecto corre en Tailwind v3 vía PostCSS) o completar la migración a v4 si era la intención |
| 🟢 Medio | Selector multi-cliente (UI) al armar embarques — backend ya listo |
| 🟢 Medio | Notas de entrega agrupadas por pedido/cliente en `shipment_manifest.blade.php` — hoy es un listado plano |
| 🟡 Medio | Fecha compromiso (`promised_date`) editable después de creado el pedido |
| 🟡 Medio | Optimización de consultas: `ProductController::index()` sin paginar, `Client::all()` sin límite en el POS |
| 🟡 Medio | Forzar Modo Taller por rol en backend (`Sales/Show.vue` / `SaleController::show()`) |
| 🟡 Medio | Dashboards especializados: Producción, Financiero, selector Admin, rutas Supervisor/Inventarios/Finanzas (Fase 3, sin empezar) |
| 🟢 Futuro | Catálogo público real conectado a BD (hoy es mockup estático) + link personalizado por cliente |
| 🔵 Futuro | Precios dinámicos por flete |
| 🟣 Final | Reportes PDF (financiero, producción, embarques) |
| 🐛 Bug | Input de moneda en Safari/iOS (no verificado en esta auditoría — pendiente revisar) |

---

## 🛠️ Instalación

### Requisitos
PHP 8.2+, Node.js 18+, MySQL/MariaDB

### Desarrollo Local
```bash
git clone https://github.com/FredyLomeli/Taller360.git
cd Taller360
composer install
npm install
cp .env.example .env
php artisan key:generate
# Configurar BD en .env
php artisan migrate:fresh --seed
php artisan serve
npm run dev
```

### Producción (Neubox / Hosting Compartido)
```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tudominio.com
FILESYSTEM_PUBLIC_ROOT=/home/usuario/public_html/storage
```
```bash
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## 🏗️ Stack Tecnológico

| Capa | Tecnología | Confirmado en |
|------|-----------|----------------|
| Backend | Laravel 12.62.0 (PHP 8.2+) | `composer.lock` |
| Frontend | Vue 3.4+ (`<script setup>`) | `package.json` |
| Puente | Inertia.js (`@inertiajs/vue3` 2.x) | `package.json`, sin rutas `/api/` en `routes/web.php` |
| Build | Vite 7.x + `laravel-vite-plugin` 2.x | `package.json`, `vite.config.js` |
| Estilos | Tailwind CSS **3.2.1** (activo, vía PostCSS) | `resources/css/app.css` — ⚠️ ver hallazgo de conflicto de versiones arriba |
| Base de Datos | MySQL / MariaDB | |
| PDFs | barryvdh/laravel-dompdf 3.1.2 | `composer.lock` |
| Firma digital | vue-signature-pad 3.x | `package.json`, `Sales/Create.vue` |
| Alertas UI | SweetAlert2 11.x | `package.json` |
| Utilidades | Lodash 4.x (debounce/throttle) | `package.json` |

---

## 📚 Documentación Relacionada

- `CONTEXTO_TECNICO.md` — Schema de BD, relaciones, rutas, reglas de negocio y matriz de roles. **Compartir con IA al retomar el proyecto.**
- `BACKLOG.md` — Lista de tareas pendientes con detalle técnico, reconciliada contra el código real.
- `GUIA_RUTA.md` — Próximos pasos recomendados, en orden de prioridad, con hallazgos de la auditoría de código del 25 de julio 2026.
