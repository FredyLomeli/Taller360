# 🛋️ TALLER 360 — Sistema POS Mueblería

![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![Vue 3](https://img.shields.io/badge/Vue.js-3-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)
![Inertia.js](https://img.shields.io/badge/Inertia.js-7855FA?style=for-the-badge&logo=inertia&logoColor=white)
![TailwindCSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)
![Status](https://img.shields.io/badge/Estado-v2.7_Sincronizado_con_código_real-brightgreen?style=for-the-badge)

Sistema de gestión de pedidos y manufactura diseñado específicamente para **mueblerías que fabrican sobre pedido**. Integra control de producción, ciclo financiero completo, gestión de inventario por variantes de material, **órdenes de trabajo para manufactura autónoma**, **catálogo digital público en Blade SSR** y **módulo de logística y embarques parciales**.

---

## 🚀 Estado del Proyecto

| Campo | Detalle |
|-------|---------|
| **Versión** | 2.7 — Manufactura Avanzada & Catálogo Digital |
| **Última auditoría** | 05 de septiembre 2026 — sincronizado directamente contra el código fuente real |
| **Backend** | Estable. Bugs históricos resueltos. Sprint de agosto (Órdenes de Trabajo, Stock Mínimo, Auto-correo, Supervisor ampliado) y Catálogo Blade SSR integrados. 16 suites de tests automatizados. |
| **Frontend** | Funcional en Vue 3 / Inertia.js para gestión interna; Laravel Blade SSR puro para Landing y Catálogo Comercial público. |
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

### 🏭 Plan Maestro de Producción y Órdenes de Trabajo (v2.7)
Agrupación por `product_variant_id` con desglose por color, filtro semanal navegable y badge de 4 estados de inventario.
- **Órdenes de Trabajo autónomas (`work_orders`):** Fabricación anticipada para stock de temporada sin requerir pedido de cliente.
- **Pausa de remanentes (`production_hold`):** Si un envío parcial deja piezas pendientes, no saturan la cola urgente del taller hasta su liberación manual.
- Formulario de captura rápida con autocompletado para el taller en `Production/Index.vue`.

### 🚚 Logística y Embarques (v2.6)
Control de flotilla y recolección:
- Registrar piezas terminadas (`production_completions`) sin alterar la etapa del pedido.
- Agrupar piezas de múltiples pedidos en un solo viaje (`shipments`).
- Envíos parciales por partida (`sale_deliveries`).
- Generar remisión PDF para chofer o cliente.
- Confirmar entrega y cerrar pedidos revisando el 100% de las líneas.
- Cancelar embarque y restituir stock con reversión automática de etapa a producción si quedan faltantes.
- **Recolección en mostrador vs. flota propia (`pickup_type`):** Toggle visual en `Shipments/Create.vue` y cierre instantáneo para entregas locales.

### 📦 Gestión de Productos y Stock Mínimo (v2.7)
CRUD completo con variantes por material y medida, imagen y favoritos.
- **Stock mínimo por variante (`min_stock`):** Configurable exclusivamente en productos preferentes (`is_favorite = true`).
- Alerta dinámica en Dashboard para monitoreo de stock crítico.

### 🛡️ Seguridad y Roles (6 Roles)
- `admin`: Control total del sistema.
- `supervisor`: Permisos operativos completos en Producción, Almacén/Productos y Embarques.
- `vendedor`: POS, clientes, abonos y ventas propias.
- `produccion`: Plan maestro de taller, captura de avances físicos y liberación de remanentes.
- `inventario`: Armado y despacho de embarques, control de stock.
- `financiero`: Reservado para módulo de cobranza y estados de cuenta (Fase 3).

### 🖨️ PDFs y Correo
Ticket de venta, nota de venta y remisión de embarque (`dompdf 3.1.2`). Despacho diferido síncrono (`dispatch()->afterResponse()`) para envíos de correo sin congelar la interfaz del usuario.

### 🌐 Catálogo Público y Showroom Digital (`/` y `/catalogo`)
Desarrollado en Laravel Blade puro (SSR) y Vanilla JS para máxima velocidad y optimización SEO:
- Landing corporativa (`/`) y Catálogo completo (`/catalogo`) conectados a base de datos real.
- Filtro por categoría y ajuste `catalog_only_with_images`.
- Ficha técnica interactiva en modal con selector de acabados por categoría.
- Carrusel responsivo con swipe táctil y controles por teclado.
- Botón directo de cotización vía WhatsApp empresarial (`company_whatsapp`).
- Precios y stock estrictamente ocultos al público.

---

## ⚠️ Pendiente Real Confirmado en Código (05 sep 2026)

| Prioridad | Tarea | Detalle |
|-----------|-------|---------|
| 🟢 Alta | Selector multi-cliente en Embarques | Backend listo con `client_ids[]`, falta componente UI en `Shipments/Create.vue` |
| 🟢 Alta | Remisión de embarque agrupada | Modificar `shipment_manifest.blade.php` para separar partidas por cliente/pedido |
| 🟢 Limpieza | Dependencias Tailwind CSS | Desinstalar `@tailwindcss/vite` (sin uso en v3) o completar migración a v4 |
| 🟡 Media | Paginación y límite de consultas | Paginar `ProductController::index()` y limitar `Client::all()` en el POS |
| 🟡 Media | Fecha compromiso editable | Permitir corregir `promised_date` tras la creación del pedido |
| 🟡 Media | Forzar Modo Taller por rol | Asignar modo taller en backend para `supervisor`, `produccion` e `inventario` |
| 🟢 Comercial | Link de catálogo por cliente (Fase 4.2) | Generar `catalog_token` para mostrar precios personalizados según `price_tier` |
| 🟡 Media | Dashboards especializados (Fase 3) | Construir vistas dedicadas para Producción, Finanzas e Inventarios |
| 🔵 Futuro | Precios dinámicos por flete (Fase 5) | Cálculo kilométrico y zonas de flete |
| 🟣 Final | Reportes PDF globales (Fase 6) | Balances financieros, cartera vencida y producción histórica |

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
