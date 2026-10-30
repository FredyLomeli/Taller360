# 🗺️ GUÍA DE RUTA — TALLER 360
**Actualizada:** 05 de septiembre 2026 — **Sincronizada documentalmente:** 29 de octubre 2026 (v2.8 real)

---

## Estado actual en una línea
> El sistema se encuentra en **versión 2.8 estable** con los módulos de **Sprint Cliente (Agosto)**, **Fase 4.1 Catálogo Digital Blade SSR**, **Estado Detallado / Reserva de Stock (v2.8)**, **Fase 2.5 Embarques (Multi-cliente y Remisiones corporativas)**, **Ajustes al Ciclo de Venta** y **Sprint de Octubre (Órdenes de Trabajo desacopladas, Control de Daños y Existencias por color para Melamina)** completados y auditados en código. El trabajo inmediato y prioritario se estructura en: **🔴 Fase 0 — Hotfix Colores de Melamina (PRIORIDAD MÁXIMA)**, seguido de **🔴 Fase 2.7 — Catálogos Administrables (URGENTE, 1°)** y **🔴 Fase 2.8 — Módulo de Devoluciones (URGENTE, 2°)** (*planeados, en espera de "Procede hotfix"*). La **Fase 3.2 Cobranza está ⏸️ EN PAUSA** a la espera de respuestas del cliente.

---

## Mapa de fases (actualizado según estado real del código)

```
✅ FASE 0 — Seguridad y dependencias              (COMPLETADA — Laravel 12.62.0, dompdf 3.1.2)
✅ FASE 1 — Deuda técnica y roles base            (COMPLETADA — UserController y CheckRole OK)
✅ BUGS CRÍTICOS JULIO                           (CONFIRMADOS RESUELTOS EN CÓDIGO)
✅ SPRINT CLIENTE (04 ago 2026)                   (COMPLETADO: Auto-correo, Supervisor, Limbo, Min Stock, Work Orders)
✅ FASE 4.1 — Catálogo Público y Landing          (COMPLETADA: Blade SSR, SEO, modal y WhatsApp)
✅ ESTADO DETALLADO Y RESERVA (v2.8)             (COMPLETADA: WIP aislado y deductivas MAX en backend)
✅ FASE 2.5 — Embarques y CSS                    (COMPLETADA: Multi-cliente, remisiones por pedido)
✅ MIGRACIÓN TAILWIND V4                         (COMPLETADA: Tailwind v4.3.3 nativo con @tailwindcss/vite)
✅ AJUSTES CICLO DE VENTA                        (COMPLETADA: Fecha compromiso editable + Modo Taller backend)
✅ SPRINT OCTUBRE 2026                           (COMPLETADO: OT autónomas, Dañadas, Existencias Melamina por Color)
                                           ↓
🔴 FASE 0 — HOTFIX COLORES DE MELAMINA           (PRIORIDAD MÁXIMA: 7 colores oficiales en código duro) [¡En espera de "Procede hotfix"!]
🔴 FASE 2.7 — SPRINT CATÁLOGOS ADMINISTRABLES     (URGENTE: 1° en orden, categorías, materiales y colores)
🔴 FASE 2.8 — MÓDULO DE DEVOLUCIONES              (URGENTE: 2° en orden, depende de color_id de 2.7)
                                           ↓
⏸️ FASE 3.2 — COBRANZA Y FINANZAS                 (EN PAUSA — esperando respuestas de negocio del cliente)
                                           ↓
🟢 FASE 4.2 — Catálogo con Precios por Cliente    (catalog_token + vista por price_tier)
🟡 FASE 3   — Dashboards por Rol                  (Finanzas, Producción, Inventarios)
🔵 FASE 5   — Precios dinámicos por flete          (diseño cerrado, sin construir)
🟣 FASE 6   — Reportes PDF globales               (actividad final)
⚪ FASE 7 / TAREA 3 — Rendimiento en Hosting      (paginación en ProductController + límite en POS) [Pospuesto al final]
```

---

## ✅ SPRINT 04 DE AGOSTO 2026 — COMPLETADO Y AUDITADO EN CÓDIGO

