# 📋 BACKLOG — TALLER 360
**Auditado directamente contra el código fuente:** 05 de septiembre 2026 — **Actualización documental:** 29 de octubre 2026 (incorporación de Fase 2.7 Catálogos Administrables y Fase 2.8 Devoluciones como *Planeado, sin construir*, corrección de contradicciones y alineación de dependencias)
**Nota importante:** las versiones anteriores de este archivo (`README.md`, `GUIA_RUTA.md` y este mismo `BACKLOG.md`) se contradecían entre sí sobre qué bugs estaban resueltos. Esta versión es la única verificada línea por línea contra el código real — es la nueva fuente de verdad.

---

## 🔥 SPRINT ACTIVO — OCTUBRE 2026 (MÁXIMA PRIORIDAD EN PRODUCCIÓN - COMPLETADO ✅)

### 1. 🛠️ Órdenes de Trabajo en Plan de Producción: Fabricación Autónoma sin Validación de Stock
- [x] Backend: Refactorizar `ProductionController::index` para desacoplar el cálculo de piezas faltantes entre pedidos de venta (`sale_details`) y órdenes de trabajo (`work_orders`).
- [x] Backend: Faltantes de pedidos $\max(0, \text{pedidos} - \text{stock\_disponible} - \text{detallado} - \text{enviados})$; Faltantes de OT $\max(0, \text{solicitado\_OT} - \text{completado\_OT})$.
- [x] Backend: `pending_to_fabricate` resultante = suma de faltantes de pedidos + faltantes de OT.
- [x] Frontend: En `Production/Index.vue`, habilitar de forma permanente la celda de captura numérica y botón `[✓]` para órdenes de trabajo mientras tengan piezas pendientes, sin importar si los pedidos del modelo ya están cubiertos por stock.
- [x] Frontend: Añadir estado visual informativo ("Cubierto por stock / OT en curso") cuando los pedidos no requieran fabricación pero la OT sí.
- [x] Pruebas: Feature test `WorkOrderLogicTest::test_work_order_pending_calculation_is_independent_from_existing_stock` que verifique que una OT puede registrar entradas al stock físico aún existiendo inventario previo.

### 2. 📦 Control Formal de Piezas Dañadas e Historial de Auditoría en Inventario
- [x] Base de Datos: Migración para añadir columna `product_variants.damaged_stock` (`unsignedInteger`, default 0).
- [x] Base de Datos: Migración para crear tabla de auditoría `damaged_stock_logs` (`id`, `product_variant_id`, `user_id`, `type`, `quantity`, `notes`, `timestamps`).
- [x] Backend: Modelo `DamagedStockLog` con relaciones a `ProductVariant` y `User`.
- [x] Backend: Actualizar accesor `ProductVariant::getAvailableStockAttribute` restando `damaged_stock` (`stock - reserved_stock - damaged_stock`).
- [x] Backend: Controlador `DamagedStockController@store` con transacciones atómicas y `lockForUpdate` soportando:
  - `marcar_danada` (Disponible $\to$ Dañada).
  - `reparada` (Dañada $\to$ Disponible).
  - `baja_definitiva` (Merma total: descuenta de `damaged_stock` y `stock` físico).
- [x] Backend: Registrar ruta segura `POST /damaged-stock` con middleware de roles (`admin`, `supervisor`).
- [x] Frontend: En `Products/Index.vue`, mostrar desglose claro por variante: Físico | Dañadas | Disponible.
- [x] Frontend: Modal de "Control de Daños" en `Products/Index.vue` con formulario para registrar movimientos validados y tabla con historial cronológico de auditoría (quién, cuándo, tipo, cantidad, notas).
- [x] Pruebas: Feature test `DamagedStockTest` validando transiciones, restricciones de inventario e integridad del historial.

