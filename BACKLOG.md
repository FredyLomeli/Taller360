# 📋 BACKLOG — TALLER 360
**Auditado y actualizado:** Julio 2026 (auditoría contra código real)
**Basado en código real + visión de negocio ampliada**

---

## ✅ COMPLETADO Y FUNCIONANDO

### Backend
- [x] Autenticación Laravel, roles (6), CRUDs base (Clientes, Productos, Usuarios con edición).
- [x] POS completo, motor de etapas (Kanban), auto-confirmación y pagos parciales.
- [x] Descuento de stock dinámico y retorno al cancelar — ⚠️ **este mecanismo (Camino A) sigue activo en paralelo al de Embarques (Camino B). Ver bug crítico #2 abajo.**
- [x] PDFs (ticket, nota, remisión de embarque) y envío de correos.
- [x] Historial automático vía `SaleObserver`.
- [x] Tabla `production_completions` para registro de piezas terminadas en taller.
- [x] Tabla `sale_deliveries` para gestionar envíos parciales a nivel de línea de detalle.
- [x] Tabla `shipments` para agrupar múltiples piezas/pedidos en viajes logísticos.
- [x] `ShipmentController` con lógica transaccional (descontar stock físico solo al embarcar).
- [x] `CheckRole.php` con parámetros variádicos (`string ...$roles`) para múltiples roles. Confirmado correcto en código.
- [x] `AuthenticatedSessionController` con `redirect()->away()` en login y logout.
- [x] **Filtro semanal en Plan de Producción (antes Tarea 2.1)** — confirmado implementado en `ProductionController@index`, incluso mejor que lo planeado: incluye atrasados y sin fecha además de la semana seleccionada. Ver Fase 2 abajo.

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

## 🆘 BUGS CRÍTICOS DETECTADOS EN AUDITORÍA (Julio 2026) — ATACAR ANTES DE FASE 3

Estos no estaban en ninguna versión previa del backlog. Se detectaron al auditar el código real contra la documentación. Se recomienda resolverlos antes de construir los Dashboards de Fase 3, porque varios alimentan directamente esos dashboards (producción y cartera).

### 🔴 #1 — Plan de Producción no descuenta correctamente lo ya fabricado tras un envío parcial
**Confirmado y reproducible.** `ProductionController::index()` (la pantalla interactiva) nunca carga `completed_quantity` (falta `->withSum('completions as completed_quantity', ...)`, que sí existe en `printReport()`). Como resultado, la fórmula de "pendiente por fabricar" termina restando solo el stock actual, que baja con cada embarque — así que cada envío parcial hace que el sistema *vuelva a pedir* piezas que ya estaban fabricadas.

**Qué hacer:**
- [ ] Agregar `->withSum('completions as completed_quantity', 'quantity_completed')` en `ProductionController::index()`.
- [ ] Cambiar la fórmula de `pending_to_fabricate` para que dependa **solo** de `quantity - completed_quantity`, sin restar `in_stock`.
- [ ] Mostrar `in_stock` como un dato aparte ("piezas listas en bodega, esperando embarque"), no mezclado con lo pendiente por fabricar. Esto además alimenta directamente el KPI de Fase 3.1 ("Piezas terminadas listas para embarcar").

### 🔴 #2 — Doble mecanismo de descuento de stock (Kanban vs. Embarques)
`SaleController::updateStage()` sigue restando/regresando stock al mover una venta a `enviado`/`cancelado`, en paralelo a `ShipmentController::store()`. Nada impide que ambos se disparen sobre el mismo pedido y se reste el doble.

**Qué hacer:**
- [ ] Decidir un único camino de salida de stock (recomendado: solo Embarques, ya que soporta envíos parciales reales).
- [ ] Quitar el descuento/retorno de stock de `updateStage()` — dejar que ese método solo cambie el `stage` y deje el historial (que ya hace vía Observer).
- [ ] Auditar si el paso a `enviado` en el Kanban debe seguir existiendo como estado manual, o si ahora ese cambio de stage debería derivarse automáticamente de los embarques confirmados.

### 🔴 #3 — `storeDelivery()` está roto (columnas inexistentes)
`SaleController::storeDelivery()` (ruta `sales.deliveries.store`) intenta guardar `user_id` y `delivered_at` en `sale_deliveries`, columnas que no existen en esa tabla (solo tiene `shipment_id`, `sale_detail_id`, `quantity_delivered`). Cualquier llamada a este endpoint truena.

