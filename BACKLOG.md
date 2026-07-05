# 📋 BACKLOG — TALLER 360
**Auditado y actualizado:** Julio 2026
**Basado en código real + visión de negocio ampliada**

---

## ✅ COMPLETADO Y FUNCIONANDO

### Backend
- [x] Autenticación Laravel, roles (6), CRUDs base (Clientes, Productos, Usuarios con edición).
- [x] POS completo, motor de etapas (Kanban), auto-confirmación y pagos parciales.
- [x] Descuento de stock dinámico y retorno al cancelar.
- [x] PDFs (ticket, nota, remisión de embarque) y envío de correos.
- [x] Historial automático vía `SaleObserver`.
- [x] Tabla `production_completions` para registro de piezas terminadas en taller.
- [x] Tabla `sale_deliveries` para gestionar envíos parciales a nivel de línea de detalle.
- [x] Tabla `shipments` para agrupar múltiples piezas/pedidos en viajes logísticos.
- [x] `ShipmentController` con lógica transaccional (descontar stock físico solo al embarcar).
- [x] `CheckRole.php` con parámetros variádicos (`string ...$roles`) para múltiples roles.
- [x] `AuthenticatedSessionController` con `redirect()->away()` en login y logout.

### Frontend
- [x] Dashboards (admin, vendedor, pantalla bienvenida para otros roles).
- [x] POS, Kanban, Detalle Híbrido (Oficina/Taller), Plan de Producción.
- [x] CRUD Clientes, Productos, Usuarios (Create + Edit + Index con badges de color por rol).
- [x] Settings con preview de logo.
- [x] Fix 419 Inception (interceptor Axios en `bootstrap.js`).
- [x] Fix Logout Inception (formulario HTML nativo en `AuthenticatedLayout.vue`).
- [x] `Shipments/Index.vue` — Lista de viajes, botón imprimir y confirmar entrega.
- [x] `Shipments/Create.vue` — Armar embarque con validación `Math.min()` y scroll dinámico.
- [x] `Shipments/Show.vue` — Detalle con `chosen_color`, fechas y piezas a bordo.

---

## 🚨 FASE 0 — Seguridad y Dependencias ✅ COMPLETADA (Junio 2026)
- Versiones `*` en `composer.json` fijadas.
- Axios actualizado. Conflicto Vite/plugin-vue resuelto (`@vitejs/plugin-vue@6.0.7`).
- Laravel actualizado de `12.46.0` a `12.62.0`.
- dompdf actualizado de `3.1.1` a `3.1.2`.

---

## 🔴 FASE 1 — Deuda Técnica Crítica ✅ COMPLETADA (Junio 2026)

### 1.1 Edición de Usuarios ✅
- `UserController`: métodos `edit`/`update`, constante `VALID_ROLES` con 6 roles.
- `Users/Edit.vue`: password opcional, datos precargados.
- `Users/Index.vue`: badge de color distinto por rol con objeto `roleBadges`.

### 1.2 Bug Logout "Inception" ✅
- Interceptor 419 en `bootstrap.js` (sesión expirada).
- Formulario HTML nativo en `AuthenticatedLayout.vue` (botón logout activo).
- `AuthenticatedSessionController` usa `redirect()->away()` en login y logout.

### 1.3 HTML sucio en `Sales/Index.vue` ✅
- Div duplicado en sección de botones del modal eliminado.

### 1.4 Roles Granulares ✅
- 6 roles: `admin`, `vendedor`, `produccion`, `inventario`, `supervisor`, `financiero`.
- `CheckRole.php` con variádicos (`string ...$roles`) — bug del primer valor corregido.
- Rutas organizadas en zonas por rol en `web.php`.
- Redirección post-login personalizada por rol.
- Dashboard muestra "Bienvenido" para roles sin módulo propio.

---

## 🟠 FASE 2 — Producción Semanal y Trazabilidad (90% Completa)

### 2.1 Plan de Producción Semanal — 🔴 PENDIENTE
**Problema:** `ProductionController` agrupa TODO lo que está en `stage = 'produccion'` sin filtrar por semana. La empresa planea semanalmente.

**Qué hacer:**
- [ ] Agregar selector de semana en `Production/Index.vue` (similar al filtro de fechas del Dashboard)
- [ ] `ProductionController@index` filtra por `promised_date` dentro del rango semanal
- [ ] Mantener opción "Ver todo acumulado" como toggle

### 2.2 Registro de Piezas Terminadas ✅ COMPLETADO
- Tabla `production_completions` implementada.
- Al registrar completados, stock sube en `product_variants`.