Los 5 puntos acordados con el cliente están completamente implementados y respaldados por pruebas funcionales:
1. **Envío automático de notas de venta:** Implementado en `SaleController::sendSaleNoteMail` con `dispatch()->afterResponse()` y controlado por la clave `auto_email_on_sale`. Respaldado por `SaleAutoEmailTest.php`.
2. **Supervisor con permisos ampliados:** Middleware `role:admin,produccion,supervisor` y `role:admin,inventario,supervisor` implementados en `routes/web.php`. Rutas de productos compartidas con `role:admin,supervisor`. Respaldado por `RoleMiddlewareTest.php`.
3. **Bug del pedido cancelado en limbo:** `ShipmentController::cancel()` recalcula en vivo el estatus matemático de partidas entregadas para regresar a `produccion`. Respaldado por `ShipmentControllerTest.php`.
4. **Stock mínimo por variante (`min_stock`):** Columna agregada a `product_variants`, input visible en `Products/Create.vue` y `Edit.vue` solo si `is_favorite === true`. Alerta dinámica en `Dashboard.vue` (`stock <= COALESCE(min_stock, 5)`).
5. **Órdenes de Trabajo (`work_orders`) y Pausa de Remanentes:** Módulo completo (`WorkOrderController`), `sale_details.production_hold`, cola combinada en `ProductionController::index` y UI de liberación en `Production/Index.vue`. Respaldado por `WorkOrderLogicTest.php` y `WorkOrderDatabaseTest.php`.

---

## ✅ FASE 4.1: CATÁLOGO PÚBLICO Y LANDING — COMPLETADA EN CÓDIGO
- Rutas públicas `/` (Landing Page) y `/catalogo` (Catálogo general) servidas por `LandingController` y `CatalogController` mediante Laravel Blade puro (SSR).
- Datos directos de `categories`, `products` y `product_variants` con filtro `catalog_only_with_images`.
- Ficha interactiva en modal Vanilla JS con swipe táctil, carrusel y selector de acabados sin exponer precios ni stock.
- Botón directo de cotización a WhatsApp empresarial (`company_whatsapp`).
- Accesor `$product->image_url` con normalización segura de URLs vía `rawurlencode()`.

---

## ✅ ESTADO DETALLADO Y STOCK RESERVADO — COMPLETADA EN CÓDIGO
- Tablas `detallado_records` y columna `reserved_stock` implementadas exitosamente.
- El remanente global y los envíos parciales están estabilizados usando lógica de MAX (`MAX(completados, detallados, enviados)`) implementada de forma segura en `ProductionController`.
- Refactorización transaccional en el `ShipmentController::store`.
- Visualización independiente del WIP para no ensuciar datos históricos en el frontend.

---

## 🔴 PRÓXIMA TAREA #0 (PRIORIDAD MÁXIMA): Fase 0 — Hotfix Colores de Melamina
**Tiempo estimado:** 1 sesión | **Prioridad:** 🔴 MÁXIMA (antes de la Fase 2.7) | **Estado:** Documentado, en espera de "Procede hotfix" del PM

Sustitución inmediata de los 4 colores erróneos de Melamina (`Blanco`, `Gris`, `Texturizado`, `Oyamel` — reconocidos como un error de desarrollo) por los 7 oficiales de fábrica acordados: `Fresno andino`, `Parota`, `Nogal africano`, `Gris cenizo`, `Gris antracita`, `Tzalam`, `Moka`.
- **Alcance Técnico (Código duro, sin alterar schema ni crear color_id):**
  1. `ProductionController.php`: L119 (cálculo de faltantes por color) y L459 (`printReport` reporte impreso).
  2. `Production/Index.vue`: L78 (selector en modal OT) y L522-525 (**eliminar default `'Blanco'`** para no asignar colores arbitrarios).
  3. `Products/Create.vue`: L46 (matriz de colores de Melamina).
  4. `Products/Edit.vue`: L25 (matriz de colores de Melamina).
  5. `Sales/Create.vue`: L36-41 (matriz Melamina 7 colores, alinear MDF a 6 colores y Madera a 4 con códigos HEX de Blade) y L45-49 (**ajuste crítico en `getColorsForMaterial`:** como evalúa por subcadena, evitar que `"Madera y MDF enchapado"` caiga en la paleta de MDF; debe usar la de Madera: Chocolate, Caoba, Tabaco, Cherry).
- **Regla estricta:** NO ejecutar hasta recibir del PM la confirmación explícita: *"Procede hotfix"*.

---

## 🔴 PRÓXIMA TAREA #1 (URGENTE — PRIORIDAD MÁXIMA): Fase 2.7 — Sprint Catálogos Administrables
**Tiempo estimado:** 5 a 7 sesiones | **Prioridad:** 🔴 URGENTE (1° en orden de ejecución tras Fase 0) | **Estado:** Planeado, sin construir

