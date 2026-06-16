# 📋 BACKLOG — TALLER 360
**Auditado y actualizado:** Junio 2026
**Basado en código real + visión de negocio ampliada**

---

## ✅ COMPLETADO Y FUNCIONANDO (Confirmado por auditoría de código)

### Backend
- [x] Autenticación Laravel (login, logout, perfil)
- [x] Middleware de roles (admin/vendedor)
- [x] CRUD completo de Clientes (con protección al borrar si tiene ventas)
- [x] CRUD completo de Productos con variantes (upsert inteligente en update)
- [x] Toggle de favorito en productos
- [x] POS — guardar pedido con detalles, color, notas, anticipo, firma digital
- [x] Auto-confirmación de pedido si anticipo > 0
- [x] Motor de etapas (updateStage) con lógica de stock
- [x] Descuento de stock al pasar a 'enviado'
- [x] Retorno de stock al cancelar pedido enviado
- [x] Configuración `allow_negative_stock` desde Settings
- [x] Plan de Producción — agrupación por producto+material con desglose por color
- [x] Abonos parciales con validación de deuda y transacción atómica
- [x] PDFs: ticket y nota de venta con lógica FILESYSTEM_PUBLIC_ROOT
- [x] Envío de correo con PDF adjunto al cliente
- [x] Dashboard único con KPIs financieros y operativos diferenciados por rol
- [x] Filtros de fecha en Dashboard
- [x] Stock bajo en Dashboard (solo favoritos, ≤ 5 piezas)
- [x] Rendimiento de vendedores en Dashboard
- [x] CRUD de Usuarios (sin edición)
- [x] Configuración del sistema (logo, datos empresa, reglas de negocio)
- [x] Historial automático de cambios de etapa vía `SaleObserver`

### Frontend
- [x] Dashboard único con KPIs, filtros, stock crítico y rendimiento
- [x] POS completo: catálogo visual, colores, notas/extras, firma digital, modal cliente
- [x] Kanban con tabs por etapa, modal de detalle inline, historial colapsable
- [x] Detalle de venta (`Show.vue`) con modo Oficina/Taller y modal de abono
- [x] Plan de Producción con CSS print
- [x] Productos: Create, Edit (con lightbox), Index (paginación local, toggle estrella)
- [x] Clientes: Create, Edit, Index (búsqueda throttle, paginación servidor)
- [x] Usuarios: Create, Index
- [x] Settings con preview de logo en tiempo real
- [x] Componente `ClientAutocomplete.vue` reutilizable
- [x] Componente `Modal.vue` reutilizable

---

## 🚨 FASE 0 — Seguridad y Dependencias ✅ SESIÓN COMPLETADA (Junio 2026)
**Resultado:** Sesión dedicada completa. Se resolvió el problema estructural de Vite, se actualizó Laravel y ~49 paquetes relacionados, Axios, y dompdf. Todo verificado funcionando (dev, build, y generación real de PDF).

### 0.1 Fijar versiones sin restricción en `composer.json` ✅ COMPLETADO
- [x] `barryvdh/laravel-dompdf`: de `*` a versión fija
- [x] `laravel-lang/common`: de `*` a versión fija

### 0.2 Actualizar Axios ✅ COMPLETADO
- [x] Actualizado a la última versión disponible (`npm install axios@latest`, ya sin necesidad de `--legacy-peer-deps`)
- [x] Confirmado: el conflicto de peer dependencies que exigía la bandera era específico de Vite/plugin-vue, no de Axios
- [x] Vulnerabilidades de npm audit bajaron de 13 a 11 tras esta actualización

### 0.3 Actualizar entorno de build (Vite + plugin-vue) ✅ COMPLETADO
- [x] Causa raíz identificada: `@vitejs/plugin-vue@5.2.4` solo soportaba Vite 5/6, pero el proyecto ya tenía Vite 7.3.1
- [x] Actualizado a `@vitejs/plugin-vue@6.0.7` (sí soporta Vite 7)
- [x] `npm run dev` probado: arranca limpio, sin errores
- [x] `npm run build` probado: 819 módulos transformados, build exitoso para producción
- ⏸️ Pendiente, no urgente: actualizar Vite/esbuild/rollup a fondo (viven en el entorno de build, no en producción — bajo riesgo real, queda para una sesión futura si se desea)