### 3. 🎨 Control de Existencias Físicas por Color (Melamina - Colores de Fábrica) [COMPLETADO ✅ - Octubre 2026]
- [x] Base de Datos: Migración `2026_10_13_160000_create_variant_color_stocks_table.php` para crear tabla `variant_color_stocks` (`id`, `product_variant_id`, `color`, `stock`, `damaged_stock`, `reserved_stock`, `timestamps`, índice único `['product_variant_id', 'color']`).
- [x] Base de Datos: Migración `2026_10_13_160500_add_color_to_work_orders_table.php` para añadir columna `color` (nullable) a `work_orders`.
- [x] Backend: Modelo `VariantColorStock` con relación `belongsTo(ProductVariant::class)` y accesor `available_stock`.
- [x] Backend: Relación `hasMany(VariantColorStock::class, 'colorStocks')` en `ProductVariant`.
- [x] Backend: Sincronización y persistencia de existencias por color en `ProductController@store` y `update`, sincronizando automáticamente `product_variants.stock` como la suma de colores.
- [x] Backend: En `ProductionController@storeCompletion`, registrar incremento al color específico (del pedido o de la OT).
- [x] Backend: En `ProductionController@index` y `@printReport`, cálculo inteligente de faltantes evaluado color por color para Melamina (`color_breakdown`), impidiendo que el stock de un color cubra la demanda de otro color faltante.
- [x] Backend: En `ShipmentController@store`, validar disponibilidad atómica por color (`lockForUpdate`) y descontar del color exacto solicitado.
- [x] Backend: En `ShipmentController@cancel`, restituir el stock al color exacto en `variant_color_stocks`.
- [x] Frontend: `Products/Create.vue` y `Edit.vue` exclusivos para Melamina ~~con los 4 colores oficiales de fábrica (`Blanco`, `Gris`, `Texturizado`, `Oyamel`)~~ *(Nota: Los 4 colores fueron un error de desarrollo a subsanar en Fase 0 Hotfix; los 7 oficiales acordados son: Fresno andino, Parota, Nogal africano, Gris cenizo, Gris antracita, Tzalam, Moka)*, cálculo automático de Stock Total y sin opciones de crear o borrar colores arbitrarios.
- [x] Frontend: `Products/Index.vue` con desglose visual de existencias por color (badges contrastados de piezas disponibles).
- [x] Frontend: `Production/Index.vue` con selector de color en la modal de Orden de Trabajo, desglose visual de existencias por color, chips de color en pedidos y órdenes (`🎨 [Color]`) y alerta de faltantes por color.
- [x] Frontend: `Production/Print.vue` con reporte para carpinteros desglosando existencias y necesidades por color, con instrucción directa de corte en notas (`[Fab: X Color]`).
- [x] Frontend: `Shipments/Create.vue` con indicador visual de existencias en el color solicitado y restricción del máximo despachable.
- [x] Pruebas: Feature test `VariantColorStockTest` validando inventario por color, producción, bloqueo en embarques, reversión en cancelaciones y cálculo inteligente en la cola de producción.

---

## 🎯 SPRINT ACORDADO CON CLIENTE — 04 de agosto 2026

Estos 5 puntos salieron de una reunión con el cliente. Todos tienen diseño técnico ya acordado (ver `CONTEXTO_TECNICO.md` sección 0.1 para el detalle completo de schema/lógica). Van en orden de prioridad sugerido.

### 1. 🟢 Envío automático de nota de venta al crear pedido (rápido, bajo riesgo)
- [x] Extraer lógica de `SaleController::sendEmail()` a método privado reutilizable.
- [x] Nuevo `Setting`: `auto_email_on_sale` (boolean, default `true`).
- [x] Llamar el envío al final de `store()`, fuera de la transacción de BD, sin bloquear la creación del pedido si el correo falla.
- [x] Agregar `auto_email_on_sale` a las claves permitidas en `SettingController`.
- [x] El botón manual de reenvío en `Sales/Index.vue` se queda intacto, funciona sin importar el estado del interruptor.

### 2. 🟢 Supervisor con permisos completos en Producción, Almacén y Embarques
- [x] Agregar `supervisor` a `role:admin,produccion` (rutas de Producción) y `role:admin,inventario` (rutas de Embarques) en `routes/web.php`.
- [x] Dar acceso de escritura a Supervisor en Productos/Inventario (hoy sin ninguna ruta).
- [x] Confirmar que ninguna vista (`Production/Index.vue`, `Shipments/*.vue`, `Products/*.vue`) tenga un `v-if` que excluya explícitamente a Supervisor de botones de acción — hoy no debería haber ninguno porque Supervisor no tenía acceso en absoluto, pero revisar por si acaso.
- [ ] Fuera de alcance por ahora: Ventas/Kanban y Configuración.

### 3. 🟡 Bug — pedido "entregado" cancelado cae en limbo
- [x] En `ShipmentController::cancel()`, eliminar la lectura del historial de etapa.
- [x] Al regresar las piezas al cancelar, verificar matemáticamente si faltan piezas en alguna partida de ese pedido; si es así, setear el `stage` dinámicamente a `produccion`.
- [x] En `ShipmentController::store()`, eliminar la creación de `SaleHistory` duplicada.

### 4. 🟢 Stock mínimo por variante, solo para productos preferentes (COMPLETADO)
- [x] Migración: `product_variants.min_stock` (int, nullable).
- [x] Formulario de variantes (`Products/Create.vue` / `Edit.vue`): mostrar el campo `min_stock` solo cuando el producto padre tiene `is_favorite = true`.
- [x] `DashboardController`: cambiar `stock <= 5` fijo por `stock <= COALESCE(min_stock, 5)`. Adicionalmente, se configuró para mostrar *siempre* los productos favoritos y colorearlos dinámicamente.

