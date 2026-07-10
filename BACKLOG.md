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

## 🛠️ SESIÓN DE IMPLEMENTACIÓN EN VIVO — Julio 2026

A diferencia de la auditoría inicial (que solo revisó código sin ejecutarlo), esta sección documenta cambios hechos **bug por bug, en el código real del proyecto, con pruebas manuales confirmadas por el equipo** en su propio entorno. Se trabajó directo sobre el proyecto real (no sobre el zip de auditoría), por eso el detalle de archivos/líneas puede diferir levemente de la sección de auditoría original más abajo.

### ✅ Bug #1 — Plan de Producción no descontaba lo ya fabricado — RESUELTO Y PROBADO
`ProductionController::index()` no cargaba `completed_quantity`; la fórmula de pendientes terminaba restando solo stock (que baja con cada embarque). Fix aplicado: `->withSum('completions as completed_quantity', 'quantity_completed')` + fórmula `pending_to_fabricate = quantity - completed_quantity` (ya sin restar stock). Mismo fix replicado en `printReport()`. **Confirmado con datos reales:** un pedido totalmente fabricado y parcialmente embarcado ya no vuelve a pedirse producir.

**Mejoras de UX agregadas en la misma sesión (no eran parte del bug original, se detectaron al probar):**
- El badge de estatus (`Production/Index.vue`) solo comparaba contra `in_stock`, así que un producto ya fabricado y ya embarcado (stock en 0) se mostraba como "🛠️ Fabricar Todo" — igual que uno nunca fabricado. Se agregó un 4º estado: "📦 Fabricado y Embarcado".
- Se agregó `sortBy` en el backend para que los grupos con piezas pendientes aparezcan primero y los ya fabricados/embarcados hasta abajo — evita que producción vea como "urgente" algo que ya está resuelto.

### ⏳ Bug #2 — Doble mecanismo de descuento de stock (Kanban vs. Embarques) — PENDIENTE
`SaleController::updateStage()` todavía resta/regresa stock al mover a `enviado`/`cancelado`, en paralelo a `ShipmentController::store()`. No se ha tocado en esta ronda de implementación en vivo. Ligado a la decisión de cuándo pasar un pedido a "Enviado" (ver más abajo, "Decisiones pendientes").

### ⏳ Bug #3 — `storeDelivery()` roto (columnas inexistentes) — PENDIENTE
Sigue intentando guardar `user_id`/`delivered_at` en `sale_deliveries`, columnas que no existen. No se ha tocado en esta ronda.

### ⏳ Bug #4 — Rutas de Embarques sin restricción de rol — PENDIENTE
`/shipments/*` sigue sin `role:...`. No se ha tocado en esta ronda.

### ⏳ Bug #5 — Falta cancelación de embarque — PENDIENTE
No existe `cancel()` en `ShipmentController`. No se ha tocado en esta ronda.

### 🆕 Bug #6 — Inventario podía quedar negativo al armar un embarque — ✅ RESUELTO Y PROBADO
**Encontrado por el cliente probando el sistema, no en la auditoría original.** `ShipmentController::store()` nunca validaba stock antes de descontar. Ejemplo real detectado: Faraón con 5 en stock, 2 pedidos de 10 c/u — el formulario dejaba cargar hasta 5 **por cada pedido**, resultando en -5 al guardar.

**Fix aplicado (backend):** dentro de la transacción de `store()`, `ProductVariant::lockForUpdate()->find(...)` + validar contra `Setting::allow_negative_stock` antes de cada `decrement()`, envuelto en `try/catch` que devuelve el error a Inertia en vez de un 500 sin control.

### 🆕 Bug #7 — Validación de stock en el formulario de Embarque era ciega entre líneas — ✅ RESUELTO Y PROBADO
`Shipments/Create.vue` — `getAvailableToSend()` calculaba el máximo de cada línea solo contra `detail.variant?.stock` (el número fijo que llegó del servidor al cargar la página), sin saber que otra línea del mismo formulario ya había "apartado" parte de ese mismo stock compartido (mismo producto/material en dos pedidos distintos).

