# 📋 BACKLOG — TALLER 360
**Auditado directamente contra el código fuente (zip del proyecto):** 25 de julio 2026
**Nota importante:** las versiones anteriores de este archivo (`README.md`, `GUIA_RUTA.md` y este mismo `BACKLOG.md`) se contradecían entre sí sobre qué bugs estaban resueltos. Esta versión es la única verificada línea por línea contra el código real — es la nueva fuente de verdad.

---

## 🎯 SPRINT ACORDADO CON CLIENTE — 04 de agosto 2026

Estos 5 puntos salieron de una reunión con el cliente. Todos tienen diseño técnico ya acordado (ver `CONTEXTO_TECNICO.md` sección 0.1 para el detalle completo de schema/lógica). Van en orden de prioridad sugerido.

### 1. 🟢 Envío automático de nota de venta al crear pedido (rápido, bajo riesgo)
- [ ] Extraer lógica de `SaleController::sendEmail()` a método privado reutilizable.
- [ ] Nuevo `Setting`: `auto_email_on_sale` (boolean, default `true`).
- [ ] Llamar el envío al final de `store()`, fuera de la transacción de BD, sin bloquear la creación del pedido si el correo falla.
- [ ] Agregar `auto_email_on_sale` a las claves permitidas en `SettingController`.
- [ ] El botón manual de reenvío en `Sales/Index.vue` se queda intacto, funciona sin importar el estado del interruptor.

### 2. 🟢 Supervisor con permisos completos en Producción, Almacén y Embarques
- [ ] Agregar `supervisor` a `role:admin,produccion` (rutas de Producción) y `role:admin,inventario` (rutas de Embarques) en `routes/web.php`.
- [ ] Dar acceso de escritura a Supervisor en Productos/Inventario (hoy sin ninguna ruta).
- [ ] Confirmar que ninguna vista (`Production/Index.vue`, `Shipments/*.vue`, `Products/*.vue`) tenga un `v-if` que excluya explícitamente a Supervisor de botones de acción — hoy no debería haber ninguno porque Supervisor no tenía acceso en absoluto, pero revisar por si acaso.
- [ ] Fuera de alcance por ahora: Ventas/Kanban y Configuración.

### 3. 🟡 Bug — pedido "entregado" cancelado cae en limbo
- [ ] En `ShipmentController::cancel()`, reemplazar la lectura de `SaleHistory::latest()->from_stage` por un recálculo en vivo: si quedan piezas sin entregar tras la cancelación, la etapa siempre vuelve a `producción`.
- [ ] Quitar el `SaleHistory::create()` manual y duplicado en `store()` — dejar que `SaleObserver` sea la única fuente de verdad.
- [ ] Probar el escenario exacto que reportó el cliente: envío completo → confirmar entrega (llega a `entregado`) → cancelar embarque → confirmar que el pedido reaparece visible en Producción o Kanban según corresponda, no en limbo.

### 4. 🟡 Stock mínimo por variante, solo para productos preferentes
- [ ] Migración: `product_variants.min_stock` (int, nullable).
- [ ] Formulario de variantes (`Products/Create.vue` / `Edit.vue`): mostrar el campo `min_stock` solo cuando el producto padre tiene `is_favorite = true`.
- [ ] `DashboardController`: cambiar `stock <= 5` fijo por `stock <= COALESCE(min_stock, 5)`.

### 5. 🟠 Órdenes de Trabajo — producción sin pedido + pausa de remanentes parciales
*(el más grande de los 5, tocar al final una vez los otros 4 estén probados)*
- [ ] Migración: nueva tabla `work_orders` (`product_variant_id`, `quantity_requested`, `target_date` nullable, `status` abierta/cerrada, `origin_sale_detail_id` nullable, `notes`, `created_by`, timestamps).
- [ ] Migración: `sale_details.production_hold` (boolean, default `false`).
- [ ] Migración: hacer `production_completions.sale_detail_id` nullable + agregar `production_completions.work_order_id` (nullable) — una fila pertenece a una fuente u otra, nunca ambas.
- [ ] `ProductionController::index()`: unir necesidades de `sale_details` (como hoy) + `work_orders` abiertas en la misma cola.
- [ ] `ProductionController::index()`: excluir de "pendiente de fabricar" las líneas con `production_hold = true`; mostrarlas en una sección aparte ("en espera de decisión").
- [ ] Lógica para activar `production_hold = true` automáticamente cuando un envío parcial deja remanente sin fabricar en una línea (`ShipmentController::store()`).
- [ ] Botón "Liberar a producción" en `Production/Index.vue` — visible para cualquier rol con acceso a Producción (`admin`, `produccion`, `supervisor` tras el punto 2) — apaga el flag y/o genera un `work_order` con `origin_sale_detail_id`.
- [ ] UI nueva: formulario para crear `work_order` standalone (variante + cantidad + fecha objetivo opcional + notas) y para "cerrar" una orden registrando lo fabricado.