**Qué hacer:**
- [ ] Confirmar si este método sigue usándose desde algún botón en el frontend (parece remanente pre-Embarques v2.6).
- [ ] Si ya no se usa: eliminar el método y su ruta.
- [ ] Si se sigue usando: decidir si debe redirigir a crear un `shipment` de una sola línea, o agregar las columnas faltantes a `sale_deliveries` (rompería el diseño actual donde toda entrega SIEMPRE pertenece a un embarque).

### 🟠 #4 — Rutas de Embarques sin restricción de rol
`/shipments/*` no está dentro de ningún `role:...` — cualquier usuario autenticado puede crear/confirmar embarques hoy.

**Qué hacer:**
- [ ] Envolver el grupo de rutas de Embarques en `role:admin,inventario` (según la matriz de roles definida en `CONTEXTO_TECNICO.md` sección 9).

### 🟡 #5 — Falta cancelación de embarque
Documentado como regla de negocio ("si se cancela un embarque antes de confirmar entrega, el stock regresa") pero no implementado — no hay método ni ruta.

**Qué hacer:**
- [ ] Agregar `shipments.cancel` (ruta + método), transaccional: regresa stock de cada `sale_delivery` del embarque, borra o marca el embarque como `cancelado`, deja rastro en `sale_histories`.

---

## 🚨 FASE 0 — Seguridad y Dependencias ✅ COMPLETADA (Junio 2026)
- Versiones `*` en `composer.json` fijadas.
- Axios actualizado. Conflicto Vite/plugin-vue resuelto (`@vitejs/plugin-vue@6.0.7`).
- Laravel actualizado de `12.46.0` a `12.62.0`.
- dompdf: `composer.json` fija `^3.1.1` — confirmar en `composer.lock` que resuelve a `3.1.2`.

---

## 🔴 FASE 1 — Deuda Técnica Crítica ✅ COMPLETADA (Junio 2026)

### 1.1 Edición de Usuarios ✅
### 1.2 Bug Logout "Inception" ✅
### 1.3 HTML sucio en `Sales/Index.vue` ✅
### 1.4 Roles Granulares ✅
- 6 roles: `admin`, `vendedor`, `produccion`, `inventario`, `supervisor`, `financiero`.
- `CheckRole.php` con variádicos — confirmado correcto.
- **Nota:** la asignación de zonas por rol necesita ampliarse — ver matriz de roles completa en `CONTEXTO_TECNICO.md` sección 9. Hoy `supervisor`, `inventario` y `financiero` no tienen ningún módulo asignado en `web.php`.

---

## 🟠 FASE 2 — Producción Semanal y Trazabilidad (95% Completa)

### 2.1 Plan de Producción Semanal — ✅ COMPLETADO (confirmado en auditoría, no estaba marcado antes)
`ProductionController@index` ya filtra por `promised_date` con `startWeek`/`endWeek`, incluyendo atrasados y sin fecha. Falta solo:
- [ ] Confirmar en `Production/Index.vue` si ya existen los botones "← Semana anterior" / "Semana siguiente →" (no se auditó ese archivo en esta sesión).
- [ ] Agregar toggle "Ver todo acumulado" si no existe.

### 2.2 Registro de Piezas Terminadas ✅ COMPLETADO (con bug — ver #1 arriba)

### 2.3 Envíos Parciales ✅ COMPLETADO (con bug — ver #3 arriba, método legado roto)

### 2.4 Embarques como Entidad Propia ✅ COMPLETADO (con bugs — ver #2, #4, #5 arriba)

### 2.5 Mejoras al módulo de Embarques (NUEVO — solicitado Julio 2026)

**2.5.1 — Filtro por cliente al armar embarque**
En `Shipments/Create.vue` / `ShipmentController::create()`, agregar la posibilidad de seleccionar uno o varios clientes y que la lista de pedidos embarcables (`shippableSales`) se filtre solo a esos clientes. Si no se selecciona ningún cliente, se muestra el listado completo (comportamiento actual). Esto permite priorizar clientes preferentes al armar la ruta del día.
- [ ] Agregar selector multi-cliente en `Shipments/Create.vue` (puede reusar el buscador de clientes que ya existe en otras vistas).
- [ ] `ShipmentController::create()` acepta `client_ids[]` opcional por query string y filtra `$sales` por `whereIn('client_id', $clientIds)` cuando venga poblado.