### 0.4 Actualizar Laravel y dependencias relacionadas ✅ COMPLETADO
- [x] Laravel actualizado de `12.46.0` a `12.62.0` (resuelve CVE-2026-48019, CRLF injection en emails)
- [x] ~49 paquetes relacionados actualizados automáticamente vía `--with-dependencies` (Symfony, Guzzle, Carbon, League/Flysystem, etc.)
- [x] Probado manualmente: login, POS, Dashboard cargan correctamente tras el salto

### 0.5 Actualizar dompdf ✅ COMPLETADO
- [x] Actualizado de `3.1.1` a `3.1.2` (junto con `dompdf/dompdf` y dependencias internas)
- [x] Probado generando una nota de venta real (`/sales/{id}/note`): logo, tabla de productos, descuentos, firma digital y totales se renderizan correctamente
- [x] Confirmado: el cambio de protocolos `data://` en versiones recientes de dompdf no afecta porque el proyecto usa configuración por defecto (no publicada/personalizada)

### 0.6 Alertas de bajo riesgo — Vigilancia, no acción inmediata
Detectadas con `composer audit` tras la actualización de Laravel:
- `phpunit/phpunit` (CVE-2026-24765) — Solo testing, no corre en producción
- `psy/psysh` (CVE-2026-25129) — Solo la consola `tinker`, no corre en producción
- `symfony/yaml` (3 CVEs: exponential memory, ReDoS, stack exhaustion) — Viene incluido con Laravel; el proyecto no parsea archivos YAML provenientes de usuarios externos, riesgo práctico bajo

Revisar de nuevo en la próxima sesión de mantenimiento (sugerido: cada 2-3 meses) con `composer audit` y `npm audit`.

---


## 🔴 FASE 1 — Deuda Técnica Crítica (corto plazo, 1-2 semanas)

### 1.1 Edición de Usuarios
No existe `Users/Edit.vue` ni métodos `edit`/`update`. Detalle técnico completo en `GUIA_DE_RUTA.md`.

### 1.2 Bug Logout "Inception" ✅ COMPLETADO
- [x] Interceptor agregado en `resources/js/bootstrap.js`
- [x] Probado: al expirar sesión (419), redirige limpio a `/login` en vez de renderizar el login flotando dentro del dashboard

### 1.3 HTML sucio en `Sales/Index.vue` ✅ COMPLETADO
- [x] Identificado: div duplicado en la línea 373 (cierre+apertura mal escritos en una línea, seguido de otra apertura idéntica en la línea 375)
- [x] Corregido: ahora solo existe un div envolviendo la sección de botones del modal
- [x] Probado visualmente: el padding duplicado que generaba un leve desalineamiento entre "Historial de Movimientos" y los botones de abajo ya no aparece; todo el contenido quedó consistente con el resto del modal
- [x] Confirmado que los botones (Nota PDF, Enviar Email, Avanzar Etapa, Cancelar) siguen funcionando correctamente

### 1.4 Permisos Granulares — Tercer Rol "Producción"
**Nuevo, alta prioridad.** Hoy el sistema solo distingue `admin` y `vendedor`. Se necesita un tercer rol `produccion` que:
- Vea cantidades, fechas y especificaciones técnicas (color, material, notas)
- **NUNCA** vea precios, totales, pagos ni nombres de columnas financieras
- Tenga acceso a: Plan de Producción, registro de piezas terminadas, vista de inventario
- NO tenga acceso a: Dashboard financiero, POS, Clientes, Configuración

Esto implica:
- [ ] Migrar columna `role` de string libre a validación contra 3 valores: `admin`, `vendedor`, `produccion`
- [ ] Revisar cada vista Vue que muestre dinero y ocultar esas secciones si el rol es `produccion`
- [ ] Nuevas reglas de middleware en `web.php` para las rutas exclusivas de producción

---

## 🟠 FASE 2 — Producción Semanal y Trazabilidad por Pieza (mediano plazo)