**Fix aplicado (frontend):** `consumedByVariant` (computed) suma cuánto se ha cargado de cada `product_variant_id` entre todas las líneas del formulario; `getAvailableToSend()` resta eso antes de calcular el máximo de cada línea. El mensaje de aviso ahora distingue si el límite viene del pedido o del stock compartido con otro pedido.

**Nota de la sesión:** el primer intento de este fix usó `detail.variant_id`, campo que no existe — la columna real es `product_variant_id` (confirmado con Vue Devtools). Quedó corregido en la versión final.

### 🆕 Bug #8 — Filtro por cliente (Fase 2.5.1) nunca se ejecutaba — ✅ RESUELTO Y PROBADO
`ShipmentController::create()` no recibía `Request $request` en su firma, así que `$clientIds` no existía en ningún lado. `if (!empty($clientIds))` no truena en PHP (variable indefinida se trata como vacía), así que el filtro simplemente nunca se activaba — sin ningún error visible. Fix: agregar `Request $request` a la firma y `$clientIds = $request->input('client_ids', [])`.

### 🆕 Bug #9 (rendimiento) — Firma del cliente y precios viajaban sin usarse en varias pantallas de Embarques — ✅ RESUELTO
`create()`, `index()` y `show()` de `ShipmentController` cargaban el modelo `Sale` completo (incluida `signature`, un base64 que puede pesar varios KB por venta) y precios (`unit_price`, `subtotal`, `total`) sin que ninguna de esas vistas los usara. Se restringieron las columnas con `select()` explícito en los tres métodos. **Se dejó `printManifest()` intacta a propósito** — ahí sí se necesitan los precios completos para la remisión que firma el cliente; ese PDF nunca viaja como JSON al navegador, así que no aplica el mismo riesgo de exposición de datos.

**Regla adoptada para el resto del proyecto:** cualquier `with('sale')` o `with('client')` sin `select()` explícito es sospechoso por default — hay que verificar primero qué usa el `.vue` real antes de decidir qué columnas traer. Pendiente aplicar esta misma revisión a `ProductionController` y otros lugares que toquen `Sale` (no se ha hecho todavía).

### 📌 Decisiones pendientes, discutidas pero no implementadas todavía
- **¿Cuándo pasa un pedido a `stage = 'enviado'`?** Se acordó que debería ser automático al crear el primer Embarque (no manual desde el Kanban), y que `confirmDelivery()` solo debería marcar `entregado` cuando **todas** las líneas del pedido estén completas (hoy revisa la línea individual, no el pedido completo — es un bug adicional detectado en conversación, aún no implementado).
- **Fecha compromiso editable al confirmar pedido / mandar a producción** (no solo al crear) — solicitado por el cliente, pendiente de implementar, se planeó hacerlo junto con el Bug #2 por tocar el mismo método.
- **Dashboard de avance por usuario** — anotado para Fase 3, no urgente.
- **Columna "Ya Embarcado" en el reporte de Producción** — mejora de UX sugerida por el cliente al ver el reporte agrupado; no urgente, el badge de 4 estados ya resuelve la confusión principal.

---

## Auditoría original (código sin ejecutar, referencia histórica — ver estado real arriba)

Los siguientes 5 bugs fueron detectados en la primera auditoría del zip compartido. La sección de arriba ("Sesión de Implementación en Vivo") es la fuente de verdad sobre qué se corrigió realmente en el proyecto — esta sección se conserva como referencia del análisis original.

### 🔴 #1 — Plan de Producción no descuenta correctamente lo ya fabricado tras un envío parcial — ✅ CONFIRMADO Y CORREGIDO EN VIVO (ver arriba)

### 🔴 #2 — Doble mecanismo de descuento de stock (Kanban vs. Embarques) — ⏳ PENDIENTE
`SaleController::updateStage()` sigue restando/regresando stock al mover una venta a `enviado`/`cancelado`, en paralelo a `ShipmentController::store()`. Nada impide que ambos se disparen sobre el mismo pedido y se reste el doble.

