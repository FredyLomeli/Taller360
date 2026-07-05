# 🛋️ TALLER 360 — Sistema POS Mueblería

![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![Vue 3](https://img.shields.io/badge/Vue.js-3-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)
![Inertia.js](https://img.shields.io/badge/Inertia.js-7855FA?style=for-the-badge&logo=inertia&logoColor=white)
![TailwindCSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)
![Status](https://img.shields.io/badge/Estado-v2.6_Logística_Integrada-success?style=for-the-badge)

Sistema de gestión de pedidos y manufactura diseñado específicamente para **mueblerías que fabrican sobre pedido**. Integra control de producción, ciclo financiero completo, gestión de inventario por variantes de material y **módulo de logística y embarques parciales**.

---

## 🚀 Estado del Proyecto

| Campo | Detalle |
|-------|---------|
| **Versión** | 2.6 — Módulo de Logística Integrado |
| **Última actualización** | Julio 2026 |
| **Backend** | 100% funcional |
| **Frontend** | ~98% funcional |
| **Repositorio** | https://github.com/FredyLomeli/Taller360 |

---

## ✅ Módulos Funcionales

### 📊 Dashboard Estratégico
KPIs en tiempo real diferenciados por rol. Admin ve métricas globales; Vendedor ve sus propios números. Roles sin dashboard propio (`produccion`, `inventario`, `supervisor`, `financiero`) ven una pantalla de bienvenida limpia hasta que se construya su módulo en la Fase 3.

### 🛒 POS — Order Builder
Catálogo visual con colores dinámicos por material, firma digital del cliente, modal para crear cliente sin salir del POS, notas y costos adicionales por partida, precios automáticos según tier del cliente (Listas A-E).

### 📋 Tablero Kanban
Flujo completo `Pedido → Confirmado → Producción → Enviado → Entregado → Cancelado`. Descuento y retorno automático de stock. Historial completamente automático vía `SaleObserver`.

### 📄 Detalle de Venta Híbrido
Switch Modo Oficina (financiero) / Modo Taller (técnico, sin precios) en una sola vista. Historial de abonos y modal de cobranza integrados.

### 💰 Ciclo de Cobranza
Abonos parciales con validación de deuda y transacción atómica. Auto-confirmación del pedido si se registra anticipo al crearlo.

### 🏭 Plan Maestro de Producción
Agrupación por Modelo + Material con desglose por color. Vista optimizada para impresión en taller. *(Pendiente: filtro por semana usando `promised_date` — Tarea 2.1)*

### 🚚 Logística y Embarques (NUEVO v2.6)
Control total de flotilla. Permite:
- Registrar cuándo el taller termina piezas físicas (`production_completions`) sin cambiar el estado del pedido.
- Agrupar piezas de múltiples pedidos en un solo viaje (`shipments`).
- Envíos parciales: una venta de 5 piezas puede enviarse en 2 o más viajes distintos (`sale_deliveries`).
- Generar remisión PDF para el chofer (`shipment_manifest.blade.php`).
- Confirmar entrega y evaluar cierre automático del pedido.

### 📦 Gestión de Productos e Inventario
CRUD completo con variantes dinámicas por material, imagen, marcado de favoritos. Sincronización inteligente de variantes al editar (upsert). Alerta de stock crítico en Dashboard (≤ 5 piezas, solo favoritos).

### 🛡️ Seguridad y Roles
6 roles implementados: `admin`, `vendedor`, `produccion`, `inventario`, `supervisor`, `financiero`. Middleware `CheckRole` con parámetros variádicos. Cada rol redirige a su pantalla correcta al iniciar sesión.

### 🖨️ PDFs y Correo
Ticket de venta, nota de venta y remisión de embarque. Compatible con hosting compartido (`FILESYSTEM_PUBLIC_ROOT`). Envío de nota por correo con PDF adjunto en memoria.

---

## ⚠️ Pendiente

| Prioridad | Tarea | Fase |
|-----------|-------|------|
| 🟠 Alto | Filtro semanal en Plan de Producción (`promised_date`) | 2.1 |
| 🟡 Medio | Dashboard de Producción (sin dinero, solo piezas y fechas) | 3 |
| 🟡 Medio | Dashboard Financiero (cartera vencida, ingresos) | 3 |
| 🟡 Medio | Selector de vista para Admin | 3 |
| 🟢 Futuro | Catálogo público + link personalizado por cliente | 4 |
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

- `CONTEXTO_TECNICO.md` — Schema de BD, relaciones, rutas y reglas de negocio. **Compartir con IA al retomar el proyecto.**
- `BACKLOG.md` — Lista de tareas pendientes con detalle técnico por fase.
- `GUIA_DE_RUTA.md` — Pasos exactos para completar cada tarea, en orden recomendado.