### 5. 🟢 Órdenes de Trabajo — producción sin pedido + pausa de remanentes parciales (COMPLETADO)
- [x] Migración: nueva tabla `work_orders` (`product_variant_id`, `quantity_requested`, `target_date` nullable, status: ~~`status` abierta/cerrada~~ ✅ Corregido 29 oct 2026: valores reales en código y migración: 'pending'|'in_progress'|'completed', default:'pending', `origin_sale_detail_id` nullable, `notes`, `created_by`, timestamps).
- [x] Migración: `sale_details.production_hold` (boolean, default `false`).
- [x] Migración: hacer `production_completions.sale_detail_id` nullable + agregar `production_completions.work_order_id` (nullable).
- [x] `ProductionController::index()`: unir necesidades de `sale_details` + `work_orders` pendientes o en proceso (`whereIn('status', ['pending', 'in_progress'])`) en la misma cola.
- [x] `ProductionController::index()`: excluir de "pendiente de fabricar" las líneas con `production_hold = true`; mostrarlas en una sección aparte ("en espera de decisión").
- [x] Lógica para activar `production_hold = true` automáticamente cuando un envío parcial deja remanente.
- [x] Botón "Liberar a producción" en `Production/Index.vue` para apagar el flag y/o generar `work_order`.
- [x] UI nueva: formulario (Combobox autocompletable) para crear `work_order` standalone y para "cerrar" una orden registrando lo fabricado.

---

Confirmado con el archivo real: CRUD completo, `VALID_ROLES` coincide exactamente con los 6 roles del sistema, protección contra auto-eliminación de la propia cuenta. Sin pendientes.

## ✅ Tailwind CSS v4 Migración Completada (QA Aprobado - Sep 2026)
El proyecto ahora funciona 100% sobre Tailwind CSS v4. Se eliminaron las dependencias legacy de PostCSS y Autoprefixer, y se reestructuró `app.css` usando `@import "tailwindcss"` y `@theme`. Adicionalmente, se resolvieron las regresiones visuales:
- **Modales (Z-Index):** Ajuste de apilamiento en `Modal.vue` para que el contenido no quede oculto detrás del overlay.
- **Bordes (currentColor):** Restaurados los bordes grises (`border-color: var(--color-gray-200)`) globalmente para corregir las líneas negras severas en tablas CRUD.
- **Opacidades Legacy:** Limpieza de clases obsoletas (`bg-opacity-*` reemplazadas por sintaxis moderna `bg-color/opacity`).
- **Rendimiento de Imágenes:** Implementado `loading="lazy" decoding="async"` y ocultamiento del texto ALT `color: transparent` para evitar parpadeos visuales al abrir el catálogo público.

---

## ✅ LOS 5 BUGS CRÍTICOS DE LA AUDITORÍA DE JULIO — CONFIRMADOS RESUELTOS EN CÓDIGO REAL

| # | Bug | Verificación |
|---|---|---|
| 1 | Plan de Producción no descontaba lo ya fabricado tras envío parcial | `ProductionController::index()` utiliza la fórmula unificada: `max(0, Requerimientos - StockFisico - EnDetallado - Enviados)`. Se reparó la lógica de agrupación e interfaz UI mostrando "X (de Y totales)" para evitar sobreproducción. ✅ |
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
- [x] **Selector multi-cliente en `Shipments/Create.vue`.** Implementado y funcionando correctamente permitiendo enviar múltiples clientes al backend mediante el filtro `client_ids[]`.
- [x] **Notas de entrega agrupadas por pedido (Venta).** Se ha refactorizado `ShipmentController::printManifest` para utilizar `$deliveries->groupBy('saleDetail.sale_id')`, evitando la agrupación por cliente y tratando el formato como una "Nota de Pedido" exacta.
- [x] **Vista PDF (Diseño Corporativo en Remisiones).** Se construyó desde cero `shipment_manifest.blade.php` fusionando el formato CSS y HTML institucional de la "Nota de Venta" (incluyendo la inyección de la configuración de empresa y `$logoBase64`). Se calculan los importes/descuentos estrictamente basados en `$delivery->quantity_delivered`.

### Producción
- [x] Toggle "Ver todo acumulado" en `Production/Index.vue` — DESCARTADO. Operativamente, el control del equipo mediante la pausa de remanentes (`production_hold`) y los filtros semanales es suficiente.

### Ventas
- [x] **Fecha compromiso (`promised_date`) editable después de creado el pedido.** Completado y verificado. Al avanzar la etapa en el Kanban (ej: hacia `confirmado`), SweetAlert2 solicita/permite corregir la fecha compromiso.
- [x] Forzar Modo Taller (sin precios) automáticamente por rol en el backend. Completado. En `SaleController::show()`, los roles `produccion`, `inventario` y `supervisor` ahora asumen `is_production_mode = true` desde el servidor, y el sistema borra (`makeHidden`) toda la información financiera y precios unitarios del objeto de la base de datos antes de enviarlo al Vue.

### Rendimiento (hosting compartido) - MOVIDO AL FINAL (FASE 7)
- [ ] `ProductController::index()` — `Product::with(['category','variants'])->get()` sin `select()` ni paginación. Carga todos los productos, todas sus variantes y los 5 precios de cada una en cada visita al catálogo interno. *(Pospuesto por decisión del cliente)*.
- [ ] `SaleController::create()` (POS) — Carga todos los clientes. Ya tiene `select()` explícito pero falta límite. *(Pospuesto por decisión del cliente)*.
- [ ] Revisar índices en BD: `sale_details.sale_id`, `sale_details.product_variant_id`, `sale_deliveries.shipment_id`, `sale_deliveries.sale_detail_id`, `sales.stage` + `sales.promised_date` — no se pudo confirmar sin acceso al schema real de la BD.
- [ ] `composer.lock`/`package-lock.json` — auditar dependencias de desarrollo innecesarias en producción en esta ronda final.