**2.5.2 — Notas de entrega individuales por pedido dentro de un mismo embarque**
Hoy `printManifest()` genera **un solo PDF** con todo el viaje mezclado. Si el viaje lleva pedidos de varios clientes, el chofer no tiene cómo hacer firmar la entrega de cada cliente por separado.
- [ ] Modificar `ShipmentController::printManifest()` (o agregar un método nuevo) para agrupar las `deliveries` del embarque por `sale_id`/cliente y generar **una nota de entrega por pedido**, cada una con su propio total, sus propias piezas, y un campo "Recibido por / Firma".
- [ ] Salida recomendada: un solo PDF combinado con un salto de página por pedido (más simple de imprimir de una vez para el chofer), o un PDF por pedido descargable individualmente desde `Shipments/Show.vue`. A definir según preferencia operativa.
- [ ] Reusar/adaptar la plantilla `shipment_manifest.blade.php` existente, parametrizada para recibir un solo pedido a la vez, y una vista "wrapper" que la repita por cada pedido del embarque.

---

## 🟡 FASE 3 — Dashboards Especializados (confirmado: sin empezar, DashboardController solo tiene ramas admin/vendedor)

### 3.1 Dashboard de Producción
**Sin dinero.** Mostrar:
- [ ] Piezas en producción agrupadas por modelo+material (una vez corregido el bug #1, reusar la misma agregación de `ProductionController`)
- [ ] Piezas terminadas listas para embarcar (completadas pero no en ningún `shipment` — `ProductionCompletion::whereDoesntHave('saleDetail.saleDeliveries')`)
- [ ] Fechas compromiso próximas a vencer (`promised_date` ordenado ascendente)
- [ ] Accesible para roles: `produccion`, `supervisor` (solo lectura), `admin`

### 3.2 Dashboard / Módulo Financiero (rediseñado — ver `CONTEXTO_TECNICO.md` sección 9)
**Sin datos de manufactura.** Basado en **estado de cuenta por cliente**, no en ventas individuales sueltas:
- [ ] Cartera vencida: calculada a partir de `shipments.status = 'entregado'` (valor entregado) menos lo cobrado por cliente — **no** usar `promised_date` como disparador, según lo definido en la sesión de planeación.
- [ ] Estado de cuenta por cliente: deuda exigible entregada, total cobrado, saldo, antigüedad desde `delivered_at`.
- [ ] Registro de pagos/abonos accesible para Finanzas (hoy solo lo tiene `role:admin,vendedor`).
- [ ] Vista de embarques en tránsito y entregados (solo lectura) para que Finanzas dé seguimiento de cobranza a partir de una entrega confirmada.
- [ ] Ingresos por rango de fechas (separado del dashboard operativo).
- [ ] Accesible para roles: `financiero`, `admin`

### 3.3 Selector de Vista para Admin
- [ ] Tabs en Dashboard: "Ventas" / "Producción" / "Financiero" / "Todo"
- [ ] Solo Admin ve las 4 opciones; otros roles ven solo la suya

### 3.4 Sidebar y rutas condicionales por rol (ampliado)
- [ ] Ocultar links que el rol no puede usar (`$page.props.auth.user.role` con `v-if`)
- [ ] Envolver `/shipments/*` en `role:admin,inventario` (bug #4)
- [ ] Dar acceso de solo-lectura a `supervisor` sobre Ventas, Producción y Embarques (hoy no tiene nada)
- [ ] Dar acceso a `financiero` sobre pagos, embarques (lectura) y su propio dashboard

### 3.5 Restricción de precios por rol (DECIDIDO con cliente, Julio 2026)
Regla final: solo `admin`, `financiero` y **`vendedor` dentro de sus propias operaciones** (POS, sus ventas, su dashboard) pueden ver precios. `supervisor`, `inventario` y `produccion` nunca ven precios. Matriz completa de pantallas afectadas en `CONTEXTO_TECNICO.md` sección 9.

**Qué NO hay que tocar (ya está bien así):**
- [x] Vendedor conserva precios en POS, Kanban de sus ventas, Detalle de sus ventas (Modo Oficina) y su dashboard — sin cambios, es el comportamiento actual.
- [x] Catálogo de Productos ya es `role:admin` — nada que hacer.

**Qué sí hay que construir:**
- [ ] `SaleController::index()` — cuando el usuario autenticado sea `supervisor`, excluir `total`, `paid_amount`, `change_amount` del `select()` antes de mandarlo a Inertia (hoy el `select()` ya existe pero incluye esas columnas siempre; falta condicionarlo por rol).
- [ ] `SaleController::show()` — si el usuario es `supervisor`, `inventario` o `produccion`, forzar el equivalente al "Modo Taller" (sin precios) sin importar el query param `production`, en vez de dejarlo a discreción del frontend.
- [ ] `ShipmentController::create()` y `show()` — hoy cargan `details.variant.product` completo, lo que incluye `price_1..price_5` en el JSON aunque la vista no los pinte. Restringir con `variant:id,product_id,material,stock` (sin columnas de precio) ya que Embarques es de Inventarios, que nunca debe recibir precios. Resolver junto con el bug #4 (restricción de rol en Embarques).
- [ ] `DashboardController::index()` — la futura rama de `supervisor` (Fase 3.1, acceso de solo lectura) no debe incluir ningún KPI en $; solo conteos/fechas.
- [ ] Confirmar que `Production/Index.vue` / `ProductionController` nunca reciban `unit_price`/`subtotal` en el payload — ya usan `variant.stock` y `material`, pero verificar que el `with(['variant.product', ...])` no arrastre columnas de precio sin querer (Eloquent trae todas las columnas del modelo relacionado si no se restringen explícitamente con `:columna1,columna2`).

---

## 🟢 FASE 4 — Catálogo Público y Link por Cliente

> ⚠️ Nota de auditoría: la ruta `/` ya devuelve una vista `catalogo.index` (Blade) en `web.php`, no una landing genérica. No se auditó el contenido — confirmar si ya hay algo de avance de esta fase no documentado antes de empezar desde cero.

### 4.1 Catálogo Público (sin precios)
- [ ] Confirmar estado real de la vista `catalogo.index` actual.
- [ ] Rutas públicas: `/catalogo`, `/catalogo/{categoria}`, `/catalogo/producto/{id}`
- [ ] Lee de `products`, `categories`, `product_variants` — nunca expone `price_1..price_5`

### 4.2 Link Personalizado por Cliente (con precios)
- [ ] Campo `catalog_token` (UUID) en tabla `clients` — confirmado que no existe todavía.
- [ ] Ruta `/catalogo/cliente/{token}` con precios según `price_tier` del cliente
- [ ] Botón "Copiar link" en `Clients/Index.vue`

---

## 🔵 FASE 5 — Precios Dinámicos por Flete (Diseño cerrado, construcción pendiente)

- [ ] Campo `unidades_por_flete` en `products` o `product_variants`
- [ ] Tabla `distance_zones` (km_min, km_max, costo_base_flete)
- [ ] Campo `distance_km` en `clients` (manual por ahora)
- [ ] Precio final: `precio_base + (costo_flete_zona / unidades_por_flete)`
- [ ] Pendiente decidir: automatización con Google Maps Distance Matrix API

---

## 🟣 FASE 6 — Reportes PDF (Actividad Final)

- [ ] Reporte financiero (ingresos por rango, estado de cuenta por cliente)
- [ ] Reporte de cartera vencida
- [ ] Reporte de producción semanal en PDF (ya existe `printReport()` — corregir bug #1 primero)
- [ ] Reporte de embarques histórico
- [ ] Notas de entrega individuales por pedido (ver 2.5.2 — comparte plantilla)

---

## 🐛 BUGS CONOCIDOS (previos, aún vigentes)

| # | Bug | Estado |
|---|-----|--------|
| 1 | Input de moneda en Safari/iOS — permite caracteres no numéricos | Pendiente |
| 2 | `formatDate` sin usar en `Production/Index.vue` — código muerto | Pendiente |

*(Ver sección "Bugs Críticos Detectados en Auditoría" arriba para los 5 hallazgos nuevos de Julio 2026 — esos tienen prioridad sobre estos dos.)*

---

## 📌 Orden de Dependencias (actualizado)

```
Fase 0 ✅ → Fase 1 ✅ → Fase 2 (95%, filtro semanal YA está)
                                     ↓
                    🆘 Bugs Críticos (producción, doble stock, storeDelivery, rol embarques, cancelación)
                    — resolver antes de Fase 3, porque Fase 3 depende de estos datos siendo correctos
                                     ↓
                    Fase 2.5 (Filtro clientes en embarques + notas de entrega individuales)
                                     ↓
                              Fase 3 (Dashboards) — usa datos de 2.2, 2.4 y de los bugs ya corregidos
                                     ↓
                    Fase 4 (Catálogo) — verificar avance existente en '/', independiente/paralelizable
                                     ↓
                    Fase 5 (Flete) — depende de 4.2 (link personalizado)
                                     ↓
                    Fase 6 (Reportes PDF) — depende de Fase 3
```