---

Confirmado con el archivo real: CRUD completo, `VALID_ROLES` coincide exactamente con los 6 roles del sistema, protección contra auto-eliminación de la propia cuenta. Sin pendientes.

## 🆘 NUEVO — Conflicto de versiones de Tailwind CSS

`package.json` tiene `tailwindcss ^3.2.1` (activo, confirmado por la sintaxis `@tailwind base/components/utilities` en `app.css`) **y** `@tailwindcss/vite ^4.0.0` instalado pero sin registrar en `vite.config.js`. No rompe el build hoy, pero es una dependencia muerta que puede confundir a quien retome el proyecto.

- [ ] Decidir: ¿se intentó migrar a Tailwind v4 y se abandonó, o fue un `npm install` accidental?
- [ ] Si se queda en v3: quitar `@tailwindcss/vite` de `package.json`.
- [ ] Si se quiere migrar a v4: reemplazar `@tailwind base/components/utilities` en `app.css` por `@import "tailwindcss";`, registrar el plugin en `vite.config.js`, y revisar si `@tailwindcss/forms` (v3) tiene equivalente compatible en v4.

---

## ✅ LOS 5 BUGS CRÍTICOS DE LA AUDITORÍA DE JULIO — CONFIRMADOS RESUELTOS EN CÓDIGO REAL

| # | Bug | Verificación |
|---|---|---|
| 1 | Plan de Producción no descontaba lo ya fabricado tras envío parcial | `ProductionController::index()` y `printReport()` usan `withSum('completions as completed_quantity', ...)`, fórmula ya no resta stock. ✅ |
| 2 | Doble mecanismo de descuento de stock (Kanban vs. Embarques) | `SaleController::updateStage()` solo acepta `pedido,confirmado,produccion,cancelado`, no toca stock. `ShipmentController::store()` es el único que descuenta, con `lockForUpdate()`. ✅ |
| 3 | `sales.deliveries.store` roto (columnas inexistentes) | Método, ruta y botón eliminados del código. ✅ |
| 4 | Rutas de Embarques sin restricción de rol | `role:admin,inventario` confirmado en `routes/web.php`. ✅ |
| 5 | Sin forma de cancelar un embarque | `ShipmentController::cancel()` implementado con las reglas de negocio (flota propia vs. recolección en mostrador) respetadas. ✅ |