Categorías, materiales por categoría y colores por material están hardcodeados en código fuente y plantillas. Se requiere administrarlos dinámicamente porque un proveedor descontinuó un color y agregó uno nuevo.
- **Alcance Técnico:**
  1. Auditoría y línea base de tests (`php artisan test`).
  2. Migraciones y modelos: tabla `materials` (4 confirmados: `MDF`, `Madera`, `Madera y MDF enchapado`, `Melamina`), `colors`, pivote `category_material`, pivote `material_color` (con campo `activo` para descontinuar por material), columna `activo` en `categories`. Migrar de texto libre a `material_id` y `color_id`.
  3. Seeder fiel a la fuente de verdad (A12): 15 colores con HEX de `catalogo/index.blade.php` L437-442; conteos pendientes de confirmación del PM: 14 o más relaciones categoría↔material (según decisión sobre las 46 variantes de Madera en Roperos/Cómodas) y 17 o 21 relaciones material↔color (según si "Madera y MDF enchapado" comparte o tiene colores propios).
  4. Pantalla de administración "Catálogos" exclusiva para `role:admin,supervisor` (el supervisor sigue sin acceso a Configuración).
  5. Productos y POS alimentados de BD con validación categoría-material y regla de "color especial" (capturado en notas con costo extra, sin `color_id`; Melamina rechaza color especial).
  6. Producción, órdenes de trabajo y embarques con `color_id` y regla de colores descontinuados (se permite embarcar si hay stock existente, pero se bloquea fabricar y vender).
  7. Catálogo público dinámico, nota visible de color especial en reporte de taller (`Production/Print.vue`) y alertas de stock de descontinuados.
  8. Tests automatizados de regresión, incluyendo validación firme: *todas las variantes existentes conservan categoría y material válidos tras la migración (incluyendo las 35 de Madera en Roperos y 11 en Cómodas, bajo regla firme A11)*.

---

## 🔴 PRÓXIMA TAREA #2 (URGENTE — PRIORIDAD ALTA): Fase 2.8 — Módulo de Devoluciones
**Tiempo estimado:** 2 a 3 sesiones | **Prioridad:** 🔴 URGENTE (2° en orden; depende de Fase 2.7) | **Estado:** Planeado, sin construir
**Ubicación:** Submenú "Devoluciones" en Embarques / Logística (`role:admin,inventario,supervisor`).  
**Restricción de inicio:** Se construye **estrictamente después de la fase 5 de la Fase 2.7** (requiere `color_id` para mover inventario por color).
- **Alcance Técnico:**
  1. Auditoría de lectura de "entregado" (realizada: ver `CONTEXTO_TECNICO.md` Anexo 0.3-X2).
  2. Migración y modelos para `sale_returns` y `sale_return_items`.
  3. Controlador transaccional con `lockForUpdate` y acción de servicio (`DamagedStockService`): validación `cantidad <= entregado neto`, destinos de pieza:
     - `danada`: suma a `product_variants.stock` Y `damaged_stock` (y en Melamina a `variant_color_stocks.stock` y `damaged_stock` del `color_id` correspondiente) y crea log en `damaged_stock_logs`. Rol `inventario` autorizado.
     - `baja`: merma total sin reposición física.
     - `color_equivocado`: suma al stock disponible del color real capturado (obligatorio color en Melamina).
  4. Bloqueo estricto de cancelación de viaje: si un embarque tiene devoluciones registradas, se rechaza la cancelación con mensaje explicativo.
  5. Recálculo dinámico de faltantes en producción (sin WorkOrder automática): restar de `completed` las piezas devueltas dañadas o de baja para reactivar el faltante en el Plan Taller. Si quedan faltantes en el pedido, su etapa regresa a `produccion` (registrado por `SaleObserver`, sin crear `SaleHistory` manual).
  6. UI con selector de pedido/partida entregada, campo obligatorio "Autorizado por" (`authorized_by`), notas y visualización en el detalle del pedido (`Sales/Show.vue`).
  7. Ajuste de todos los `withSum` de entregas para restar devoluciones netas. Tests automatizados de regresión.

---

## ⏸️ FASE 3.2 COBRANZA Y MÓDULO FINANCIERO — EN PAUSA (Esperando Respuestas del Cliente)
**Estado:** Detenido temporalmente. No es prioridad actual. Se retomará tras resolver las incógnitas comerciales y contables con el cliente.
**Preguntas pendientes que el cliente debe responder antes de codificar:**
1. **Política de crédito y plazos:** ¿A qué clientes se les otorga crédito, con cuántos días de plazo/gracia y qué montos máximos autorizados?
2. **Anticipo para confirmación:** ¿El anticipo es estrictamente obligatorio para confirmar el pedido en el Kanban y pasarlo a Producción, o se admiten pedidos confirmados sin anticipo bajo autorización?
3. **Abonos globales:** ¿Se aceptan pagos globales que amorticen múltiples pedidos de un mismo cliente simultáneamente, o cada abono debe vincularse forzosamente a una venta individual (`sale_id`)?
4. **Tratamiento de anticipos en cancelaciones:** Si un pedido con abonos previos se cancela, ¿qué sucede con el dinero (reembolso en efectivo, generación de saldo a favor del cliente o penalización)?
5. **Roles y atribuciones:** ¿Quién está facultado para capturar cobros y quién para consultar estados de cuenta (vendedor, administrativo/caja, supervisor)?
6. **Bloqueo automático por cartera vencida:** ¿El sistema debe impedir que un cliente con facturas/notas vencidas registre nuevos pedidos o reciba embarques?
7. **Reportes y formatos actuales:** ¿Qué reportes o formatos contables se utilizan hoy en día para conciliar ingresos, saldos pendientes y notas de crédito?