### 2.1 Plan de Producción Semanal (no acumulado)
**Problema actual:** `ProductionController` agrupa TODO lo que está en `stage = 'produccion'`, sin filtrar por semana. La empresa planea por semana, así que el reporte debe filtrar por `promised_date` dentro de un rango semanal seleccionable.

- [ ] Agregar filtro de semana (selector de rango) en `Production/Index.vue`
- [ ] El controlador filtra `sale_details` cuyas ventas tengan `promised_date` dentro del rango Y `stage = 'produccion'`
- [ ] Mantener la vista general "todo lo pendiente" como opción adicional, no reemplazo

### 2.2 Registro de Piezas Terminadas → Inventario de Producto Terminado
**Esto es nuevo y central.** Hoy el stock solo se mueve al pasar a `'enviado'`. Se necesita un paso intermedio:
- [ ] Nueva tabla `production_completions` (sale_detail_id, quantity_completed, completed_at, user_id)
- [ ] Vista para que producción marque, línea por línea, cuántas piezas de un pedido ya se terminaron
- [ ] Al marcar como terminada, esa cantidad entra al stock de `product_variants` (inventario de producto terminado), quedando disponible para envío
- [ ] Esto NO cambia el `stage` de la venta automáticamente — el pedido sigue en `produccion` hasta que se envíe, pero ya hay piezas físicas listas en bodega

### 2.3 Envíos Parciales — Trazabilidad a Nivel de Línea
**El cambio más grande de esta fase.** Hoy el `stage` vive a nivel de venta completa. La realidad es que una venta con 5 piezas puede enviarse en 2 embarques distintos.
- [ ] Agregar campo `shipped_quantity` en `sale_details` (cuántas de esa línea ya se enviaron)
- [ ] Una venta se considera `'enviado'` completo solo cuando TODAS sus líneas tengan `shipped_quantity = quantity`
- [ ] Nuevo estado visual "Envío Parcial" que coexiste con `stage = produccion` o `enviado`
- [ ] El campo `is_partial_shipping` ya existe en la tabla `sales` — actualmente no se usa en la lógica real, hay que activarlo

### 2.4 Embarques como Entidad Propia
**Depende de 2.3.** "2 o 3 pedidos en una camioneta" requiere agrupar piezas de múltiples ventas en un solo viaje.
- [ ] Nueva tabla `shipments` (id, shipped_at, driver_name o user_id, notes, status)
- [ ] Nueva tabla pivote `shipment_items` (shipment_id, sale_detail_id, quantity)
- [ ] Vista para armar un embarque: seleccionar piezas ya terminadas (de 2.2) de distintas ventas y agruparlas en un viaje
- [ ] Al confirmar el embarque, se actualiza `shipped_quantity` en cada `sale_detail` correspondiente
- [ ] Reporte/ticket de embarque imprimible (qué lleva la camioneta hoy)

---

## 🟡 FASE 3 — Dashboards Especializados por Rol (mediano plazo)

Hoy existe un solo Dashboard que cambia ligeramente entre admin/vendedor. Se necesitan **3 perfiles de dashboard distintos**, más la vista combinada para admin.

### 3.1 Dashboard de Ventas (Vendedor)
Ya existe parcialmente. Mantiene: sus ventas, sus tickets, su historial reciente.

### 3.2 Dashboard de Producción
**Nuevo.** Sin una sola cifra de dinero. Debe mostrar:
- [ ] Qué hay actualmente en producción (cantidad de piezas, agrupado por modelo+material)
- [ ] Qué está listo para embarcar (piezas con `production_completions` registradas pero aún no en un `shipment`)
- [ ] Calendario o lista de fechas compromiso (`promised_date`) próximas a vencer

### 3.3 Dashboard Financiero
**Nuevo.** Solo visible para roles con acceso a dinero (admin, y posiblemente un futuro rol "finanzas"):
- [ ] Cartera vencida: ventas con `promised_date` pasada y `paid_amount < total`
- [ ] Ingresos por rango de fechas (ya existe parcialmente, pero hay que separarlo del dashboard operativo)
- [ ] Proyección de cobranza pendiente

### 3.4 Selector de Vista para Admin
- [ ] El admin puede alternar entre los 3 dashboards o ver una versión combinada
- [ ] Tabs o selector en la parte superior del Dashboard