### Roles y Fase 3 (Pausada / Posterior a Fases 2.7 y 2.8)
- [ ] ~~`supervisor` y~~ `financiero` no tiene ninguna ruta asignada — cae directo a pantalla de bienvenida (~~`supervisor`~~ ya tiene acceso operativo completo a Producción, Almacén y Embarques ✅ Corregido 29 oct 2026).
- [ ] Dashboard/Módulo Financiero y estado de cuenta por cliente (Fase 3.2) — ⏸️ **EN PAUSA** (esperando respuestas del cliente sobre crédito, anticipos y reglas de cobranza; no es prioridad actual).
- [ ] Dashboard de Producción (Fase 3.1).
- [ ] Selector de vista para Admin (Fase 3.3).
- [ ] Sidebar y rutas condicionales completas por rol (Fase 3.4).

---

## 🔴 FASE 0 — HOTFIX COLORES DE MELAMINA (PRIORIDAD MÁXIMA — EN ESPERA DE "PROCEDE HOTFIX")
**Prioridad:** 🔴 MÁXIMA (antes de la Fase 2.7) | **Estado:** Documentado, en espera de orden explícita "Procede hotfix" del PM  
**Motivo:** Los 4 colores hardcodeados de Melamina (`Blanco`, `Gris`, `Texturizado`, `Oyamel`) fueron un **error de desarrollo**, no colores de fábrica válidos. Los 7 colores oficiales acordados son: `Fresno andino`, `Parota`, `Nogal africano`, `Gris cenizo`, `Gris antracita`, `Tzalam`, `Moka`.  
**Regla técnica fundamental:** Este hotfix NO crea tablas nuevas ni columnas foráneas `color_id`. Corrige únicamente los valores hardcodeados en el código existente para alinear el sistema a la realidad operativa antes de acometer la Fase 2.7.

### Alcance Técnico de la Intervención (tras confirmación "Procede hotfix"):
1. `app/Http/Controllers/ProductionController.php`:
   - L119: Sustituir el array `['Blanco', 'Gris', 'Texturizado', 'Oyamel']` por los 7 oficiales (`['Fresno andino', 'Parota', 'Nogal africano', 'Gris cenizo', 'Gris antracita', 'Tzalam', 'Moka']`) en el cálculo de faltantes por color.
   - L459: Actualizar el array en `printReport()` para el reporte de producción impreso.
2. `resources/js/Pages/Production/Index.vue`:
   - L78: Actualizar el selector de color en la modal de Orden de Trabajo con los 7 oficiales.
   - L522-525: **Eliminar el default `'Blanco'`** en la asignación reactiva para evitar que pedidos u órdenes sin color se guarden erróneamente como Blanco.
3. `resources/js/Pages/Products/Create.vue`:
   - L46: Reemplazar la matriz fija de colores de Melamina por los 7 oficiales.
4. `resources/js/Pages/Products/Edit.vue`:
   - L25: Reemplazar la matriz fija de colores de Melamina por los 7 oficiales.
5. `resources/js/Pages/Sales/Create.vue`:
   - L36-41: Alinear la matriz de Melamina con los 7 oficiales; además, alinear MDF a 6 colores y Madera a 4 colores con los códigos HEX oficiales del Blade (`catalogo/index.blade.php` L437-442).
   - L45-49: **Ajuste crítico en `getColorsForMaterial`:** La función actual evalúa por coincidencia de subcadena (`materialName.includes(k)`). Como `"Madera y MDF enchapado"` contiene `"MDF"`, si se evalúa `'MDF'` antes que `'Madera'`, caería erróneamente en la paleta de MDF. Se debe verificar y asegurar que `"Madera y MDF enchapado"` NO caiga en la lista de MDF por error y utilice la paleta de Madera (`Chocolate`, `Caoba`, `Tabaco`, `Cherry`).

---

## 🔴 FASE 2.7 — SPRINT CATÁLOGOS ADMINISTRABLES (URGENTE — PLANEADO, SIN CONSTRUIR)
**Prioridad:** 🔴 MÁXIMA / URGENTE (1° en orden de ejecución tras Fase 0) | **Estimado:** 5 a 7 sesiones  
**Motivo:** Actualmente categorías, materiales por categoría y colores por material están hardcodeados en PHP, Vue y Blade. Los 4 colores originales de Melamina (`Blanco`, `Gris`, `Texturizado`, `Oyamel`) fueron un ~~error de desarrollo~~ que se subsana en la Fase 0 Hotfix (quedando los 7 oficiales: `Fresno andino`, `Parota`, `Nogal africano`, `Gris cenizo`, `Gris antracita`, `Tzalam`, `Moka`). No obstante, siguen fijos en código. Un proveedor descontinuó un color y agregó uno nuevo; el cliente necesita administrarlos dinámicamente sin tocar código.

