# 🛋️ TALLER 360 — Sistema POS Mueblería

![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![Vue 3](https://img.shields.io/badge/Vue.js-3-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)
![Inertia.js](https://img.shields.io/badge/Inertia.js-7855FA?style=for-the-badge&logo=inertia&logoColor=white)
![TailwindCSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)
![Status](https://img.shields.io/badge/Estado-v2.6_Auditado-yellow?style=for-the-badge)

Sistema de gestión de pedidos y manufactura diseñado específicamente para **mueblerías que fabrican sobre pedido**. Integra control de producción, ciclo financiero completo, gestión de inventario por variantes de material y **módulo de logística y embarques parciales**.

---

## 🚀 Estado del Proyecto

| Campo | Detalle |
|-------|---------|
| **Versión** | 2.6 — Módulo de Logística Integrado (con bugs críticos detectados en auditoría) |
| **Última actualización** | Julio 2026 |
| **Backend** | ~90% funcional — ver bugs críticos abajo antes de considerarlo 100% |
| **Frontend** | ~98% funcional |
| **Repositorio** | https://github.com/FredyLomeli/Taller360 |

---

## 🆘 Hallazgos críticos de la auditoría de Julio 2026

Antes de seguir construyendo sobre este sistema, hay 5 bugs de consistencia de datos que se detectaron auditando el código real (no estaban documentados en versiones previas). Detalle completo y solución paso a paso en `BACKLOG.md` y `GUIA_RUTA.md`:

1. El Plan de Producción vuelve a pedir piezas ya fabricadas cada vez que se hace un envío parcial (falta cargar el histórico de fabricación en la vista interactiva).
2. Existen dos mecanismos independientes que descuentan stock (motor de etapas del Kanban + módulo de Embarques) que pueden descontar el doble.
3. El endpoint legado `sales.deliveries.store` está roto — intenta guardar columnas que no existen en la base de datos.
4. Las rutas de Embarques no tienen restricción de rol — cualquier usuario autenticado puede crear/confirmar embarques.
5. No existe forma de cancelar un embarque y regresar el stock, aunque está documentado como regla de negocio.

---

## ✅ Módulos Funcionales

### 📊 Dashboard Estratégico
KPIs en tiempo real diferenciados por rol. Admin ve métricas globales; Vendedor ve sus propios números. Roles sin dashboard propio (`produccion`, `inventario`, `supervisor`, `financiero`) ven una pantalla de bienvenida limpia hasta que se construya su módulo en la Fase 3.

### 🛒 POS — Order Builder
Catálogo visual con colores dinámicos por material, firma digital del cliente, modal para crear cliente sin salir del POS, notas y costos adicionales por partida, precios automáticos según tier del cliente (Listas A-E).

### 📋 Tablero Kanban
Flujo completo `Pedido → Confirmado → Producción → Enviado → Entregado → Cancelado`. Historial completamente automático vía `SaleObserver`. ⚠️ El descuento de stock al mover a "Enviado" sigue activo y entra en conflicto con el módulo de Embarques — ver hallazgo crítico #2.

### 📄 Detalle de Venta Híbrido
Switch Modo Oficina (financiero) / Modo Taller (técnico, sin precios) en una sola vista. Historial de abonos y modal de cobranza integrados.

### 💰 Ciclo de Cobranza
Abonos parciales con validación de deuda y transacción atómica. Auto-confirmación del pedido si se registra anticipo al crearlo. *(Rediseño en curso: estado de cuenta agregado por cliente para el futuro rol Finanzas — ver Fase 3.2)*

### 🏭 Plan Maestro de Producción
Agrupación por Modelo + Material con desglose por color, con filtro semanal por `promised_date` ya implementado (incluye atrasados y sin fecha). ⚠️ Ver hallazgo crítico #1: la vista interactiva no refleja correctamente lo ya fabricado tras envíos parciales.

### 🚚 Logística y Embarques (v2.6)
Control de flotilla. Permite:
- Registrar cuándo el taller termina piezas físicas (`production_completions`) sin cambiar el estado del pedido.
- Agrupar piezas de múltiples pedidos en un solo viaje (`shipments`).
- Envíos parciales: una venta de 5 piezas puede enviarse en 2 o más viajes distintos (`sale_deliveries`).
- Generar remisión PDF para el chofer.
- Confirmar entrega y evaluar cierre automático del pedido.
- *(En construcción)* Filtro por cliente al armar embarques, y notas de entrega individuales por pedido cuando un viaje agrupa a varios clientes.

### 📦 Gestión de Productos e Inventario
CRUD completo con variantes dinámicas por material, imagen, marcado de favoritos. Sincronización inteligente de variantes al editar (upsert). Alerta de stock crítico en Dashboard (≤ 5 piezas, solo favoritos).

### 🛡️ Seguridad y Roles
6 roles definidos: `admin`, `supervisor`, `vendedor`, `inventario`, `produccion`, `financiero`. Middleware `CheckRole` con parámetros variádicos, confirmado correcto. Matriz completa de permisos por módulo en `CONTEXTO_TECNICO.md`. ⚠️ Solo `admin` y `vendedor` tienen zonas de rutas completas hoy; `supervisor`, `inventario` y `financiero` aún no tienen módulo asignado en código.

### 🖨️ PDFs y Correo
Ticket de venta, nota de venta y remisión de embarque. Compatible con hosting compartido (`FILESYSTEM_PUBLIC_ROOT`). Envío de nota por correo con PDF adjunto en memoria.

---

## ⚠️ Pendiente

| Prioridad | Tarea | Fase |
|-----------|-------|------|
| 🆘 Crítico | Corregir Plan de Producción (no descuenta lo fabricado tras envío parcial) | Bug #1 |
| 🆘 Crítico | Unificar mecanismo de descuento de stock (Kanban vs. Embarques) | Bug #2 |
| 🆘 Crítico | Eliminar o reparar `storeDelivery()` (columnas inexistentes) | Bug #3 |
| 🟠 Alto | Restringir rol en rutas de Embarques | Bug #4 |
| 🟠 Alto | Cancelación de embarques con retorno de stock | Bug #5 |
| 🟢 Medio | Filtro por cliente al armar embarques | 2.5.1 |
| 🟢 Medio | Notas de entrega individuales por pedido en un mismo viaje | 2.5.2 |
| 🟡 Medio | Dashboard de Producción (sin dinero, solo piezas y fechas) | 3.1 |
| 🟡 Medio | Dashboard/Módulo Financiero (estado de cuenta por cliente, cartera) | 3.2 |
| 🟡 Medio | Selector de vista para Admin | 3.3 |
| 🟡 Medio | Rutas y sidebar completos para Supervisor, Inventarios, Finanzas | 3.4 |
| 🟢 Futuro | Catálogo público + link personalizado por cliente (verificar avance ya existente en `/`) | 4 |
| 🔵 Futuro | Precios dinámicos por flete (distancia + capacidad) | 5 |
| 🟣 Final | Reportes PDF (financiero, producción, embarques) | 6 |
| 🐛 Bug | Input de moneda en Safari/iOS | — |
| 🐛 Bug | `formatDate` sin usar en `Production/Index.vue` | — |

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

| Capa | Tecnología |
|------|-----------|
| Backend | Laravel 12 (PHP 8.2+) |
| Frontend | Vue 3 (`<script setup>`) |
| Puente | Inertia.js |
| Estilos | Tailwind CSS |
| Base de Datos | MySQL / MariaDB |
| PDFs | barryvdh/laravel-dompdf |
| Firma digital | vue-signature-pad |
| Alertas UI | SweetAlert2 |
| Utilidades | Lodash (debounce/throttle) |

---

## 📚 Documentación Relacionada

- `CONTEXTO_TECNICO.md` — Schema de BD, relaciones, rutas, reglas de negocio y matriz de roles. **Compartir con IA al retomar el proyecto.**
- `BACKLOG.md` — Lista de tareas pendientes con detalle técnico por fase, incluyendo bugs críticos de la auditoría.
- `GUIA_RUTA.md` — Pasos exactos para completar cada tarea, en orden recomendado, con código de referencia para las correcciones críticas.