# 🛋️ TALLER 360 — Sistema POS Mueblería

![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![Vue 3](https://img.shields.io/badge/Vue.js-3-4FC08D?style=for-the-badge&logo=vue.js&logoColor=white)
![Inertia.js](https://img.shields.io/badge/Inertia.js-7855FA?style=for-the-badge&logo=inertia&logoColor=white)
![TailwindCSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)
![Status](https://img.shields.io/badge/Estado-Auditado_y_Casi_Completo-success?style=for-the-badge)

Sistema de gestión de pedidos y manufactura diseñado específicamente para **mueblerías que fabrican sobre pedido**. Integra control de producción, ciclo financiero completo (abonos/cobranza) y gestión de inventario por variantes de material.

---

## 🚀 Estado del Proyecto

| Campo | Detalle |
|-------|---------|
| **Versión** | 2.5 — Auditado contra código real |
| **Última auditoría** | Junio 2026 |
| **Backend** | 100% funcional |
| **Frontend** | ~95% funcional (falta edición de usuarios) |
| **Repositorio** | https://github.com/FredyLomeli/Taller360 |

> Este README fue regenerado a partir de una auditoría línea por línea de controladores, modelos, middlewares, observer y vistas Vue. Refleja el código real, no solo lo planeado.

---

## ✅ Módulos Funcionales (Confirmados en código)

### 📊 Dashboard Estratégico
KPIs en tiempo real diferenciados por rol, filtros de fecha, alerta de stock crítico (solo productos favoritos, ≤5 piezas) y tabla de rendimiento de vendedores.

### 🛒 POS — Order Builder
Catálogo visual con colores dinámicos por material, firma digital del cliente (`vue-signature-pad`), modal para crear cliente sin salir del POS, notas y costos adicionales por partida, precios automáticos según tier del cliente.

### 📋 Tablero Kanban
Flujo completo `Pedido → Confirmado → Producción → Enviado → Entregado → Cancelado`. Descuento y retorno automático de stock. **Historial completamente automático** vía `SaleObserver` — cada cambio de etapa queda registrado sin intervención manual del controlador.

### 📄 Detalle de Venta Híbrido
Switch Modo Oficina (financiero) / Modo Taller (técnico, sin precios) en una sola vista.

### 💰 Ciclo de Cobranza
Abonos parciales con validación de deuda y transacción atómica. Auto-confirmación del pedido si se registra anticipo al crearlo.

### 🏭 Plan Maestro de Producción
Agrupación por Modelo + Material con desglose por color, vista optimizada para impresión en taller.

### 📦 Gestión de Productos
CRUD completo con variantes dinámicas, materiales sugeridos según categoría, imagen, marcado de favoritos. Sincronización inteligente de variantes al editar (upsert).

### 🛡️ Seguridad y Roles
Middleware `CheckRole` valida acceso por rol en cada ruta protegida. El usuario autenticado se comparte globalmente a todas las vistas Vue vía `HandleInertiaRequests`.

### 🖨️ PDFs y Correo
Tickets y notas de venta en PDF compatibles con hosting compartido (`FILESYSTEM_PUBLIC_ROOT`). Envío de nota de venta por correo con PDF adjunto en memoria — **ya implementado**, no es una feature futura.

---

## ⚠️ Pendiente Confirmado

| Prioridad | Tarea | Detalle |
|-----------|-------|---------|
| 🔴 Crítico | Edición de Usuarios | No existe `Users/Edit.vue` ni métodos `edit`/`update` en el controlador |
| 🟠 Alto | Reporte Corte de Caja (Excel) | Feature nueva, no iniciada |
| 🟠 Alto | Ajuste Manual de Inventario | Feature nueva, no iniciada |
| 🟡 Medio | Bug Logout "Inception" | Login aparece flotando al expirar sesión (419) |
| 🟡 Medio | HTML sucio en `Sales/Index.vue` | Div mal cerrado en modal, no rompe funcionalidad |
| 🔵 Futuro | Notificaciones automáticas, CFDI 4.0, Envíos Parciales, PWA | Ver `BACKLOG.md` |

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

- `CONTEXTO_TECNICO.md` — Schema de BD, relaciones, rutas y reglas de negocio. **Usar este archivo al retomar el proyecto con cualquier IA.**
- `BACKLOG.md` — Lista de tareas pendientes con detalle técnico.
- `GUIA_DE_RUTA.md` — Pasos exactos para completar cada tarea pendiente, en orden recomendado.