### Checklist por Fases:
- [ ] **Fase 1: Auditoría y línea base:** Ejecutar la auditoría de lectura (ver tabla en `CONTEXTO_TECNICO.md` Anexo 0.3-X1) y correr `php artisan test`; los tests existentes deben estar en verde antes de empezar a escribir código.
- [ ] **Fase 2: BD, modelos y seeders:** Crear migraciones para tabla `materials` (4 confirmados: `MDF`, `Madera`, `Madera y MDF enchapado`, `Melamina`), `colors`, pivote `category_material`, pivote `material_color` (con campo `activo` para descontinuar por material), agregar `activo` a `categories`, migrar `product_variants.material` a `material_id`, migrar `color` a `color_id` en `variant_color_stocks` y `work_orders`, y agregar `color_id` nullable a `sale_details` conservando `chosen_color` como snapshot textual. Seeders con fuente de verdad oficial (A12): 15 colores con HEX de `catalogo/index.blade.php` L437-442; conteos pendientes de confirmar por el PM (14 o más relaciones categoría↔material; 17 o 21 relaciones material↔color).
- [ ] **Fase 3: Pantalla "Catálogos" (Admin + Supervisor):** Nueva sección propia "Catálogos" en el menú principal (`role:admin,supervisor`). CRUD de categorías, materiales y colores; asignación muchos a muchos categoría↔material y material↔color; descontinuar color por material (`material_color.activo`); interruptor global (`colors.activo`); candado de renombrado (libre si no se ha usado; solo corrección tipográfica con confirmación si ya tiene registros históricos vinculados).
- [ ] **Fase 4: Productos y POS leyendo de BD:** Formularios de productos (`Products/Create.vue` y `Edit.vue`) y POS (`Sales/Create.vue`) cargando catálogos reactivos desde base de datos. Validación estricta categoría-material en backend. Regla de "color especial" (capturado en nota de partida `custom_notes` con costo extra `additional_cost`, sin `color_id`). Melamina rechaza color especial: solo admite colores de línea activos. Ocultar productos de categoría o material desactivado en catálogo público y POS (visibles en inventario interno).
- [ ] **Fase 5: Producción, órdenes de trabajo y embarques con `color_id` y regla de descontinuados:** *(FASE DE MAYOR RIESGO — toca stock por color)*. `ProductionController::index`, `@printReport`, `@storeCompletion`, `WorkOrderController` y `ShipmentController` operando con `color_id`. Permitir embarque y cancelación de embarque con color descontinuado mientras quede stock físico; bloquear nueva venta y fabricación de colores descontinuados.
- [ ] **Fase 6: Catálogo público, reporte de taller y alertas:** Vista de catálogo público (`catalogo/index.blade.php`) leyendo materiales y colores desde BD. En `Production/Print.vue` y en la cola de producción (`Production/Index.vue`), las partidas con color especial deben mostrar la nota de forma visible (ej. *"Color especial: ver nota"*). Alerta en Dashboard y Productos: *"quedan X piezas de [Material] [Color] (descontinuado)"*.
- [ ] **Fase 7: Tests nuevos y regresión completa:** Crear tests automatizados de las reglas A1–A12 y verificar que las suites existentes sigan en verde.

### Tests Nuevos a Construir:
- [ ] Descontinuar un color en Melamina no lo bloquea en Madera ni en MDF.
- [ ] Color descontinuado no aparece en POS, catálogo público, creación de productos nuevos ni órdenes de trabajo.
- [ ] Embarcar y cancelar embarque con color descontinuado y stock existente funciona correctamente.
- [ ] Partida con color especial no toca `variant_color_stocks` ni falla validaciones de producción.
- [ ] Melamina rechaza venta con color especial (exige color de línea activo).
- [ ] Validación categoría-material rechaza variantes con material no asociado a la categoría.
- [ ] Todas las variantes existentes conservan categoría y material válidos tras la migración (incluyendo las 35 de Madera en Roperos y 11 en Cómodas, bajo regla firme A11).
- [ ] Producto de categoría o material desactivado se oculta en catálogo público y POS pero sigue visible en inventario interno.
- [ ] Renombrar color: libre si no se ha usado; restringido con confirmación si ya tiene uso histórico.
- [ ] Seeders reproducen exactamente las categorías, materiales, relaciones y colores acordados (15 colores del Blade; conteos pendientes de confirmar: 14 o más categoría↔material, 17 o 21 material↔color).
- [ ] Las suites existentes de tests siguen en verde tras cada fase.

