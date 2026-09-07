# 🗺️ GUÍA DE RUTA — TALLER 360
**Actualizada:** 05 de septiembre 2026 — sincronizada contra código real del repositorio (v2.7)

---

## Estado actual en una línea
> El **Sprint de Agosto 2026** (5 puntos: Auto-correo, Supervisor con permisos ampliados, Corrección de limbo de pedidos, Stock mínimo por variante y Órdenes de Trabajo) y la **Fase 4.1 de Catálogo Digital Público y Landing Page** están **100% implementados, funcionales y con 16 suites de tests automatizados**. Los pendientes reales del sistema se concentran en la Fase 2.5 de Embarques (selector multi-cliente y agrupación de remisiones), optimizaciones de consultas para hosting compartido, fecha compromiso editable y la Fase 4.2 (catálogo con precios por cliente).

---

## Mapa de fases (actualizado según estado real del código)

```
✅ FASE 0 — Seguridad y dependencias         (COMPLETADA — Laravel 12.62.0, dompdf 3.1.2)
✅ FASE 1 — Deuda técnica y roles base       (COMPLETADA — UserController y CheckRole OK)
✅ BUGS CRÍTICOS JULIO                      (CONFIRMADOS RESUELTOS EN CÓDIGO)
✅ SPRINT CLIENTE (04 ago 2026)              (COMPLETADO: Auto-correo, Supervisor, Limbo, Min Stock, Work Orders)
✅ FASE 4.1 — Catálogo Público y Landing     (COMPLETADA: Blade SSR, SEO, modal y WhatsApp)
✅ ESTADO DETALLADO Y RESERVA                (COMPLETADA: WIP aislado y deductivas MAX en backend)
                                     ↓
🟢 TAREA 1 — FASE 2.5 Embarques              (selector multi-cliente UI + remisión agrupada)
🟢 TAREA 2 — Limpieza dependencias Tailwind  (remover @tailwindcss/vite muerto o migrar a v4)
🟡 TAREA 3 — FASE 2.6 Rendimiento Hosting    (paginación en ProductController + límite en POS)
🟡 TAREA 4 — Ajustes al Ciclo de Venta       (fecha compromiso editable + forzar modo taller por backend)
🟢 TAREA 5 — FASE 4.2 Catálogo por Cliente   (catalog_token + vista con precios por tier)
🟡 TAREA 6 — FASE 3 Dashboards por Rol       (Producción, Financiero, Inventarios)
🔵 FASE 5   — Precios dinámicos por flete     (diseño cerrado, sin construir)
🟣 FASE 6   — Reportes PDF globales          (actividad final)
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
## PRÓXIMA TAREA #0 (CRÍTICA): Resolución Bug Kanban
**Tiempo estimado:** 30 min | **Prioridad:** Bloqueante
- Corregir pérdida de reactividad `s.value is null` en `promised_date` dentro de `Sales/Index.vue`.

## PRÓXIMA TAREA #1: Fase 2.5 — Embarques: Lo que falta en UI y Remisión
**Tiempo estimado:** 2-3 horas | **Prioridad:** Alta (operativa)

1. **Selector multi-cliente en `Shipments/Create.vue`:**
   - El backend ya soporta `client_ids[]` en `ShipmentController::create()`.
   - Falta construir un selector múltiple o chips de clientes en el `.vue` para filtrar pedidos a embarcar sin perder la selección de partidas ya cargadas al camión.
2. **Remisión agrupada por cliente/pedido (`shipment_manifest.blade.php`):**
   - Hoy `printManifest()` y la plantilla hacen un `@foreach` plano que mezcla todos los pedidos del viaje.
   - Agrupar en `ShipmentController::printManifest()`:
     ```php
     $groupedByClient = $shipment->deliveries->groupBy(fn($d) => $d->saleDetail->sale->client_id);
     ```
   - Reemplazar el `@foreach` plano en `shipment_manifest.blade.php` por bloques de entrega separados con encabezados por cliente y pedido.

---

## PRÓXIMA TAREA #2: Resolver conflicto de versiones de Tailwind CSS
**Tiempo estimado:** 15 min (Opción A) a 1 h (Opción B) | **Prioridad:** Media (limpieza técnica)

El archivo `package.json` tiene instalados `@tailwindcss/vite ^4.0.0` y `tailwindcss ^3.2.1`. El proyecto corre en v3 vía PostCSS.

- **Opción A — quedarse en v3 (rápido, cero riesgo):**
  ```bash
  npm uninstall @tailwindcss/vite
  ```
- **Opción B — completar la migración a v4:**
  1. En `resources/css/app.css`: reemplazar `@tailwind...` por `@import "tailwindcss"; @plugin "@tailwindcss/forms";`.
  2. En `vite.config.js`: registrar plugin `tailwindcss()`.
  3. Quitar `tailwindcss` v3, `postcss`, `autoprefixer` y eliminar `tailwind.config.js`.

---

## PRÓXIMA TAREA #3: Optimización de Consultas para Hosting Compartido (Fase 2.6)
**Tiempo estimado:** 1-2 horas | **Prioridad:** Media (rendimiento)

1. **`ProductController::index()`:**
   - Carga todos los productos con variantes y precios sin paginar.
   - Paginar en servidor (`Product::with(['category:id,name', 'variants'])->paginate(20)`) y adaptar `Products/Index.vue`.
2. **`SaleController::create()` (POS):**
   - Acotar `Client::all()` a:
     ```php
     Client::select('id', 'name', 'business_name', 'price_tier')->orderBy('name')->get()
     ```

---

## PRÓXIMA TAREA #4: Ajustes Finos al Ciclo de Venta
**Tiempo estimado:** 1-2 horas | **Prioridad:** Media

1. **Fecha compromiso editable:** Agregar modal o formulario en `Sales/Show.vue` o Kanban para actualizar `promised_date` tras haber creado el pedido.
2. **Forzar Modo Taller por rol en backend:** Modificar `SaleController::show()` para que asigne automáticamente `is_production_mode = true` cuando el usuario autenticado tenga rol `supervisor`, `produccion` o `inventario`, ignorando el query param.

---

## PRÓXIMA TAREA #5: Fase 4.2 — Link de Catálogo Personalizado por Cliente
**Tiempo estimado:** 2-3 horas | **Prioridad:** Comercial

1. Migración: agregar `catalog_token` (UUID único) a tabla `clients`.
2. Ruta pública `/catalogo/cliente/{token}` que muestre el catálogo con los precios de la lista (`price_1` a `price_5`) asignada al `price_tier` del cliente.
3. Botón "Copiar Link de Catálogo" en `Clients/Index.vue`.

---

## PRÓXIMA TAREA #6: Fase 3 — Dashboards Especializados por Rol
**Tiempo estimado:** 4-6 horas | **Prioridad:** Media

`DashboardController::index()` solo tiene ramas para `admin` y `vendedor`. Los roles `produccion`, `inventario`, `supervisor` y `financiero` caen a una pantalla de bienvenida genérica.
- Construir vistas especializadas y KPIs para Taller, Inventarios y Finanzas.

---

## Notas para la siguiente sesión con IA

1. Comparte siempre `CONTEXTO_TECNICO.md` actualizado al iniciar.
2. El proyecto cuenta con **16 suites de tests automatizados** (`tests/Feature/`); ejecútalas con `php artisan test` para validar no regresiones antes de tocar lógica central.
3. Para tareas de frontend/build, ten en cuenta el estado de Tailwind v3 documentado en la Tarea #2.