### 2.3 Envíos Parciales ✅ COMPLETADO
- Tabla `sale_deliveries` implementada.
- Una venta puede enviarse en múltiples viajes.
- `is_partial_shipping` en `sales` activado correctamente.

### 2.4 Embarques como Entidad Propia ✅ COMPLETADO
- Tabla `shipments` con `driver_name`, `license_plate`, `destination`, `status`.
- `ShipmentController` con lógica transaccional.
- Vistas Create, Index, Show e impresión de remisión PDF.

---

## 🟡 FASE 3 — Dashboards Especializados (Siguiente Enfoque)

### 3.1 Dashboard de Producción
**Sin dinero.** Mostrar:
- [ ] Piezas en producción agrupadas por modelo+material (datos de `production_completions`)
- [ ] Piezas terminadas listas para embarcar (completadas pero no en ningún `shipment`)
- [ ] Fechas compromiso próximas a vencer (`promised_date` ordenado ascendente)
- [ ] Accesible para roles: `produccion`, `supervisor`, `admin`

### 3.2 Dashboard Financiero
**Sin datos de manufactura.** Mostrar:
- [ ] Cartera vencida: ventas con `promised_date` pasada y `paid_amount < total`
- [ ] Ingresos por rango de fechas (separado del dashboard operativo)
- [ ] Proyección de cobranza pendiente
- [ ] Accesible para roles: `financiero`, `admin`

### 3.3 Selector de Vista para Admin
- [ ] Tabs en Dashboard: "Ventas" / "Producción" / "Financiero" / "Todo"
- [ ] Solo Admin ve las 4 opciones; otros roles ven solo la suya

### 3.4 Sidebar condicional por rol
- [ ] Ocultar links que el rol no puede usar (hoy todos ven todos los links aunque den 403)
- [ ] Usar `$page.props.auth.user.role` para condicionar con `v-if` en `AuthenticatedLayout.vue`

---

## 🟢 FASE 4 — Catálogo Público y Link por Cliente

### 4.1 Catálogo Público (sin precios)
- [ ] Rutas públicas: `/catalogo`, `/catalogo/{categoria}`, `/catalogo/producto/{id}`
- [ ] Diseño tipo tienda (referencia: websitedemos.net/furniture-shop-04)
- [ ] Lee de `products`, `categories`, `product_variants` — nunca expone `price_1..price_5`
- [ ] Se actualiza automáticamente con lo registrado en el sistema

### 4.2 Link Personalizado por Cliente (con precios)
- [ ] Campo `catalog_token` (UUID) en tabla `clients`
- [ ] Ruta `/catalogo/cliente/{token}` con precios según `price_tier` del cliente
- [ ] Botón "Copiar link" en `Clients/Index.vue`
- [ ] Admin puede regenerar el token

---

## 🔵 FASE 5 — Precios Dinámicos por Flete (Diseño cerrado, construcción pendiente)

- [ ] Campo `unidades_por_flete` en `products` o `product_variants`
- [ ] Tabla `distance_zones` (km_min, km_max, costo_base_flete)
- [ ] Campo `distance_km` en `clients` (manual por ahora)
- [ ] Al registrar cliente: sugerir zona automáticamente según distancia
- [ ] Precio final: `precio_base + (costo_flete_zona / unidades_por_flete)`
- [ ] El precio se muestra como número único — nunca desglosado como "flete"
- [ ] Pendiente decidir: automatización con Google Maps Distance Matrix API

---

## 🟣 FASE 6 — Reportes PDF (Actividad Final)

- [ ] Reporte financiero (ingresos por rango de fechas)
- [ ] Reporte de cartera vencida
- [ ] Reporte de producción semanal en PDF (la vista de pantalla ya es imprimible)
- [ ] Reporte de embarques histórico

---

## 🐛 BUGS CONOCIDOS

| # | Bug | Estado |
|---|-----|--------|
| 1 | Input de moneda en Safari/iOS — permite caracteres no numéricos | Pendiente |
| 2 | `formatDate` sin usar en `Production/Index.vue` — código muerto | Pendiente |

---

## 📌 Orden de Dependencias

```
Fase 0 ✅ → Fase 1 ✅ → Fase 2 (90%) → completar 2.1 (filtro semanal)
                                     ↓
                              Fase 3 (Dashboards) — usa datos de 2.2 y 2.4
                                     ↓
                    Fase 4 (Catálogo) — independiente, paralelizable
                                     ↓
                    Fase 5 (Flete) — depende de 4.2 (link personalizado)
                                     ↓
                    Fase 6 (Reportes PDF) — depende de Fase 3
```