### ⚠️ Dudas Abiertas de Catálogos (Decisiones Pendientes):
- **Materiales confirmados (4 materiales):** El PM confirmó que `MDF`, `Madera`, `Madera y MDF enchapado` y `Melamina` son **cuatro materiales distintos** en la tabla `materials`. Se resuelve y retira la duda sobre si eran uno o dos materiales.
- **Regla firme e inviolable A11:** Tras la migración, **todas las variantes existentes deben conservar categoría y material válidos** (la regla de negocio A11 no puede invalidar variantes preexistentes).
- **Dudas Abiertas Nuevas (Pendientes de Decisión del PM):**
  1. **Colores de "Madera y MDF enchapado":** ¿Comparte los mismos 4 colores de Madera (`Chocolate`, `Caoba`, `Tabaco`, `Cherry`) o tiene una lista propia de colores? (Define si el pivote `material_color` tiene 17 o 21 relaciones en el seeder).
  2. **46 variantes existentes de "Madera" en Roperos (35) y Cómodas (11):** La hoja oficial del PM no contempla "Madera" como material permitido para Roperos ni Cómodas. *Opción recomendada:* agregar "Madera" a esas dos categorías en `category_material` (aumentando las relaciones categoría↔material de 14 a 16). Decisión pendiente del PM; no tomarla por cuenta propia.
  3. **Categoría "Trincheros" vs "Trinchers":** En código y BD actual existe "Trincheros", mientras que en la hoja oficial del PM figura como "Trinchers". Pendiente confirmación tipográfica.

### Riesgos Técnicos:
- Migración de datos existentes de texto a IDs foráneos en stock por color (`variant_color_stocks`) y órdenes de trabajo (`work_orders`).
- Invalidación accidental de variantes existentes si el seeder de `category_material` no asocia Madera a Roperos y Cómodas.
- Pedidos históricos deben conservar snapshot textual del nombre y color vendido (`sale_details.chosen_color`).
- Colores hardcodeados olvidados en controladores o vistas Blade/Vue secundarias (se detectaron 3 matrices distintas en el código).
- Catálogo público en producción con caché activa (al desplegar en Neubox requerirá: `npm run build`, `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`).

---

## 🔴 FASE 2.8 — MÓDULO DE DEVOLUCIONES (URGENTE — PLANEADO, SIN CONSTRUIR)
**Prioridad:** 🔴 ALTA / URGENTE (2° en orden de ejecución; depende de Fase 2.7) | **Estimado:** 2 a 3 sesiones  
**Ubicación:** Submenú "Devoluciones" dentro del módulo de Embarques / Logística (`role:admin,inventario,supervisor`).  
**Dependencia:** Se construye **DESPUÉS de la fase 5 de la Fase 2.7** porque las devoluciones mueven stock por color y deben operar con `color_id`.

### Checklist por Fases:
- [ ] **Fase 1: Auditoría de lectura de "entregado":** Auditoría exhaustiva completada (ver tabla en `CONTEXTO_TECNICO.md` Anexo 0.3-X2) identificando todos los puntos donde se calcula "entregado" y deberán restar devoluciones (`ShipmentController`, `SaleController::show`, `ProductionController`, vistas Vue).
- [ ] **Fase 2: BD y modelos:** Migraciones para `sale_returns` (`id`, `folio`, `client_id`, `user_id`, `authorized_by`, `returned_at`, `notes`, `timestamps`) y `sale_return_items` (`id`, `sale_return_id`, `sale_detail_id`, `shipment_id` nullable, `quantity`, `reason_type`, `disposition`, `color_id` nullable, `chosen_color` snapshot, `notes`, `timestamps`). Modelos Eloquent y relaciones.
- [ ] **Fase 3: Controlador transaccional con `lockForUpdate` y Acción de Servicio:** Endpoint `POST /shipments/returns` siguiendo el patrón transaccional de `ShipmentController::store`. Validación: `cantidad <= entregado neto`. Destinos:
  - `danada`: Acción de servicio desacoplada (`DamagedStockService`) que en transacción con `lockForUpdate` suma simultáneamente a `product_variants.stock` Y `product_variants.damaged_stock` (y en Melamina a `variant_color_stocks.stock` y `damaged_stock` del `color_id` correspondiente) y genera el registro en `damaged_stock_logs`. El rol `inventario` tiene permiso para registrar devoluciones sin requerir ruta de `DamagedStockController`.
  - `baja`: Merma total sin reposición de stock físico.
  - `color_equivocado`: Suma al stock físico y disponible del color real indicado (obligatorio `color_id` en Melamina; opcional en otros).
  - **Bloqueo estricto de cancelación de viaje:** Si un embarque tiene devoluciones registradas, se bloquea su cancelación (`ShipmentController::cancel`) con mensaje explicativo claro; los ajustes deben gestionarse por el módulo de devoluciones.
  - **Recálculo dinámico a producción (sin WorkOrder automática):** La partida devuelta reaparece como faltante en el Plan Taller para que Producción decida cuándo fabricar. Para evitar que la fórmula `MAX(completed, detailed, delivered)` enmascare el faltante, se restan de `completed` las piezas con destino `danada` o `baja`. Si quedan faltantes netos en el pedido, su `stage` regresa a `produccion` (registrado limpiamente por `SaleObserver`, sin invocar `SaleHistory` manual).