---

## 🟢 FASE 4 — Catálogo Público y Ventas Asistidas (mediano-largo plazo)

### 4.1 Catálogo Público (sin precios)
**Nuevo módulo completo, sin autenticación.**
- [ ] Rutas públicas: `/catalogo`, `/catalogo/{categoria}`, `/catalogo/producto/{id}`
- [ ] Diseño tipo tienda (referencia: websitedemos.net/furniture-shop-04) — hero, categorías en tarjetas, grid de productos con foto, selector de color/material visual
- [ ] Lee de las mismas tablas `products`, `categories`, `product_variants` — **nunca expone price_1...price_5**
- [ ] Se actualiza automáticamente con lo que ya está capturado en el sistema (no requiere mantenimiento duplicado)

### 4.2 Link Personalizado por Cliente (con precios)
**Depende de 4.1.**
- [ ] Cada cliente tiene un token único no adivinable (UUID, no su `id` numérico)
- [ ] Ruta tipo `/catalogo/cliente/{token}` que carga el mismo catálogo pero con precios calculados según el `price_tier` de ese cliente
- [ ] Botón en `Clients/Index.vue` o `Clients/Show` para "Generar/Copiar link personalizado"
- [ ] El admin puede regenerar el token si se compromete (ej. el cliente lo compartió de más)

---

## 🔵 FASE 5 — Precios Dinámicos por Flete (largo plazo, diseño aún abierto)

**Diseño confirmado en conversación, pendiente de construcción.** No es prioritaria.

- [ ] Nuevo campo en `products` o `product_variants`: `unidades_por_flete` (cuántas piezas de ese modelo caben en un viaje)
- [ ] Nueva tabla `price_tiers` o `distance_zones`: rango de distancia (km) + costo base de flete de esa zona
- [ ] Campo `distance_km` en `clients`, capturado manualmente por ahora (automatización vía mapa queda para después)
- [ ] Al registrar/editar un cliente, el sistema sugiere automáticamente su tier según su distancia
- [ ] Cálculo de precio final: `precio_base + (costo_flete_zona / unidades_por_flete_producto)`
- [ ] El precio final se muestra como un solo número — nunca desglosado como "flete"
- [ ] **Pendiente de decidir:** automatización del cálculo de distancia (Google Maps API u otro servicio)

---

## 🟣 FASE 6 — Reportes en PDF (actividad final, largo plazo)

- [ ] Reporte financiero en PDF (ingresos por rango de fechas)
- [ ] Reporte de cartera vencida en PDF
- [ ] Reporte de producción semanal en PDF (ya parcialmente cubierto por la vista imprimible de Production/Index)
- [ ] Reporte de embarques (qué se envió, cuándo, en qué viaje)

---

## 🐛 BUGS CONOCIDOS

1. **Logout "Inception"** — Login flotando al expirar sesión (ver Fase 1.2)
2. **HTML sucio en Sales/Index.vue** — Div mal cerrado (ver Fase 1.3)
3. **Input de moneda en Safari/iOS** — Permite caracteres no numéricos
4. **`formatDate` sin usar en Production/Index.vue** — Código muerto, limpiar

---

## 📌 Orden de Dependencias (importante)

```
Fase 1 (Deuda técnica)
   ↓
Fase 2.1 (Producción semanal) → Fase 2.2 (Piezas terminadas) → Fase 2.3 (Envíos parciales) → Fase 2.4 (Embarques)
   ↓
Fase 3 (Dashboards especializados) — usa datos de Fase 2 para el dashboard de Producción
   ↓
Fase 4 (Catálogo público) — independiente, se puede hacer en paralelo a Fase 2/3
   ↓
Fase 5 (Precios por flete) — depende de Fase 4.2 (link personalizado) para tener sentido
   ↓
Fase 6 (Reportes PDF) — depende de que Fase 3 (dashboards) ya tenga los datos consolidados
```

**Recomendación:** Fase 1 primero siempre. Fase 4 (catálogo) se puede paralelizar porque no depende de nada de producción/embarques. Fases 2 y 5 son las más complejas — vale la pena dedicarles sesiones completas y dedicadas.