No quedan huecos pendientes de estos 5 — a diferencia de lo que decía `GUIA_RUTA.md` previamente (que marcaba #2, #3, #4 y #5 como "siguen pendientes"), el código confirma que ya no lo están.

---

## ✅ COMPLETADO Y CONFIRMADO EN CÓDIGO (además de los 5 bugs)

### Backend
- [x] Autenticación Laravel, roles (6), CRUDs base (Clientes, Productos).
- [x] POS completo, motor de etapas (Kanban), auto-confirmación y pagos parciales.
- [x] Descuento de stock unificado — Embarques es el único mecanismo, confirmado.
- [x] PDFs (ticket, nota, remisión de embarque) y envío de correos.
- [x] Historial automático vía `SaleObserver`.
- [x] `production_completions`, `sale_deliveries`, `shipments` — confirmadas en migraciones.
- [x] `ShipmentController` con lógica transaccional y `lockForUpdate()` real contra condiciones de carrera entre líneas del mismo formulario o usuarios concurrentes.
- [x] `CheckRole.php` con parámetros variádicos. Confirmado correcto.
- [x] `AuthenticatedSessionController` con `redirect()->away()` en login y logout.
- [x] Filtro semanal en Plan de Producción — confirmado en `ProductionController@index`, con navegación "« Ant." / "Sig. »" en `Production/Index.vue`.
- [x] **`pickup_type` (recolección en mostrador vs. flota propia) — implementado completo, backend Y frontend.** Corrección importante: la versión anterior de este documento lo marcaba como "backend listo, falta selector en frontend" — el selector ya existe y funciona (toggle visual en `Shipments/Create.vue`).
- [x] Filtro `client_ids[]` soportado en `ShipmentController::create()` (falta la UI, ver pendientes).
- [x] Validación de stock compartido entre líneas del mismo formulario de embarque (evita inventario negativo cuando dos pedidos compiten por el mismo producto).
- [x] Exclusión de embarques cancelados en los 3 `withSum` de entregas (`ShipmentController::create()`, `SaleController::show()`, `closeOrderIfComplete()`).

### Frontend
- [x] Dashboards (admin, vendedor, pantalla bienvenida para otros roles).
- [x] POS, Kanban, Detalle Híbrido (Oficina/Taller), Plan de Producción.
- [x] CRUD Clientes, Productos (Create + Edit + Index).
- [x] Settings con preview de logo.
- [x] `Shipments/Index.vue` — lista, imprimir, confirmar, **cancelar** (badge de 3 estados).
- [x] `Shipments/Create.vue` — armar embarque, validación de stock compartido, toggle flota propia/recolección en mostrador.
- [x] `Shipments/Show.vue` — detalle con `chosen_color`, fechas y piezas a bordo.
- [x] Badge de 4 estados en `Production/Index.vue` (fabricar todo / parcial / listo para embarcar / fabricado y ya embarcado).
- [x] `formatDate` sin usar en `Production/Index.vue` — **ya no existe en el código, este bug quedó resuelto** (no estaba documentado como resuelto en versiones previas).

---

## ⚠️ CONFIRMADO PENDIENTE (verificado contra código, no solo contra reportes previos)

### Embarques
- [ ] **Selector multi-cliente en `Shipments/Create.vue`.** El backend ya soporta `client_ids[]` (confirmado en `ShipmentController::create()`), pero no existe ningún input/select en el `.vue` que lo use.
- [ ] **Notas de entrega agrupadas por pedido/cliente.** Corrección importante: la versión anterior de este documento decía que `printManifest()` "ya agrupa las entregas por pedido/cliente" con una plantilla parcial. **Confirmado falso** — tanto el controlador como `shipment_manifest.blade.php` hacen un `@foreach` plano sobre todas las entregas del viaje, sin ninguna agrupación. Si un viaje mezcla pedidos de distintos clientes, la remisión los mezcla todos en una sola lista. Hay que construir la agrupación desde cero, no "fusionar con una versión de referencia" como decía el plan anterior (no se encontró tal archivo de referencia en el código).

### Producción
- [ ] Toggle "Ver todo acumulado" en `Production/Index.vue` — no existe en el código (los filtros actuales son "todos/embarque/fabricar" dentro de la semana seleccionada, no un acumulado histórico).

### Ventas
- [ ] **Fecha compromiso (`promised_date`) editable después de creado el pedido.** Confirmado: solo se captura en `SaleController::store()`. Ni `updateStage()` ni ningún otro método permite corregirla después.
- [ ] Forzar Modo Taller (sin precios) automáticamente por rol en el backend. Confirmado: `Sales/Show.vue` sigue dependiendo 100% del query param `?production=` — no hay lógica en `SaleController::show()` que lo fuerce para `supervisor`, `inventario` o `produccion`.

### Rendimiento (hosting compartido)
- [ ] `ProductController::index()` — `Product::with(['category','variants'])->get()` sin `select()` ni paginación. Carga todos los productos, todas sus variantes y los 5 precios de cada una en cada visita al catálogo interno.
- [ ] `SaleController::create()` (POS) — `Client::all()` sin límite ni `select()` explícito, se ejecuta en cada apertura del POS.
- [ ] Revisar índices en BD: `sale_details.sale_id`, `sale_details.product_variant_id`, `sale_deliveries.shipment_id`, `sale_deliveries.sale_detail_id`, `sales.stage` + `sales.promised_date` — no se pudo confirmar sin acceso al schema real de la BD (las migraciones no muestran índices explícitos más allá de las FKs).
- [ ] `composer.lock`/`package-lock.json` — no se auditaron dependencias de desarrollo innecesarias en producción en esta ronda.

### Roles y Fase 3 (confirmado: sin empezar en código)
- [ ] `supervisor` y `financiero` no tienen ninguna ruta asignada — caen directo a pantalla de bienvenida.
- [ ] Dashboard de Producción (Fase 3.1).
- [ ] Dashboard/Módulo Financiero y estado de cuenta por cliente (Fase 3.2) — sigue siendo solo diseño, sin controlador ni ruta.
- [ ] Selector de vista para Admin (Fase 3.3).
- [ ] Sidebar y rutas condicionales completas por rol (Fase 3.4).

---

## 🟢 FASE 4 — Catálogo Público y Link por Cliente

> **Confirmado en código:** la ruta `/` devuelve `view('catalogo.index')`, una plantilla Blade **estática con datos hardcodeados** (categorías fijas, un solo producto de ejemplo). No consulta ninguna tabla real. No hay avance funcional de esta fase más allá del mockup visual — construir desde cero cuando se retome.

### 4.1 Catálogo Público (sin precios)
- [ ] Conectar la vista a `products`, `categories`, `product_variants` reales (hoy es 100% estático).
- [ ] Rutas públicas: `/catalogo`, `/catalogo/{categoria}`, `/catalogo/producto/{id}` — no existen todavía.
- [ ] Nunca exponer `price_1..price_5`.

### 4.2 Link Personalizado por Cliente (con precios)
- [ ] Campo `catalog_token` (UUID) en `clients` — confirmado que no existe.
- [ ] Ruta `/catalogo/cliente/{token}` con precios según `price_tier`.
- [ ] Botón "Copiar link" en `Clients/Index.vue`.

---

## 🔵 FASE 5 — Precios Dinámicos por Flete (Diseño cerrado, sin construcción)

- [ ] Campo `unidades_por_flete` en `products` o `product_variants`.
- [ ] Tabla `distance_zones` (km_min, km_max, costo_base_flete).
- [ ] Campo `distance_km` en `clients`.
- [ ] Precio final: `precio_base + (costo_flete_zona / unidades_por_flete)`.
- [ ] Pendiente decidir: automatización con Google Maps Distance Matrix API.

---

## 🟣 FASE 6 — Reportes PDF (Actividad Final)

- [ ] Reporte financiero (ingresos por rango, estado de cuenta por cliente).
- [ ] Reporte de cartera vencida.
- [ ] Reporte de producción semanal en PDF (ya existe `printReport()`, ya corregido — ver Bug #1).
- [ ] Reporte de embarques histórico.
- [ ] Notas de entrega individuales por pedido (comparte plantilla con la agrupación pendiente de Embarques, arriba).

---

## 🐛 BUGS CONOCIDOS

| # | Bug | Estado |
|---|-----|--------|
| 1 | Input de moneda en Safari/iOS — permite caracteres no numéricos | No se revisó en esta auditoría (requiere prueba manual en Safari) |
| 2 | `formatDate` sin usar en `Production/Index.vue` | ✅ Ya no existe en el código — resuelto |

---

## 📌 Orden de Dependencias (actualizado 25 jul 2026)

```
Fase 0 ✅ → Fase 1 ✅ → Fase 2 (95%, filtro semanal + navegación de semana confirmados)
                                     ↓
                    ✅ Los 5 Bugs Críticos — CONFIRMADOS RESUELTOS en código real
                    ✅ UserController — confirmado completo (era omisión del zip)
                                     ↓
                    Fase 2.5 — Selector multi-cliente + notas de entrega agrupadas
                    (ambos confirmados pendientes, no solo "detalle menor")
                                     ↓
                    Optimización de consultas (ProductController, SaleController::create)
                                     ↓
                              Fase 3 (Dashboards) — confirmado sin empezar en código
                                     ↓
                    Fase 4 (Catálogo) — confirmado: hoy es mockup estático, empezar desde cero
                                     ↓
                    Fase 5 (Flete) — depende de 4.2 (link personalizado)
                                     ↓
                    Fase 6 (Reportes PDF) — depende de Fase 3
```