- [ ] **Fase 4: UI de Devoluciones:** Submenú en Embarques con listado de devoluciones, modal/formulario de captura con selector de pedido/partida entregada, motivo, destino, color real si Melamina, campo de texto libre obligatorio "Autorizado por" (`authorized_by`) y notas. Desglose visible de devoluciones en el detalle del pedido (`Sales/Show.vue`).
- [ ] **Fase 5: Integración con producción y embarques:** Ajustar todos los `withSum` de entregas (`deliveries`) para restar devoluciones netas. Sin reenvío automático ni creación de WorkOrder automática: Producción decide en el Plan Taller cuándo se reenvía o repone. Bloqueo de cancelación en `ShipmentController::cancel` si existen devoluciones.
- [ ] **Fase 6: Tests y regresión completa:** Pruebas unitarias y de integración para los flujos de devolución y verificación de suites completas en verde.

### Tests Nuevos a Construir:
- [ ] Intento de devolución por cantidad mayor al entregado neto se rechaza con error de validación.
- [ ] Destino 'danada' suma a `product_variants.damaged_stock` (y `stock` físico) y crea registro en `damaged_stock_logs`.
- [ ] Destino 'danada' en Melamina suma a `variant_color_stocks.stock` y `damaged_stock` del color indicado.
- [ ] Rol `inventario` registra devoluciones con destino 'danada' exitosamente sin tener acceso directo a `DamagedStockController`.
- [ ] Destino 'baja' no toca inventario y deja registro histórico de la merma.
- [ ] Destino 'color_equivocado' en Melamina suma existencias al color real indicado; si no se especifica color falla validación.
- [ ] Destino 'color_equivocado' en otro material suma al stock general sin requerir color.
- [ ] Cancelar un embarque que tiene devoluciones registradas es bloqueado con error descriptivo.
- [ ] Devolución con destino 'danada' o 'baja' reactiva el faltante en la partida de producción (se descuenta de `completed` en la fórmula de avance).
- [ ] Pedido 'entregado' regresa a 'produccion' si tras la devolución quedan piezas pendientes; historial registrado por `SaleObserver` (sin duplicar `SaleHistory`).
- [ ] Devolución de pedido completo (todas las partidas devueltas).
- [ ] Campo "Autorizado por" es obligatorio; devolución permitida sin plazo límite tras la entrega.
- [ ] Partida con color especial devuelta no altera `variant_color_stocks`.
- [ ] Roles no autorizados (`vendedor`, `produccion`) reciben 403 Forbidden al intentar registrar devoluciones.
- [ ] Suites existentes + tests del módulo A siguen en verde.

### Riesgos Técnicos:
- Omisión de restar devoluciones en algún cálculo de "entregado" en producción o embarques (riesgo de cerrar pedidos indebidamente o generar sobreproducción).
- Doble conteo entre cancelación de un viaje y devolución de partidas de ese mismo viaje (mitigado con el bloqueo estricto de cancelación de viaje con devoluciones).
- Interacción con remanentes pausados (`production_hold`) y con la fórmula de avance máximo `MAX(completed, detailed, delivered)`.
- Dependencia estricta de `color_id` de la Fase 2.7.

#### 🔮 Integración futura con Cobranza (Fase 3.2 en Pausa):
- La devolución almacena `sale_detail_id` para calcular posteriormente el valor económico devuelto: `(unit_price con descuento × cantidad) + proporción de additional_cost`.
- Si la pieza devuelta se vendió como color especial (nota con costo extra), su `additional_cost` **debe incluirse** en el cálculo del saldo a favor / nota de crédito en Cobranza, a pesar de no tocar `variant_color_stocks`.

---

## Fase 4: Catálogo Público Digital [x] COMPLETADA
**Objetivo:** Crear un catálogo web público, optimizado para SEO, que actúe como un "Showroom Digital" sin exponer precios, incitando a la cotización vía WhatsApp.
**Especificaciones Técnicas (Strict):**
- [x] Desarrollo en Laravel Blade puro (SSR) y Vanilla JS (Prohibido Inertia/Vue).
- [x] Filtros por categoría y cuadrícula de productos elegante.
- [x] Construcción segura de URLs de imágenes (evitando fallos por espacios con `rawurlencode`) y normalización mediante accesor `$product->image_url`.
- [x] Ficha de fabricante en Modal interactivo con matrices lógicas de taller (colores HEX condicionados por el material de la categoría).
- [x] Modal tipo carrusel con navegación continua, swipe táctil y controles de teclado, restringido fluidamente al `max-h-[90vh]`.
- [x] Call-To-Action (CTA) dinámico directo a WhatsApp para cotizaciones personalizadas (`company_whatsapp` con fallback a `company_phone`).
- [x] Configuración en panel de Settings: `company_whatsapp` para atención comercial y `catalog_only_with_images` para ocultar piezas sin foto en `/catalogo`.

### 4.1 Catálogo Público (sin precios)
- [x] Conectar la vista a `products`, `categories`, `product_variants` reales.
- [x] Rutas públicas: `/` (Landing Page corporativa) y `/catalogo` (Catálogo completo).
- [x] Nunca exponer `price_1..price_5`.
- [x] Ocultar productos sin imagen si la configuración `catalog_only_with_images` está activada.
- [x] Configuración de WhatsApp empresarial (`company_whatsapp`).

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
- [x] Notas de entrega individuales por pedido (Remisiones de embarque refactorizadas con diseño corporativo, inyectando variables `$logoBase64` y `$company` desde el controlador `ShipmentController::printManifest`).