---

## 🟢 HISTORIAL DE TAREAS PREVIAS COMPLETADAS (Auditadas en Código)

### ~~PRÓXIMA TAREA #0 (CRÍTICA): Resolución Bug Kanban~~ ✅ Resuelto
- Se implementó captura local inmutable (`const sale = selectedSale.value;`) antes de abrir los modales de SweetAlert en `Sales/Index.vue`, estabilizando la reactividad de `promised_date`.

### ~~PRÓXIMA TAREA #1: Fase 2.5 — Embarques: Lo que falta en UI y Remisión~~ ✅ Resuelto
- **Selector multi-cliente:** Implementado en `Shipments/Create.vue` con soporte reactivo de `client_ids[]`.
- **Remisión agrupada por pedido:** Implementado en `ShipmentController::printManifest()` (`$deliveries->groupBy('saleDetail.sale_id')`) y `shipment_manifest.blade.php` con formato institucional y cálculo de importes exactos.

### ~~PRÓXIMA TAREA #2: Resolver conflicto de versiones de Tailwind CSS~~ ✅ Resuelto
- Migración completa a Tailwind CSS v4.3.3 nativo utilizando `@tailwindcss/vite` y `@import "tailwindcss"`. Dependencias legacy de PostCSS y Autoprefixer eliminadas.

### ~~PRÓXIMA TAREA #4: Ajustes Finos al Ciclo de Venta~~ ✅ Resuelto
- **Fecha compromiso editable:** Implementado en Kanban y `Sales/Show.vue` con SweetAlert.
- **Forzar Modo Taller por rol en backend:** Implementado en `SaleController::show()` (`is_production_mode = true` y `makeHidden()` de precios para roles `produccion`, `inventario` y `supervisor`).

---

## 🟢 PRÓXIMA TAREA #3: Fase 4.2 — Link de Catálogo Personalizado por Cliente
**Tiempo estimado:** 2-3 horas | **Prioridad:** Comercial (posterior a Fases 2.7 y 2.8)

1. Migración: agregar `catalog_token` (UUID único) a tabla `clients`.
2. Ruta pública `/catalogo/cliente/{token}` que muestre el catálogo con los precios de la lista (`price_1` a `price_5`) asignada al `price_tier` del cliente.
3. Botón "Copiar Link de Catálogo" en `Clients/Index.vue`.

---

## 🟡 PRÓXIMA TAREA #4: Fase 3 — Dashboards Especializados por Rol
**Tiempo estimado:** 4-6 horas | **Prioridad:** Media (posterior a Fases 2.7 y 2.8)

`DashboardController::index()` solo tiene ramas para `admin` y `vendedor`. Los roles `produccion`, `inventario`, `supervisor` y `financiero` caen a una pantalla de bienvenida genérica.
- Construir vistas especializadas y KPIs para Taller, Inventarios y Finanzas (Fases 3.1, 3.3, 3.4).

---

## ⚪ PRÓXIMA TAREA #5: Fase 7 (Fase 2.6) — Optimización para Hosting Compartido
**Estado:** Pospuesto al final del proyecto por decisión de negocio. | **Prioridad:** Baja

1. **`ProductController::index()`:** Paginar en servidor (`Product::with(['category:id,name', 'variants'])->paginate(20)`) y adaptar `Products/Index.vue`.
2. **`SaleController::create()` (POS):** Acotar `Client::all()` a columnas necesarias (`id`, `name`, `business_name`, `price_tier`).

---

## Notas para la siguiente sesión con IA

1. Comparte siempre `CONTEXTO_TECNICO.md` actualizado al iniciar.
2. El proyecto cuenta con **21 archivos de tests en `tests/Feature/`** (y suites base en `tests/`); ejecútalas con `php artisan test` para validar no regresiones antes de tocar lógica central.
3. Para tareas de frontend/build, ten en cuenta que el proyecto corre en **Tailwind CSS v4** nativo con `@tailwindcss/vite` (no existen `tailwind.config.js` ni `postcss.config.js`).