**Qué hacer:**
- [ ] Decidir un único camino de salida de stock (recomendado: solo Embarques, ya que soporta envíos parciales reales).
- [ ] Quitar el descuento/retorno de stock de `updateStage()` — dejar que ese método solo cambie el `stage` y deje el historial (que ya hace vía Observer).
- [ ] Implementar la transición automática a `enviado` al crear el primer Embarque del pedido (decisión ya tomada, ver sección de arriba).
- [ ] Corregir `confirmDelivery()` para que revise **todas** las líneas del pedido antes de marcarlo `entregado`, no solo la línea que se está entregando en ese momento (bug adicional detectado, no en la auditoría original).

### 🔴 #3 — `storeDelivery()` está roto (columnas inexistentes) — ⏳ PENDIENTE
`SaleController::storeDelivery()` (ruta `sales.deliveries.store`) intenta guardar `user_id` y `delivered_at` en `sale_deliveries`, columnas que no existen en esa tabla (solo tiene `shipment_id`, `sale_detail_id`, `quantity_delivered`). Cualquier llamada a este endpoint truena.

**Qué hacer:**
- [ ] Confirmar si este método sigue usándose desde algún botón en el frontend (parece remanente pre-Embarques v2.6).
- [ ] Si ya no se usa: eliminar el método y su ruta.
- [ ] Si se sigue usando: decidir si debe redirigir a crear un `shipment` de una sola línea, o agregar las columnas faltantes a `sale_deliveries`.

### 🟠 #4 — Rutas de Embarques sin restricción de rol — ⏳ PENDIENTE
`/shipments/*` no está dentro de ningún `role:...` — cualquier usuario autenticado puede crear/confirmar embarques hoy.

**Qué hacer:**
- [ ] Envolver el grupo de rutas de Embarques en `role:admin,inventario` (según la matriz de roles definida en `CONTEXTO_TECNICO.md` sección 9).

### 🟡 #5 — Falta cancelación de embarque — ⏳ PENDIENTE
Documentado como regla de negocio ("si se cancela un embarque antes de confirmar entrega, el stock regresa") pero no implementado — no hay método ni ruta.

**Qué hacer:**
- [ ] Agregar `shipments.cancel` (ruta + método), transaccional: regresa stock de cada `sale_delivery` del embarque, marca el embarque como `cancelado`, deja rastro en `sale_histories`.

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

### 2.1 Plan de Producción Semanal — ✅ COMPLETADO
`ProductionController@index` ya filtra por `promised_date` con `startWeek`/`endWeek`, incluyendo atrasados y sin fecha. Falta solo:
- [ ] Confirmar en `Production/Index.vue` si ya existen los botones "← Semana anterior" / "Semana siguiente →".
- [ ] Agregar toggle "Ver todo acumulado" si no existe.

### 2.2 Registro de Piezas Terminadas ✅ COMPLETADO Y CORREGIDO (ver Bug #1 arriba)

### 2.3 Envíos Parciales ✅ COMPLETADO (con bug — ver #3 arriba, método legado roto, aún pendiente)

### 2.4 Embarques como Entidad Propia ✅ COMPLETADO (bugs #6, #7, #8, #9 corregidos hoy; #2, #4, #5 aún pendientes)

### 2.5 Mejoras al módulo de Embarques

**2.5.1 — Filtro por cliente al armar embarque — ✅ CORREGIDO (ver Bug #8 arriba)**
El controlador ya soporta `client_ids[]`. Pendiente: agregar el selector multi-cliente en `Shipments/Create.vue` (el backend está listo, falta la UI para elegirlos).

**2.5.2 — Notas de entrega individuales por pedido dentro de un mismo embarque — parcialmente implementado**
`ShipmentController::printManifest()` ya agrupa las entregas por pedido/cliente. Falta fusionar la plantilla `shipment_manifest.blade.php` real (no compartida en la auditoría) con la lógica de agrupación — hay una versión de referencia (`reference_shipment_manifest.blade.php`) para integrar.

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