---

## 🐛 BUGS CONOCIDOS

| # | Bug | Estado |
|---|-----|--------|
| 1 | Input de moneda en Safari/iOS — permite caracteres no numéricos | No se revisó en esta auditoría (requiere prueba manual en Safari) |
| 2 | `formatDate` sin usar en `Production/Index.vue` | ✅ Ya no existe en el código — resuelto |

### ⚠️ Bugs Latentes Auditados en Código (Pendientes de Corrección)
1. **`ProductionController::printReport` no calcula entregas:** Asume 0 entregas y filtra exclusivamente pedidos con `stage = 'produccion'`, omitiendo piezas pendientes de fabricación de pedidos en otras etapas y distorsionando el reporte impreso de taller.
2. **`ShipmentController::store` suma entregas canceladas:** Al verificar `quantity_delivered` (L188-200), no excluye los embarques cancelados (`where('status', '!=', 'cancelled')`), provocando inconsistencias si una partida tuvo un embarque previo cancelado.
3. **Validación de color obligatorio universal en POS:** `Sales/Create.vue` L274 exige seleccionar color en TODOS los materiales (contradiciendo la regla de negocio A3 donde en MDF y Madera el color es opcional/natural) y `SaleController::store` L106 valida `color` como `required` de forma universal.

### 🐛 BUGS NUEVOS (Alta Prioridad)
- [x] **Fallo de Reactividad en Kanban:** Resuelto. Se implementó una captura local inmutable (`const sale = selectedSale.value;`) antes de lanzar promesas asíncronas de SweetAlert, y el backend ahora procesa el guardado en `updateStage`.

### 🏭 NUEVA FUNCIONALIDAD: Estado "Detallado" y Stock Reservado
- [x] **Esquema:** Añadir columna `reserved_stock` (int, default 0) a `product_variants`.
- [x] **Esquema:** Crear tabla `detallado_records` (similar a `sale_deliveries`) vinculada a `sale_detail_id` para registrar envíos parciales a detallado.
- [x] **Backend (Ventas/POS y Producción):** Modificar el cálculo de disponibilidad en todo el sistema. El stock real disponible ahora será `stock - reserved_stock`.
- [x] **Backend (Embarques):** Refactorizar `ShipmentController::store`. Al confirmar un viaje usando piezas que vienen de detallado, descontar simultáneamente de `stock` físico y `reserved_stock` utilizando `lockForUpdate()`.
- [x] **Frontend:** UI en el Detalle del Pedido (`Sales/Show.vue`) para enviar piezas parciales a la etapa de Detallado sin alterar el `stage` global del pedido en el Kanban.
*Nota de implementación:* Se implementó la fórmula de "Avance Máximo" (`MAX(completados, detallados, enviados)`) en `ProductionController` para evitar deducciones dobles en el cálculo de remanentes, y se aisló exitosamente el WIP visual en el frontend (columna "En Detallado" mostrando solo piezas no embarcadas).

---

## 📌 Orden de Dependencias (actualizado 30 oct 2026)
 
 ```
 Fase 0 ✅ → Fase 1 ✅ → Sprint Cliente (5 puntos) ✅ → Fase 4.1 Catálogo Público ✅
                                      ↓
                     Fase 2.5 — Embarques y CSS (COMPLETADO)
                                      ↓
                     🔴 Fase 0 — Hotfix Colores de Melamina (¡PRIORIDAD MÁXIMA!)
                                      ↓
                     🔴 Fase 2.7 — Catálogos Administrables (¡Prioridad 1 URGENTE!)
                                      ↓
                     🔴 Fase 2.8 — Módulo de Devoluciones (¡Prioridad 2 URGENTE!)
                                      ↓
                     ⏸️ [Fase 3.2 Cobranza — EN PAUSA esperando respuestas del cliente]
                                      ↓
                     Fase 4.2 — Link Personalizado por Cliente con Precios (catalog_token)
                                      ↓
                     Fase 3 (Dashboards por rol) — Producción, Finanzas e Inventarios
                                      ↓
                     Fase 5 (Precios dinámicos por flete) — depende de Fase 4.2
                                      ↓
                     Fase 6 (Reportes PDF globales) — depende de Fase 3
                                      ↓
                     Fase 7 (Rendimiento en Hosting) — Paginación y Optimizaciones (Fase 2.6 pospuesta)
 ```

> **Nota sobre Cobranza (Fase 3.2 en Pausa):** Se encuentra temporalmente detenida a la espera de definiciones de negocio por parte del cliente (política de crédito y días de gracia, obligatoriedad de anticipo para confirmar pedido, reglas de abonos globales entre ventas, política de anticipos ante cancelaciones, roles autorizados para cobrar, bloqueo automático por saldo vencido y formatos contables requeridos). Se reanudará una vez resueltas estas incógnitas.
