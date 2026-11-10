# 🧠 CONTEXTO TÉCNICO — TALLER 360
**Versión Real:** 2.8 (sin cambio de versión en esta actualización) | **Sincronizado directamente contra el código fuente:** 07 de septiembre 2026 — **actualización documental del 29 de octubre 2026:** correcciones de contradicciones (marcadas `✅ Corregido 29 oct 2026`), schema real completo (sección 2), nueva sección **0.3** (Fases 2.7 Catálogos Administrables y 2.8 Devoluciones, 🔴 URGENTE, *Planeado, sin construir*) y auditorías de lectura (Anexos 0.3-X1 y 0.3-X2).
**Para:** Retomar desarrollo con IA o desarrollador nuevo sin perder contexto ni repetir trabajo ya completado.
**IMPORTANTE:** A diferencia de la versión anterior de este documento (que auditaba solo texto/reportes previos), esta versión se verificó línea por línea contra controladores, modelos, migraciones, rutas y componentes Vue reales. Compartir siempre este archivo al iniciar una nueva sesión.

---

## 0.2 Requerimientos de Producción — Octubre 2026 (MÁXIMA PRIORIDAD EN VIVO)

### A. Órdenes de Trabajo: Fabricación Autónoma sin Validación de Stock en Almacén
- **Problema en producción:** `ProductionController::index` calculaba faltantes restando el stock físico existente al requerimiento total (`total_needed - stock_fisico`). Al existir stock para cubrir pedidos, `pending_to_fabricate` caía a 0, ocultando el recuadro de captura de fabricación en `Production/Index.vue` y mostrando "Producción Completada".
- **Regla de Negocio:** Las órdenes de trabajo (`work_orders`) se emiten para fabricar stock para almacén sí o sí, sin importar el stock existente. Solo los pedidos de venta (`sale_details`) se cubren con el stock de almacén.
- **Solución Técnica:**
  - Faltantes de Pedidos: $\max(0, \text{pedidos\_total} - \text{stock\_disponible} - \text{detallado} - \text{enviados})$.
  - Faltantes de OT: $\max(0, \text{solicitado\_OT} - \text{completado\_OT})$ (estricto por orden de trabajo).
  - Total pendiente por fabricar del grupo: $\text{faltantes\_pedidos} + \text{faltantes\_OT}$.
  - Frontend (`Production/Index.vue`): La caja de captura de avance y botón `[✓]` para órdenes de trabajo permanece siempre activa mientras `completado_OT < solicitado_OT`, permitiendo ingresar piezas directo a stock y cerrar la OT automáticamente.

### B. Control Formal de Piezas Dañadas e Historial de Auditoría
- **Problema en producción:** No existe manera de marcar piezas dañadas en inventario. Si hay 7 piezas físicas y 1 está dañada, el sistema permite vender o embarcar las 7, provocando errores logísticos y de ventas.
- **Regla de Negocio:** El sistema debe conservar las 7 piezas en el conteo patrimonial/físico, pero registrar 1 dañada y mostrar únicamente 6 disponibles para venta, embarques y satisfacción de pedidos.
- **Solución Técnica:**
  - Columna en `product_variants`: `damaged_stock` (`unsignedInteger`, default 0).
  - Nueva tabla de auditoría: `damaged_stock_logs` (`id`, `product_variant_id`, `user_id`, `type`, `quantity`, `notes`, `timestamps`).
  - Tipos de movimiento:
    - `'marcar_danada'`: Pasa de Disponible a Dañada (+ `damaged_stock`).
    - `'reparada'`: Pasa de Dañada a Disponible (- `damaged_stock`).
    - `'baja_definitiva'`: Merma/desecho irreparable (- `damaged_stock` y - `stock` físico total).
  - Cálculo de Stock Disponible en `ProductVariant.php`:
    $$\text{available\_stock} = \max(0, \text{stock} - \text{reserved\_stock} - \text{damaged\_stock})$$
  - Frontend (`Products/Index.vue`): Desglose de stock (Físico | Dañadas | Disponible) y modal interactivo para registrar movimientos con su historial cronológico de auditoría.

### C. Control de Existencias Físicas por Color (Melamina) (COMPLETADO)
- **Problema en producción:** Artículos de Melamina se fabrican a partir de tableros que ya vienen con color de fábrica. No existe la melamina "natural". Si se crearan variantes separadas por color, el catálogo público y el POS se saturarían multiplicando artículos y precios innecesariamente. Sin embargo, almacén, producción y embarques necesitan saber con precisión cuántas piezas hay físicamente en bodega de cada color para no despachar un color equivocado ni dar por cubiertos pedidos de un color cuando solo hay stock de otro.
- **Regla de Negocio:**
  - El producto y la variante (`ProductVariant`) permanecen como entidad comercial única (Medidas, Material, Precios).
  - Exclusivo para `Melamina`: ~~se gestionan de forma fija e inmutable los 4 colores oficiales del cliente (`Blanco`, `Gris`, `Texturizado`, `Oyamel`). **⚠️ ACTUALIZACIÓN 29 oct 2026 (Fase 2.7, 🔴 URGENTE — Planeado, sin construir):** estos 4 colores dejan de ser fijos y pasan a ser un **catálogo administrable**...~~ **✅ CORRECCIÓN (Adenda 3, 30 oct 2026):** Los 4 colores (`Blanco`, `Gris`, `Texturizado`, `Oyamel`) **NO son colores de línea y NO "pasan a catálogo": fueron un ERROR de desarrollo**. Los **7 colores oficiales de Melamina** acordados con el cliente son: **Fresno Andino, Parota, Nogal Africano, Gris Cenizo, Gris Antracita, Tzalam y Moka** (coincidentes con la matriz de `catalogo/index.blade.php`). Antes de la Fase 2.7, se ejecutará la **Fase 0 — Hotfix colores de Melamina (🔴 PRIORIDAD MÁXIMA)** para alinear el código fuente a los 7 oficiales sin alterar schema. En la Fase 2.7 pasarán a ser administrables en base de datos.
  - La suma de los stocks por color se mantiene sincronizada con `product_variants.stock` (retrocompatibilidad total con el resto del sistema).
  - **Plan de Producción inteligente:** En `ProductionController@index` y `@printReport`, el cálculo de faltantes para Melamina se evalúa color por color (`color_breakdown`). Si un pedido solicita *Oyamel* y hay 0 en bodega, el sistema marca que falta fabricar 1 *Oyamel*, incluso si existen 6 piezas de *Blanco* en stock.
  - **Cola de Producción y Reporte de Taller:** En `Production/Index.vue` se visualiza el inventario físico por color, las piezas pendientes por fabricar de cada color y chips distintivos (`🎨 [Color]`). En `Production/Print.vue` se imprime el desglose de existencias y la instrucción explícita de corte para los carpinteros en la columna de notas (`[Fab: X Color]`).
  - **Taller (Completado de Producción):** En `ProductionController@storeCompletion`, al finalizar un lote se incrementa el stock del `chosen_color` del pedido o del `color` indicado en la orden de trabajo (`WorkOrder`).
  - **Embarques:** En `ShipmentController@store`, se valida disponibilidad atómica con `lockForUpdate` del color solicitado (`chosen_color`). Si no hay existencias del color requerido, se impide el despacho.
  - **Cancelación de Embarque:** En `ShipmentController@cancel`, el stock se restituye al color exacto en `variant_color_stocks`.
- **Estructura de Base de Datos:**
  - Tabla `variant_color_stocks`: `id`, `product_variant_id` (FK), `color` (string), `stock` (integer, default 0), `damaged_stock` (integer, default 0), `reserved_stock` (integer, default 0), `timestamps`, índice único `['product_variant_id', 'color']`.
  - Columna en `work_orders`: `color` (`string`, nullable).
- **Cobertura de Pruebas:** Suite completa en `tests/Feature/VariantColorStockTest.php` validando relaciones de modelo, incremento por avance de venta y de OT, bloqueo en embarques por discrepancia de color, deducción y reversión en cancelaciones, sincronización en CRUD de productos y cálculo inteligente en la cola de producción.

---

## 0.3 Requerimientos de Catálogos y Devoluciones — Noviembre 2026

> **Estado Actualizado:**
> 1) **Fase 0 — Hotfix colores de Melamina:** COMPLETADA Y VALIDADA ✅.
> 2) **Fase 2.7 — Catálogos Administrables:** COMPLETADA Y VALIDADA EN QA (114/114 tests en verde) ✅. Migraciones ejecutadas, modelos y relaciones Eloquent, módulo `/catalogs` (CRUD, asignaciones, descontinuado por material, candado de renombrado histórico), integración reactiva en Productos y POS, OTs y Embarques con `color_id`, alertas de stock remanente, vista compacta en taller y catálogo público Blade 100% dinámico.
> 3) **Fase 2.8 — Módulo de Devoluciones:** 🔴 SIGUIENTE EN ORDEN DE EJECUCIÓN (URGENTE). Dependía de la Fase 2.7 por operar con `color_id`.
> 4) **Fase 3.2 Cobranza: EN PAUSA** (esperando respuestas del cliente; solo se documenta estado y puntos de integración futura).
> 5) Fase 4.2 (catálogo por cliente), Fase 3 (dashboards por rol), Fase 5, Fase 6 y rendimiento en hosting (Fase 2.6/7).
> **Numeración:** `2.7` y `2.8` estaban libres (la numeración existente llegaba hasta 2.6); no hubo conflicto.

### 0.3-A. Fase 2.7 — Sprint Catálogos Administrables (COMPLETADO Y VALIDADO EN QA — NOVIEMBRE 2026) ✅

**Motivo:** hoy categorías, materiales por categoría y colores por material están hardcodeados (PHP, Vue, Blade). Los 4 colores previos de Melamina (`Blanco`, `Gris`, `Texturizado`, `Oyamel`) fueron un **error de desarrollo** que se subsana preliminarmente en la Fase 0 Hotfix (alineándolos a los 7 oficiales: `Fresno Andino`, `Parota`, `Nogal Africano`, `Gris Cenizo`, `Gris Antracita`, `Tzalam`, `Moka`). Además, un proveedor descontinuó acabados y agregó nuevos; el cliente necesita administrarlos dinámicamente en base de datos.

**Reglas de negocio acordadas (A1–A12):**
- **A1.** Se administran tres catálogos: **categorías, materiales y colores**.
- **A2.** Relaciones muchos a muchos: **categoría↔material** (un material puede estar en varias categorías) y **material↔color**. Los colores son **GLOBALES** (nombre y HEX); un mismo color puede usarse en varios materiales.
- **A3.** Solo **Melamina** tiene color obligatorio y stock por color (viene pintada de fábrica). En MDF, madera y demás materiales el color es opcional; natural = sin color.
- **A4.** **"Color de línea"** = color capturado en el catálogo de un material. **"Color especial"** = NO se cataloga: se pide en la venta usando la nota de la partida (`sale_details.custom_notes`) con costo extra (`sale_details.additional_cost`), **sin `color_id`**.
- **A5.** Descontinuar es **POR MATERIAL**, no global. La disponibilidad vive en la tabla pivote `material_color` (campo `activo`). Descontinuar Moka en Melamina NO afecta a Madera ni MDF. Además el color global conserva un interruptor "desactivar en todo el sistema". **Un color está disponible para un material solo si está activo en AMBOS niveles (global y pivote).**
- **A6.** Al descontinuar un color para un material: desaparece de catálogo público, POS, alta/edición de productos, órdenes de trabajo y producción nueva (se bloquea fabricarlo). **Pedidos existentes quedan intactos.** Embarques y cancelaciones de embarque siguen permitidos mientras quede stock de ese color. Alerta en Dashboard y en Productos: *"quedan X piezas de [Material] [Color] (descontinuado)"*. Cuando el stock llega a 0 el color queda solo como historial.
- **A7.** Si alguien aún necesita un color de línea descontinuado en MDF/madera, lo pide como **color especial** (nota + costo extra). En **Melamina NO se admite color especial**: solo colores de línea activos.
- **A8.** Renombrar colores: permitido **con candado**. Libre mientras el color no se haya usado en ningún lado; una vez usado (en cualquier material, pedido o stock) solo corrección tipográfica con confirmación. Para un cambio real: descontinuar y crear uno nuevo. Como los registros apuntarán a `color_id`, renombrar no rompe nada; los pedidos históricos conservan el nombre con el que se vendieron (**snapshot** en `sale_details.chosen_color`).
- **A9.** Permisos: **admin y supervisor** administran los catálogos. Se crea una sección propia **"Catálogos"** en el menú (el supervisor hoy NO tiene acceso a Configuración y eso no cambia).
- **A10.** Un producto cuya categoría o material esté desactivado se **oculta del catálogo público y del POS**, pero sigue visible en inventario interno.
- **A11.** Una variante solo puede usar materiales permitidos para la categoría de su producto (**validación en backend**).
- **A12. Fuente de Verdad del Seeder (RESUELTO por Adenda 3):** Los seeders deben cargar **EXACTAMENTE** la matriz oficial de 15 colores de `resources/views/catalogo/index.blade.php` (L437-442): `Chocolate`, `Caoba`, `Tabaco`, `Cherry`, `Nogal`, `Blanco`, `Gris`, `Gris Antracita`, `Gris Cenizo`, `Parota`, `Tzalam`, `Moka`, `Fresno Andino`, `Nogal Africano`, `258`, con sus respectivos valores HEX. Se descartan los HEX de `Sales/Create.vue` y `ProductCard.vue`. Los colores `Texturizado`, `Oyamel`, `Negro`, `Natural` y `Cedro` **NO se siembran**. Las relaciones categoría↔material (14 relaciones) y material↔color (17 relaciones) provienen de la hoja oficial del PM.

#### Catálogos Confirmados y Dudas Abiertas de la Fase 2.7:
- **Materiales confirmados (4 materiales):** El PM confirmó que `MDF`, `Madera`, `Madera y MDF enchapado` y `Melamina` son **cuatro materiales distintos** en la tabla `materials`. Se resuelve y retira la duda sobre si eran uno o dos materiales.
- **Regla firme e inviolable A11:** Tras la migración, **TODAS las variantes existentes deben quedar con material y categoría válidos** (la regla de negocio A11 no puede invalidar variantes preexistentes ni romper el catálogo).
- **Conteos del Seeder (Pendientes de Confirmar):**
  - Relaciones categoría↔material: 14 o más (según la decisión sobre las 46 variantes de Madera).
  - Relaciones material↔color: 17 o 21 (según si "Madera y MDF enchapado" tiene colores propios o comparte los 4 de Madera).
- **Dudas Abiertas Nuevas:**
  1. **Nomenclatura "Trincheros" vs "Trinchers":** En código y seeders actuales se nombra `'Trincheros'`; en la hoja oficial del PM figura `'Trinchers'`. Pendiente de definición del PM si se homologa al migrar.
  2. **Colores de "Madera y MDF enchapado":** ¿Comparte los mismos 4 colores de Madera (`Chocolate`, `Caoba`, `Tabaco`, `Cherry`) o tiene una lista propia de colores? (Define si el pivote `material_color` tiene 17 o 21 filas).
  3. **46 variantes existentes de "Madera" en Roperos (35) y Cómodas (11):** La hoja oficial del PM no contempla "Madera" como material válido para Roperos ni Cómodas. *Opción recomendada:* agregar la relación "Madera" a esas dos categorías en `category_material` (aumentando las relaciones categoría↔material de 14 a 16). Decisión pendiente del PM; no se toma por iniciativa propia.

**Regla de disponibilidad por material (descontinuado):** `color_disponible(material, color) = colors.activo AND material_color.activo`. Descontinuar = poner `material_color.activo = false` solo para ese material; el interruptor global `colors.activo = false` apaga el color en todo el sistema. Un color descontinuado con stock > 0 se sigue pudiendo **embarcar y cancelar embarque**, pero no se vende, no se fabrica ni se ofrece en alta de productos/OT.

**Modelo de datos (Fase 2.7) — PLANEADO, sin migraciones creadas:**
```
categories (ya existe: id, name, timestamps)  → AGREGAR: activo (boolean, default:true)

materials (NUEVA)
  id, name, activo (boolean, default:true),
  color_obligatorio (boolean), stock_por_color (boolean),   -- solo Melamina con ambas en true
  timestamps

colors (NUEVA)
  id, name, hex, activo (boolean, global, default:true), timestamps

category_material (NUEVA, pivote)
  category_id (FK), material_id (FK)

material_color (NUEVA, pivote)
  material_id (FK), color_id (FK), activo (boolean, descontinuado por material),
  orden (nullable), timestamps

product_variants (ya existe) → migrar `material` (texto) a `material_id` (FK),
  conservando el texto como snapshot donde aplique.
sale_details (ya existe)     → MANTENER `chosen_color` como snapshot de texto;
  AGREGAR `color_id` (nullable; NULL = color especial o sin color).
variant_color_stocks (ya existe) → migrar `color` (texto) a `color_id`.
work_orders (ya existe)          → migrar `color` (texto) a `color_id`.
```
> ⚠️ Hoy el índice único de `variant_color_stocks` es `['product_variant_id','color']`; al migrar debe pasar a `['product_variant_id','color_id']` (ver índices a revisar en el Anexo 0.3-X4).

**Fases (checklist en `BACKLOG.md`, Fase 2.7):** 1 Auditoría y línea base → 2 BD, modelos y seeders → 3 Pantalla "Catálogos" → 4 Productos y POS desde BD → 5 Producción/OT/Embarques con `color_id` (**FASE DE MAYOR RIESGO**, toca stock por color) → 6 Catálogo público, reporte de taller y alertas (partidas con color especial muestran la nota de forma visible, ej. *"Color especial: ver nota"*, en `Production/Print.vue` y en la cola de producción) → 7 Tests nuevos y regresión completa.

**Riesgos (2.7):** migración de texto a IDs en stock por color y órdenes de trabajo; pedidos históricos deben conservar snapshot de nombre; colores hardcodeados olvidados en algún archivo (ver Anexo 0.3-X1: 3 matrices de color distintas); catálogo público con caché (al desplegar en Neubox: `npm run build`, `config:cache`, `route:cache`, `view:cache`).

### 0.3-B. Fase 2.8 — Módulo de Devoluciones (🔴 URGENTE, segundo; estimado 2 a 3 sesiones)

**Ubicación:** submenú **"Devoluciones"** dentro de Embarques/Logística. Se construye **DESPUÉS de la fase 5 del módulo 2.7** (las devoluciones mueven stock por color y deben usar `color_id`).

**Reglas de negocio acordadas (B1–B10):**
- **B1.** Una devolución se registra **por partida (`sale_detail`)** de embarques con status `'entregado'` (incluye recolección en mostrador). Devolver un pedido completo = devolver todas sus partidas; está permitido.
- **B2.** Se registra: cliente (vía el pedido), pedido, partida, cantidad, motivo, destino de la pieza, comentario, usuario que captura, fecha y "Autorizado por".
- **B3.** **"Autorizado por"**: campo de **TEXTO LIBRE y OBLIGATORIO** (`authorized_by`). Solo registra quién autoriza; NO bloquea ni limita la devolución. Sin límites por monto. Sin plazo máximo para devolver tras la entrega.
- **B4. Destinos de la pieza (tres):**
  - **`danada`** (dañada / con detalle, reparable): **NO se usa `DamagedStockController` tal cual**. Se implementará una acción de servicio interna que, dentro de una transacción con `lockForUpdate`, sume a `product_variants.stock` Y a `product_variants.damaged_stock` (y en Melamina a `variant_color_stocks.stock` y `damaged_stock` del color real devuelto) y cree el registro en `damaged_stock_logs`. El rol **`inventario`** tiene autorización plena para registrar devoluciones dañadas sin requerir acceso a la ruta de `DamagedStockController`. Luego se repara con el flujo ordinario `'reparada'`.
  - **`baja`** (baja definitiva, irreparable): no regresa al inventario físico ni disponible; solo queda el registro de la devolución como merma.
  - **`color_equivocado`**: la pieza NO se reasigna ni se transfiere entre colores. Queda en el stock del color que REALMENTE tiene y el historial anota que el color solicitado no era el correcto. En Melamina el usuario indica el color real (obligatorio) y se suma a `variant_color_stocks` en stock físico. En otros materiales no se pide color; solo suma la pieza al inventario general.
- **B4-Bis. Cancelación de Embarques con Devoluciones:** Si un embarque tiene devoluciones registradas, **la cancelación del viaje queda estrictamente BLOQUEADA** con mensaje claro: *"No se puede cancelar un embarque que tiene devoluciones asociadas. Cualquier ajuste debe realizarse mediante el módulo de Devoluciones."*
- **B5.** Melamina: el color de la pieza devuelta es siempre obligatorio. Otros materiales: no se pide color.
- **B6.** Cantidad devuelta ≤ **entregado neto** (entregado − devoluciones previas). Es la única validación de cantidad.
- **B7.** Efecto en el pedido: entregado neto = entregado − devuelto. Si quedan piezas faltantes en alguna partida, el pedido regresa de `'entregado'` a `'produccion'` (pendiente por enviar), reutilizando la lógica de recálculo en vivo de `ShipmentController::cancel()`. El historial de stage lo registra `SaleObserver`. **NO crear `SaleHistory` manual**; el detalle de la devolución se audita directamente en `sale_return_items` (especialmente si el stage no cambia). *(Nota técnica: `ShipmentController::cancel` L365 hoy duplica `SaleHistory` manualmente; queda registrado como deuda técnica).*
- **B8.** **NO hay reenvío automático.** Producción decide en el Plan Taller cuándo se reenvía/repone. Para evitar que la fórmula $\max(\text{completed}, \text{detailed}, \text{delivered})$ enmascare la devolución, la regla técnica es: **restar de `completed` las piezas devueltas con destino `'danada'` o `'baja'`**, de modo que la partida reaparezca de forma inmediata como faltante a fabricar en la cola de producción (mientras que en `'color_equivocado'`, la pieza vuelve buena a stock del color real).
- **B9.** Permisos: **admin, supervisor e inventario** (el grupo de rutas `role:admin,inventario,supervisor` de Embarques ya coincide con esta matriz).
- **B10.** Por ahora **NO afecta saldos de clientes** (Cobranza está en pausa). Solo inventario e historial.

**Modelo de datos (Fase 2.8) — PLANEADO, sin migraciones creadas:**
```
sale_returns (NUEVA)
  id, folio, client_id (FK → clients), user_id (FK → users, quien captura),
  authorized_by (string, obligatorio), returned_at, notes (text, nullable), timestamps

sale_return_items (NUEVA)
  id, sale_return_id (FK → sale_returns), sale_detail_id (FK → sale_details),
  shipment_id (FK → shipments, nullable), quantity,
  reason_type, disposition ('danada' | 'baja' | 'color_equivocado'),
  color_id (FK → colors, nullable; obligatorio si Melamina),
  chosen_color (snapshot texto), notes (text, nullable), timestamps

damaged_stock_logs (ya existe) → se reutiliza para destino 'danada'.
```

**Fases (checklist en `BACKLOG.md`, Fase 2.8):** 1 Auditoría de lectura de "entregado" (ver Anexo 0.3-X2) → 2 BD y modelos → 3 Controlador transaccional con `lockForUpdate` (patrón de `ShipmentController::store`) → 4 UI: submenú Devoluciones en Embarques y registro visible en el detalle del pedido → 5 Integración con producción y embarques (todos los `withSum` de entregas restan devoluciones, igual que ya excluyen embarques cancelados) → 6 Tests y regresión completa.

**Riesgos (2.8):** cálculo de "entregado" omitido en algún punto (pedido cerrado indebidamente o sobreproducción); doble conteo entre cancelación de embarque y devolución; interacción con `production_hold` y con la fórmula MAX (detallado/enviados); depender de `color_id` (por eso va después de 2.7).

#### 🔮 Integración futura con Cobranza (NOTA — sin construir)
- La devolución guarda la partida original (`sale_return_items.sale_detail_id`) para poder calcular después el **valor devuelto = (`unit_price` con descuento × cantidad) + proporción de `additional_cost` de esa partida**, para nota de crédito o saldo a favor del cliente.
- Si la pieza devuelta se había vendido como **color especial** (nota con costo extra), **no toca `variant_color_stocks`, pero su `additional_cost` DEBE incluirse al calcular el valor devuelto en Cobranza.** *(Anotado explícitamente por instrucción del PM.)*
- **Fase 3.2 Cobranza: ⏸️ EN PAUSA** esperando respuestas del cliente (preguntas pendientes en `GUIA_RUTA.md`). Hoy no existe ningún controlador ni ruta de estado de cuenta (ver sección 9).

### 0.3-C. Resoluciones de Negocio y Hallazgos Auditados (Adenda 3 — el código real manda)

| # | Regla / premisa | Código real y estado de resolución (30 oct 2026) | Impacto / Solución adoptada |
|---|---|---|---|
| 1 | **A3** "color opcional en MDF/madera; natural = sin color" | `SaleController::store` valida `items.*.chosen_color` como `required\|string` (línea 106) para **cualquier** material, y `Sales/Create.vue` (línea 274) bloquea el pedido si alguna partida no tiene color. `sale_details.chosen_color` es nullable en BD pero obligatorio en validación. | En la Fase 0 Hotfix se mantiene la obligatoriedad para no romper validaciones intermedias. En la Fase 2.7 se relaja en backend y front: solo Melamina es obligatoria. |
| 2 | **A12** "cargar exactamente lo actual" | **✅ RESUELTO (Adenda 3):** Los 4 colores previos de Melamina (`Blanco`, `Gris`, `Texturizado`, `Oyamel`) **fueron un error de desarrollo**, no colores de línea. La **fuente de verdad oficial del Seeder** es la matriz de `catalogo/index.blade.php` (L437-442) con **15 colores**: `Chocolate`, `Caoba`, `Tabaco`, `Cherry`, `Nogal`, `Blanco`, `Gris`, `Gris Antracita`, `Gris Cenizo`, `Parota`, `Tzalam`, `Moka`, `Fresno Andino`, `Nogal Africano`, `258`. Melamina tiene 7 oficiales (`Fresno Andino`, `Parota`, `Nogal Africano`, `Gris Cenizo`, `Gris Antracita`, `Tzalam`, `Moka`). Se descartan los HEX de `Sales/Create.vue` y `ProductCard.vue`. `Texturizado`, `Oyamel`, `Negro`, `Natural` y `Cedro` **no se siembran**. | En la Fase 0 Hotfix se reemplazan en código las 4 constantes por los 7 oficiales. En Fase 2.7 se siembran exactamente los 15 colores oficiales. |
| 3 | **A11** validación categoría-material y **Materiales** | **✅ CONFIRMADO POR PM:** `MDF`, `Madera`, `Madera y MDF enchapado` y `Melamina` son 4 materiales distintos. En la BD real existen 35 variantes de `Madera` en Roperos y 11 en Cómodas que la hoja oficial no asocia a esas categorías. | **Dudas Abiertas Nuevas (Pendiente PM):** (a) Definir si "Madera y MDF enchapado" comparte los 4 colores de Madera o tiene lista propia (conteos de seeder: 17 o 21 material↔color); (b) Definir solución para las 46 variantes de Madera en Roperos/Cómodas (opción recomendada: agregar Madera a esas categorías en `category_material`, resultando 14 o más relaciones). **Regla firme A11:** tras migrar, TODAS las variantes existentes deben conservar categoría y material válidos. |
| 4 | **B1 / B4-Bis** cancelación con devoluciones | Si un embarque entregado tiene devoluciones parciales o totales registradas, cancelar el embarque en `Shipments/Index.vue` provocaría doble restitución de inventario. | **✅ DECISIÓN ADOPTADA:** Queda estrictamente BLOQUEADA la cancelación de cualquier viaje que tenga devoluciones asociadas. Los ajustes deben gestionarse vía Devoluciones. |
| 5 | **B4 `danada`** "reutilizar flujo de DamagedStockController" | `marcar_danada` asume que la pieza ya está en `available_stock` físico y solo suma a `damaged_stock` (no a `stock`). Además no toca `variant_color_stocks.damaged_stock` y su ruta es solo para `admin,supervisor`. | **✅ DECISIÓN ADOPTADA:** Se creará una acción de servicio interna con `lockForUpdate` que sume atómicamente a `product_variants.stock` Y a `damaged_stock` (y en Melamina a `variant_color_stocks.stock` y `damaged_stock` del color indicado) y cree el registro en `damaged_stock_logs`. El rol `inventario` podrá registrar devoluciones dañadas. |
| 6 | **B7** "historial lo registra `SaleObserver`, no crear `SaleHistory` manual" | `ShipmentController::cancel()` (línea 365) hoy crea un `SaleHistory` manual duplicando el registro del Observer. Si una devolución no altera el stage (ej. pedido que ya estaba en `produccion`), el Observer no registra nada. | **✅ DECISIÓN ADOPTADA:** NO crear `SaleHistory` manual en Devoluciones. El detalle de cada devolución se audita directamente en `sale_return_items`. Se documenta como deuda técnica la duplicación en `ShipmentController::cancel` L365. |
| 7 | **B8** "recalcula faltantes en taller" | La fórmula $\max(\text{completed}, \text{detailed}, \text{delivered})$ por partida mantendría enmascarado el faltante si `completed` no se reduce, ya que las piezas devueltas fueron previamente completadas en taller. | **✅ DECISIÓN ADOPTADA:** Restar de `completed` las piezas devueltas con destino `'danada'` o `'baja'` para que la partida reaparezca de inmediato como faltante a fabricar en el Plan Taller. Para `'color_equivocado'`, la pieza ingresa buena a stock del color real. Test obligatorio: devolución dañada reactiva faltante en partida. |
| 8 | **Bugs Latentes Auditados** | Se detectaron 3 bugs latentes en el código actual (se documentan en BACKLOG sin tocarlos en código todavía): | 1) `ProductionController::printReport` no calcula entregas (asume 0) y filtra solo stage 'produccion'.<br>2) `ShipmentController::store` suma `quantity_delivered` sin excluir embarques cancelados (L188-200).<br>3) `Sales/Create.vue` L274 y `SaleController` L106 exigen color obligatorio para todos los materiales. |

### 0.3-X1. Anexo — Auditoría de lectura Fase 2.7: dónde se hardcodean categorías, materiales y colores

| Archivo (líneas) | Qué se hardcodea | Qué cambia en 2.7 |
|---|---|---|
| `app/Http/Controllers/ProductController.php` (22, 43, 136, 52, 145, 60, 153, 92, 96-119, 192-225) | `material` como texto libre (`required\|string`); `Category::all()` sin filtro `activo`; `color_stocks[].color` texto sin validar contra catálogo; SKU con `substr(material)`; borrado de colores no recibidos (`whereNotIn('color')`). | Validar `material_id` contra `category_material`; `color_id` contra `material_color` activo; filtrar categorías/materiales activos; stock por color con `color_id`. |
| `app/Http/Controllers/SaleController.php` (37, 83, 106, 153, 157-159) | `chosen_color required\|string` para todos los materiales; sin validación color↔material; `product_name` con material en texto; POS recibe todos los productos. | Color obligatorio solo si `color_obligatorio`; guardar `color_id` + snapshot; rechazar color especial en Melamina; ocultar productos con categoría/material inactivo (A10). |
| `app/Http/Controllers/ProductionController.php` (114-121, 454-461, 322-330, 353-361) | Literal `'Melamina'` (`$isMelamina`, `hasColorTracking`) y `$defaultColors = ['Blanco','Gris','Texturizado','Oyamel']` (duplicado en `index` y `printReport`); `firstOrCreate` por `color` texto; `$usedColors` por `chosen_color`. | Usar `materials.stock_por_color` y colores activos del catálogo (más colores descontinuados con stock); incrementos por `color_id`; bloquear fabricar descontinuados; partidas de color especial sin tocar `variant_color_stocks`. |
| `app/Http/Controllers/ShipmentController.php` (81-85, 151-160, 176-177, 333-339) | `'Melamina'` y `firstWhere/where('color', chosen_color)`; `firstOrCreate` por texto al cancelar. | Resolver por `color_id`; permitir embarcar/cancelar con color descontinuado mientras haya stock; color especial no valida stock por color. |
| `app/Http/Controllers/WorkOrderController.php` (16, 23) | `color` nullable string libre. | `color_id` validado (activo y de Melamina si aplica). |
| `app/Http/Controllers/DashboardController.php` (63) | Alerta solo de favoritos por `stock <= min_stock`; selecciona `material` texto. | Agregar alerta "quedan X piezas de [Material] [Color] (descontinuado)". |
| `app/Http/Controllers/CatalogController.php` (17, 25, 32, 35) | Categorías con productos; materiales públicos salen del texto `product_variants.material`. | Filtrar categorías/materiales activos; colores públicos desde BD. |
| `app/Http/Controllers/LandingController.php` (15, 18-26) | `Category::all()`; sin hardcodes de color/material. | Filtrar categorías activas. |
| `resources/js/Pages/Products/Create.vue` (12-18, 42, 46, 49-50, 241-247, 277-284) | `categoryRules` (5 categorías → materiales), fallback `['MDF','Madera','Melamina','MDF Enchapado','Pino','Parota','Tzalam']`, `defaultMelaminaColors`, literal `'Melamina'`, texto "Melamina (4 Colores de Fábrica)". | Recibir categorías, materiales y colores del backend (Inertia props). |
| `resources/js/Pages/Products/Edit.vue` (16-22, 25, 37-46, 65-77, 97, 309-352) | Mismas matrices duplicadas de Create.vue. | Igual que Create.vue; extraer a una sola fuente. |
| `resources/js/Pages/Products/Index.vue` (22-23, 236, 251-255) | Muestra `material` y `cs.color` en texto. | Mostrar nombre desde relación; badge "descontinuado" con alerta. |
| `resources/js/Pages/Sales/Create.vue` (22-43, 45-49, 274, 433, 660) | `materialColors` con colores y HEX para MDF (5), Madera (4), Melamina (4) y `default`; `getColorsForMaterial` por `includes`; bloquea si falta color. | Colores por material desde BD; color opcional fuera de Melamina; flujo "color especial" con nota + costo extra. |
| `resources/js/Pages/Production/Index.vue` (77-78, 329, 376, 519-525) | Default `'Blanco'` para OT de Melamina; literal `'Melamina'`; `<option>` Blanco/Gris/Texturizado/Oyamel. | Opciones desde catálogo activo; mostrar nota de color especial en la cola. |
| `resources/js/Pages/Production/Print.vue` (56-74) | Literal `'Melamina'`; `[Fab: X color]` solo por `color_breakdown`. | Agregar "Color especial: ver nota" en partidas con color especial. |
| `resources/js/Pages/Shipments/Create.vue` (77-92, 329, 337) | Usa `available_color_stock` y `chosen_color` (texto). | Mantener; validar por `color_id`. |
| `resources/js/Components/ProductCard.vue` (4-18) | Tercera matriz `colorMap` (11 colores). **Componente sin ninguna importación** (código muerto). | Eliminar o alinear en 2.7. |
| `resources/views/catalogo/index.blade.php` (421-429, 431-435, 437-442, 494-517, 527) | `categoryMaterials` (claves en minúscula por coincidencia parcial: ropero, trinchero, cómoda, loker, recámara, comedor, base), `materialColors` (MDF 6, MADERA 4, MELAMINA 7), `colorHex` (15 colores), fallback `['MDF','Madera']`, borde especial a `'Blanco'`. | Pasar JSON desde `CatalogController`; eliminar matrices JS. Considerar caché. |
| `resources/views/landing/index.blade.php` (221) y `catalogo/index.blade.php` (102) | Texto comercial ("maderas seleccionadas, MDF y melaminas texturizadas"). | Solo copy; sin lógica. Revisar manualmente. |
| `database/seeders/InventarioSeeder.php` + `storage/app/import/*.csv` | Categorías (5) y variantes (materiales MDF/Madera/Melamina) se cargan de CSV; no existe tabla de materiales ni colores. | Nuevos seeders de categorías, materiales, relaciones y colores (A12). |
| `database/seeders/DatabaseSeeder.php` (74, 116) | `$possibleColors = ['Chocolate','Nogal','Blanco','Gris','Cherry','Tzalam','Moka']` para ventas demo. | Tomar colores del catálogo sembrado. |
| `database/factories/CategoryFactory.php` (13-17), `ProductVariantFactory.php` (24) | Lista de categorías; materiales aleatorios `['MDF','Madera','Melamina','Pino','Roble']`. | Ajustar factories a `material_id`. |
| `tests/Feature/VariantColorStockTest.php` (27-424) | Colores Blanco/Gris/Texturizado/Oyamel y `'Melamina'` como texto; test de cola de producción espera `color_breakdown.3.color == 'Oyamel'` (depende del orden fijo). | Reescribir fixtures con `color_id`; no depender del índice 3. |
| `tests/Feature/SaleAutoEmailTest.php`, `SaleControllerTest.php`, `SalesTest.php` | `chosen_color` con `'Rojo'`, `'Y'`, `'B'` sobre material `Madera`. | Ajustar si A3 exige validar contra catálogo. |
| `tests/Feature/ProductTest.php` (28), `CatalogTest.php` (88), `DetalladoConcurrencyTest.php` (26), `ShipmentControllerTest.php` (146) | `material` como texto (`Madera`, `MDF`). | Pasar a `material_id`. |

### 0.3-X2. Anexo — Auditoría de lectura Fase 2.8: dónde se calcula "entregado" y deberá restar devoluciones

> **Definición real hoy:** `delivered_quantity = SUM(sale_deliveries.quantity_delivered)` de embarques cuyo `status != 'cancelado'` (incluye `en_transito`). El stage `entregado` del pedido solo se alcanza al cubrir el 100% de **todas** las partidas.

| Archivo (líneas) | Qué calcula | Cambio requerido |
|---|---|---|
| `ShipmentController::create` (50-53, 73-77, 188) | `delivered_quantity` por partida; `detailed_quantity = max(0, detallado − entregado)`; `pending = quantity − delivered`; qué pedidos son embarcables. | Restar devoluciones del entregado; el pedido devuelto reaparece como embarcable solo si Producción decide reponer (B8). |
| `ShipmentController::index` | Listado de viajes (sin cálculo de entregado). | Sin cambio de fórmula; mostrar devoluciones asociadas opcional. |
| `ShipmentController::show` (215-231) | Muestra `quantity_delivered` por viaje. | Mostrar devoluciones del viaje (informativo). |
| `ShipmentController::store` (188-200) | `totalDelivered` (sin filtro de cancelados) para `production_hold`; cierra pedido con `closeOrderIfComplete`. | Restar devoluciones; revisar `production_hold`. Nota: este `sum` **no excluye embarques cancelados**. |
| `ShipmentController::closeOrderIfComplete` (276-288) | `every(delivered >= quantity)` → stage `entregado`/`enviado`. | Restar devoluciones del entregado. |
| `ShipmentController::cancel` (351-363) | Recalcula `delivered < quantity` en vivo; regresa a `produccion`; crea `SaleHistory` manual (365). | Restar devoluciones (evitar doble conteo con la cancelación); decidir qué pasa si se cancela un embarque con piezas ya devueltas. |
| `SaleController::show` (213-215) | `delivered_quantity` a `Sales/Show.vue`. | Restar devoluciones y enviar el listado de devoluciones. |
| `SaleController::sendToDetallado` (260-266) | `maxPermitido = quantity − detallado − entregado`. | Restar devoluciones del entregado. |
| `ProductionController::index` (35-39, 60, 110-111, 130-136, 163-165, 183, 192, 221, 272-276) | `delivered_quantity` por partida y pausadas; `faltantes = quantity − max(completed, detailed, delivered)`; `salesDelivered`, `wipDetailed`, `pendingSales` (global y por color), `totalDelivered`. | Restar devoluciones; resolver el efecto de `max(completed, …)` (ver 0.3-C #7). |
| `ProductionController::printReport` (384-394, 450-543) | **No carga `delivered_quantity`** (siempre 0) y filtra solo `stage = 'produccion'`; usa la misma fórmula MAX. | Agregar `withSum` de entregas netas de devoluciones y alinear con `index`. |
| `resources/js/Pages/Production/Index.vue` (257-258, 352, 419, 442, 457) | Recalcula en JS `quantity − max(completed, detailed, delivered)`; "Enviados" = `total_delivered`. | Consumir el valor neto del backend; no recalcular en cliente. |
| `resources/js/Pages/Sales/Show.vue` (53-54, 239-256) | `maxLogico`, "entregado", ✓ Completado, disponible vs pendiente. | Mostrar neto y las devoluciones. |
| `resources/js/Pages/Shipments/Create.vue` (75, 89, 107, 334) | `pending = quantity − delivered_quantity` en JS. | Usar neto del backend. |
| `resources/js/Pages/Shipments/Show.vue` (66-74), `Shipments/Index.vue` (45-46, 87-90) | Cantidades por viaje; cancelación de mostrador post-`entregado`. | Informativo; definir si se puede cancelar un embarque con devoluciones. |
| `resources/views/pdf/shipment_manifest.blade.php` (235-238) | `quantity_delivered` por entrega. | Sin cambio (la remisión documenta lo enviado). |
| `app/Http/Controllers/DashboardController.php` (47-48) | Contadores `produccion`/`enviado` por `stage`. | Sin cambio de fórmula; el pedido devuelto cae a `produccion`. |

### 0.3-X3. Anexo — Listas literales hardcodeadas (Módulo 2.7) y diferencias entre copias

| Dato | Literal exacto | Dónde vive | ¿Duplicado con diferencias? |
|---|---|---|---|
| Categorías (5) | `Roperos`, `Trincheros`, `Cómodas y Lokers`, `Recámaras y comedores`, `Bases` | `storage/app/import/categories.csv`; `CategoryFactory.php` 13-17; claves de `categoryRules` en `Products/Create.vue` 12-18 y `Edit.vue` 16-22; claves **en minúscula parcial** en `catalogo/index.blade.php` 421-429 | Sí: el Blade usa subcadenas (`ropero`, `trinchero`, `cómoda`, `loker`, `recámara`, `comedor`, `base`) y no coincide por nombre exacto. |
| Materiales por categoría | Roperos, Cómodas y Lokers, Recámaras y comedores: `MDF`, `Madera y MDF enchapado`, `Melamina`. Trincheros: `Madera`, `Madera y MDF enchapado`, `MDF`. Bases: `MDF`, `Madera`. | `Products/Create.vue` 12-18; `Products/Edit.vue` 16-22; `catalogo/index.blade.php` 421-429 | Mismo contenido en las 3 copias. **Fallback distinto:** Vue `['MDF','Madera','Melamina','MDF Enchapado','Pino','Parota','Tzalam']` (Create 42 / Edit 97); Blade `['MDF','Madera']` (503). |
| Materiales reales en datos semilla | `MDF` (87), `Madera` (59), `Melamina` (35) | `storage/app/import/product_variants.csv` | `Madera y MDF enchapado` y `MDF Enchapado` no existen en los datos. |
| **Colores oficiales Melamina (7)** | `Fresno Andino`, `Parota`, `Nogal Africano`, `Gris Cenizo`, `Gris Antracita`, `Tzalam`, `Moka` | `resources/views/catalogo/index.blade.php` L434. (~~`Blanco`, `Gris`, `Texturizado`, `Oyamel`~~ en `ProductionController`, `Production/Index.vue`, `Products/Create.vue`, `Products/Edit.vue`, `Sales/Create.vue` fueron un **ERROR de desarrollo** que se corregirá en la Fase 0 Hotfix). | **Alineación:** La Fase 0 Hotfix alineará el código a estos 7 oficiales sin tocar schema. La Fase 2.7 los sembrará en la tabla `colors` y pivote `material_color`. |
| Colores POS (`Sales/Create.vue` 22-43) | MDF: `Chocolate #5D4037`, `Blanco #F5F5F5`, `Gris #9E9E9E`, `Nogal #8D6E63`, `Negro #212121`. Madera: `Natural #D7CCC8`, `Caoba #4E342E`, `Nogal #5D4037`, `Cedro #A1887F`. Melamina: ~~`Blanco #FFFFFF`, `Gris #BDBDBD`, `Texturizado #EFEBE9`, `Oyamel #D7CCC8`~~. Default: `Estándar #CCCCCC`. | `Sales/Create.vue` | En la Fase 0 Hotfix se alineará MDF y Madera a la lista oficial (MDF: Chocolate, Nogal, Blanco, Gris, Cherry, 258; Madera: Chocolate, Caoba, Tabaco, Cherry) con HEX del Blade. |
| **Fuente de Verdad del Seeder: Matriz HEX oficial (15)** | `Chocolate #3B2314`, `Caoba #4A1525`, `Tabaco #422A1D`, `Cherry #722F37`, `Nogal #5C3A21`, `Blanco #FFFFFF`, `Gris #9CA3AF`, `Gris Antracita #374151`, `Gris Cenizo #6B7280`, `Parota #8B5A2B`, `Tzalam #6F4E37`, `Moka #4A3728`, `Fresno Andino #D4C4A8`, `Nogal Africano #50382B`, `258 #A08A75` | `catalogo/index.blade.php` 437-442 | **Fuente de Verdad oficial (Adenda 3):** Se descartan los HEX de `Sales/Create.vue` y `ProductCard.vue`. `Texturizado`, `Oyamel`, `Negro`, `Natural` y `Cedro` NO se siembran. |
| Matriz huérfana `ProductCard.vue` 4-18 | `Chocolate #5D4037`, `Nogal #8D6E63`, `Blanco #FFFFFF`, `Gris #9E9E9E`, `Gris cenizo #B0BEC5`, `Cherry #880E4F`, `Tabaco #4E342E`, `Caoba #3E2723`, `Negro #000000`, `Tzalam #A1887F`, `Parota #795548`, default `#E0E0E0` | `ProductCard.vue` (no se importa en ningún archivo) | Tercera copia, con HEX distintos. |
| Colores demo de seeder | `Chocolate`, `Nogal`, `Blanco`, `Gris`, `Cherry`, `Tzalam`, `Moka` | `DatabaseSeeder.php` 74 | Distinto de las demás. |

**Combinaciones categoría + material presentes en los datos semilla** (`storage/app/import`, 89 productos, 181 variantes): Roperos MDF 64 / Madera 35 / Melamina 26; Cómodas y Lokers MDF 11 / Madera 11 / Melamina 9; Trincheros MDF 12 / Madera 12; Bases Madera 1; Recámaras y comedores: 0 variantes. *(Son los datos de importación inicial; la base de producción puede diferir — ver consultas del Anexo 0.3-X5.)*

### 0.3-X4. Anexo — Schema real (leído de migraciones, 29 oct 2026) y índices a revisar

Las 12 tablas solicitadas están descritas en la sección 2 (actualizada). Resumen de **índices reales**: MySQL crea índice automático por cada FK; los únicos índices explícitos son `settings.key` (unique), `users.email` (unique) y `variant_color_stocks` unique `['product_variant_id','color']`. **No existe ningún otro índice** (no hay índice en `sales.stage`, `shipments.status`, `work_orders.status` ni `product_variants.material`).

| Índice faltante / a revisar | Motivo para la migración a IDs |
|---|---|
| `materials.name` (unique), `colors.name` (unique), `categories.name` (unique) | Evitar duplicados al sembrar y al renombrar (A8). |
| `category_material` PK/unique `['category_id','material_id']`; `material_color` PK/unique `['material_id','color_id']` | Integridad de pivotes M:N. |
| `product_variants.material_id` (FK + índice) | Reemplaza la columna texto sin índice. |
| `variant_color_stocks` unique `['product_variant_id','color_id']` (reemplaza el de `color`) | Mantener unicidad tras migrar a `color_id`. |
| `variant_color_stocks.color_id`, `work_orders.color_id`, `sale_details.color_id` (FK + índice) | Consultas "¿se usó este color?" (candado de renombrado A8) y alertas de descontinuados. |
| `work_orders.status` | La cola de producción filtra `whereIn('status', ['pending','in_progress'])` en cada carga. |
| `shipments.status` | Todos los `withSum` de entregas usan `whereHas('shipment', status != 'cancelado')`. |
| `sale_deliveries` compuesto `['sale_detail_id','shipment_id']` | Subconsulta de `withSum` por partida. |
| `sales.stage` (+ `promised_date`) | Filtros de producción, embarques y dashboard (ya pendiente en Fase 2.6/7). |
| `sale_return_items.sale_detail_id` y `sale_returns.client_id` (FK + índice) | Módulo 2.8: suma de devoluciones por partida en cada `withSum`. |
| Verificar en producción `sale_details.sale_id`, `sale_details.product_variant_id`, `production_completions.sale_detail_id/work_order_id` | Cubiertos por FK; confirmar con `SHOW INDEX`. |

### 0.3-X5. Anexo — Consultas SELECT de solo lectura para correr en producción (Ajustadas a 7 Colores Oficiales)

> Solo `SELECT`. Valores canónicos de Melamina: `'Fresno Andino'`, `'Parota'`, `'Nogal Africano'`, `'Gris Cenizo'`, `'Gris Antracita'`, `'Tzalam'`, `'Moka'`. Comparaciones binarias para detectar mayúsculas/espacios.

```sql
-- 1) Materiales distintos y conteo general
SELECT material, COUNT(*) AS variantes FROM product_variants GROUP BY material ORDER BY variantes DESC;

-- 1b) Materiales fuera de la lista esperada o con espacios/mayúsculas distintas
SELECT material, COUNT(*) AS n FROM product_variants
WHERE material COLLATE utf8mb4_bin NOT IN ('MDF','Madera','Melamina') OR CHAR_LENGTH(material) <> CHAR_LENGTH(TRIM(material))
GROUP BY material;

-- 2) chosen_color distintos por material y conteo (LISTADO COMPLETO SIN FILTROS)
SELECT v.material, d.chosen_color, COUNT(*) AS partidas, SUM(d.quantity) AS piezas
FROM sale_details d JOIN product_variants v ON v.id = d.product_variant_id
GROUP BY v.material, d.chosen_color ORDER BY v.material, partidas DESC;

-- 2b) Melamina con color fuera de los 7 oficiales (detecta los 4 colores erróneos del desarrollo previo o NULL)
SELECT d.id AS sale_detail_id, d.sale_id, d.product_name, d.chosen_color 
FROM sale_details d JOIN product_variants v ON v.id = d.product_variant_id
WHERE v.material = 'Melamina' 
  AND (d.chosen_color IS NULL OR d.chosen_color COLLATE utf8mb4_bin NOT IN (
    'Fresno Andino', 'Parota', 'Nogal Africano', 'Gris Cenizo', 'Gris Antracita', 'Tzalam', 'Moka'
  ));

-- 2c) Materiales no Melamina con color (para evaluar color de línea vs color especial)
SELECT v.material, d.chosen_color, COUNT(*) AS partidas 
FROM sale_details d JOIN product_variants v ON v.id = d.product_variant_id
WHERE v.material <> 'Melamina' AND d.chosen_color IS NOT NULL 
GROUP BY v.material, d.chosen_color ORDER BY partidas DESC;

-- 3) variant_color_stocks.color y work_orders.color (LISTADO COMPLETO SIN FILTROS)
SELECT color, COUNT(*) AS registros_variante, SUM(stock) AS stock_total, 
       SUM(damaged_stock) AS total_danadas, SUM(reserved_stock) AS total_apartadas 
FROM variant_color_stocks GROUP BY color;

SELECT color, status, COUNT(*) AS total_ot, SUM(quantity_requested) AS total_piezas 
FROM work_orders GROUP BY color, status;

-- 3b) Colores fuera de los 7 oficiales en existencias por color / OT (detecta existencias en Blanco, Gris, Texturizado, Oyamel)
SELECT 'variant_color_stocks' AS tabla, color, COUNT(*) AS filas, SUM(stock) AS stock_afectado 
FROM variant_color_stocks 
WHERE color COLLATE utf8mb4_bin NOT IN (
  'Fresno Andino', 'Parota', 'Nogal Africano', 'Gris Cenizo', 'Gris Antracita', 'Tzalam', 'Moka'
) GROUP BY color
UNION ALL
SELECT 'work_orders' AS tabla, color, COUNT(*) AS filas, SUM(quantity_requested) AS piezas_afectadas 
FROM work_orders 
WHERE color IS NOT NULL AND color COLLATE utf8mb4_bin NOT IN (
  'Fresno Andino', 'Parota', 'Nogal Africano', 'Gris Cenizo', 'Gris Antracita', 'Tzalam', 'Moka'
) GROUP BY color;

-- 4) Variantes Melamina: stock físico vs suma de colores y variantes sin filas de color
SELECT v.id AS variant_id, v.stock AS stock_variante, COALESCE(SUM(c.stock),0) AS suma_colores,
       (v.stock - COALESCE(SUM(c.stock),0)) AS discrepancia
FROM product_variants v LEFT JOIN variant_color_stocks c ON c.product_variant_id = v.id
WHERE v.material = 'Melamina' GROUP BY v.id, v.stock HAVING v.stock <> suma_colores;

-- 4b) Variantes de Melamina con stock > 0 y piezas apartadas (reserved_stock > 0)
SELECT v.id AS variant_id, p.name AS producto, v.measurements, v.stock, v.reserved_stock, v.damaged_stock
FROM product_variants v
JOIN products p ON p.id = v.product_id
WHERE v.material = 'Melamina' AND (v.stock > 0 OR v.reserved_stock > 0)
ORDER BY v.stock DESC;

-- 4c) Filas de color en variantes que NO son Melamina
SELECT v.material, COUNT(*) AS filas 
FROM variant_color_stocks c JOIN product_variants v ON v.id = c.product_variant_id 
WHERE v.material <> 'Melamina' GROUP BY v.material;

-- 5) Combinaciones categoría + material reales (para validar A11 y duda de Madera)
SELECT c.name AS categoria, v.material, COUNT(*) AS variantes
FROM product_variants v JOIN products p ON p.id = v.product_id JOIN categories c ON c.id = p.category_id
GROUP BY c.name, v.material ORDER BY c.name, v.material;

-- 6) Inspección de índices reales
SHOW INDEX FROM variant_color_stocks; 
SHOW INDEX FROM sale_details; 
SHOW INDEX FROM work_orders;
SHOW INDEX FROM sale_deliveries; 
SHOW INDEX FROM shipments; 
SHOW INDEX FROM sales;
```

---

## 0.4 Fase 0 — Hotfix Colores de Melamina y Paleta POS (✅ COMPLETADO — Noviembre 2026)

> **Estado:** Implementado, verificado con suite de pruebas automatizadas (81/81 tests en verde) y validado en QA local.
> Este hotfix corrigió el error de desarrollo donde se codificaron 4 colores incorrectos (`Blanco`, `Gris`, `Texturizado`, `Oyamel`), sustituyéndolos por los **7 colores oficiales de Melamina** acordados con el cliente:
> `Fresno Andino`, `Parota`, `Nogal Africano`, `Gris Cenizo`, `Gris Antracita`, `Tzalam`, `Moka`.
> Además, se blindó la paleta en el POS para que `"Madera y MDF enchapado"` use la paleta de Madera y no caiga por coincidencia de subcadena en MDF.
>
> 1. [x] **`app/Http/Controllers/ProductionController.php` (L119, L459):** Reemplazado `$defaultColors` por los 7 oficiales en cola de producción y reporte impreso.
> 2. [x] **`resources/js/Pages/Production/Index.vue`:**
>    - En L77-78: Eliminado el color por defecto `'Blanco'` para Melamina en el formulario de Orden de Trabajo (`workOrderForm.color = ''`).
>    - En L522-530: Reemplazados los `<option>` por placeholder obligatorio y los 7 colores oficiales de Melamina.
> 3. [x] **`resources/js/Pages/Products/Create.vue` (L46) y `Edit.vue` (L25):** Reemplazado `defaultMelaminaColors` por los 7 oficiales y actualizado badge a "7 Colores de Fábrica".
> 4. [x] **`resources/js/Pages/Sales/Create.vue` (L22-43):**
>    - Melamina (L36-41): Reemplazado por los 7 oficiales con sus códigos HEX canónicos del Blade (`Fresno Andino #D4C4A8`, `Parota #8B5A2B`, `Nogal Africano #50382B`, `Gris Cenizo #6B7280`, `Gris Antracita #374151`, `Tzalam #6F4E37`, `Moka #4A3728`).
>    - MDF: Alineado a los 6 oficiales del Blade con sus HEX (`Chocolate #3B2314`, `Nogal #5C3A21`, `Blanco #FFFFFF`, `Gris #9CA3AF`, `Cherry #722F37`, `258 #A08A75`).
>    - Madera: Alineado a los 4 oficiales del Blade con sus HEX (`Chocolate #3B2314`, `Caoba #4A1525`, `Tabaco #422A1D`, `Cherry #722F37`).
>    - En `getColorsForMaterial` (L45-51): Blindado con coincidencia prioritaria hacia `'Madera'` para garantizar que `"Madera y MDF enchapado"` utilice siempre la paleta de Madera y no caiga en MDF.
> 5. [x] **`tests/Feature/VariantColorStockTest.php`:** Actualizado con los 7 colores oficiales y ruta `/production-plan`, confirmando la suite al 100% en verde.

---

---

## 0.1 Acuerdos de la reunión con cliente (04 ago 2026) — pendientes de construir

Estos 5 puntos salieron de una reunión con el cliente y ya tienen diseño técnico acordado con el desarrollador. Van en orden de prioridad para la siguiente ronda de código.

### A. Supervisor con permisos completos en Producción, Almacén y Embarques (COMPLETADO)
- **Rutas y Middleware:** En `routes/web.php`, se agregó el rol `supervisor` a los grupos de middleware `role:admin,produccion,supervisor` y `role:admin,inventario,supervisor`.
- **Inventario:** Se extrajeron las rutas de `products` (CRUD y favoritos) de la zona exclusiva de admin hacia un nuevo grupo compartido `role:admin,supervisor`.
- **Restricciones:** El supervisor tiene prohibido el acceso a Clientes, Configuración, Usuarios y Ventas (corroborado vía tests con `assertForbidden()`).
- **Frontend:** Se validó que no existen directivas `v-if` restrictivas por rol en los componentes Vue involucrados, por lo que los botones y acciones operan de forma nativa basados en el acceso del backend.


### B. Bug — pedido "entregado" cancelado cae en limbo (`enviado` invisible) (COMPLETADO)
- **Causa raíz solucionada:** Se eliminó la lectura estática a `SaleHistory` en `ShipmentController::cancel()` reemplazándola por un recálculo en vivo de piezas entregadas. Al cancelar un viaje, si un pedido previamente "entregado" aún tiene piezas pendientes en alguna de sus líneas, la etapa vuelve de forma dinámica a `produccion`.
- **Limpieza de Historial:** Se suprimió la creación manual y duplicada de registros de `SaleHistory` en el método `store()`, consolidando a `SaleObserver` como la única fuente de verdad para el tracking de etapas.
- **Fix Secundario (Modelo):** Se añadió el campo `pickup_type` al array `$fillable` del modelo `Shipment` para garantizar el guardado del tipo de entrega y evitar choques con validaciones de flota propia al cancelar remisiones de mostrador.


### C. Órdenes de Trabajo — producción sin pedido + pausa de remanentes parciales (COMPLETADO)
**Necesidad del cliente:** (1) puede producir por anticipado sin que exista un pedido todavía (conoce la demanda de temporada), y (2) cuando un envío parcial deja piezas sin fabricar, no quiere que el sistema las marque automáticamente como urgentes en el taller — quiere decidir él cuándo se fabrica el remanente.

**Diseño acordado — nueva tabla `work_orders`:**
```
id, product_variant_id (FK), quantity_requested (int),
target_date (date, nullable), status (string: ~~'abierta'|'cerrada', default:'abierta'~~ ✅ Corregido 29 oct 2026 → valores reales en código y migración: 'pending'|'in_progress'|'completed', default:'pending'),
origin_sale_detail_id (FK → sale_details, nullable — si nace de un remanente parcial),
notes (text, nullable), created_by (FK → users), timestamps
```
- Aparece en el Plan de Producción mezclada con las necesidades de pedidos normales (mismo query, unión de dos fuentes).
- Al cerrarla se registra cuánto se terminó realmente y esas piezas entran directo a `product_variants.stock` — mismo mecanismo que ya usa `production_completions`, pero sin requerir una venta.
- `production_completions.sale_detail_id` pasa a ser nullable; se agrega `work_order_id` (FK, nullable) — una fila de avance pertenece a una u otra fuente, nunca a ambas.

**Diseño acordado — pausa de remanentes (`sale_details.production_hold`):**
```
sale_details.production_hold (boolean, default: false)
```
- Se activa automáticamente la primera vez que una línea recibe un envío parcial (queda pieza sin entregar Y sin fabricar todavía).
- Mientras está en `true`, `ProductionController::index()` excluye esa cantidad remanente de "pendiente de fabricar" — aparece en una sección aparte ("en espera de decisión"), no mezclada con lo urgente.
- Cualquier usuario con acceso a Producción (`role:admin,produccion,supervisor` tras el cambio A) puede "liberar a producción" — apaga el flag y/o genera un `work_order` con `origin_sale_detail_id` apuntando a esa línea.

### D. Stock mínimo por variante, solo para productos preferentes (COMPLETADO)
**Decisión:** el campo nuevo vive en `product_variants`, no en `products` — el stock siempre se ha manejado por variante (confirmado: la alerta actual de Dashboard ya consulta `ProductVariant`, no `Product`). "Preferente" sigue siendo el `is_favorite` que ya existe; no se crea concepto nuevo, solo se usa como filtro de visibilidad del campo.
```
product_variants.min_stock (int, nullable)
```
- El formulario de variantes solo pide/muestra `min_stock` cuando el producto padre tiene `is_favorite = true`.
- La alerta de stock crítico del Dashboard cambia de `stock <= 5` (fijo) a `stock <= COALESCE(min_stock, 5)` — mantiene el comportamiento actual como default si un producto se marca preferente sin definir aún su mínimo por variante.
- Cambiar de temporada es simplemente desmarcar/marcar `is_favorite` — no requiere migrar ni tocar `min_stock` de variantes que ya no importan.

### E. Envío automático de la nota de venta al crear el pedido (COMPLETADO)
- **Configuración (Settings):** Se agregó la clave `auto_email_on_sale` (boolean, default `true`) a `$allowedKeys` en `SettingController`. Determina el envío automático de correos en el POS.
- **Ventas:** En `SaleController`, se refactorizó el envío de correos al método `private function sendSaleNoteMail(Sale $sale)`. En el método `store()`, se llama al envío de correos fuera del bloque de transacción (`DB::transaction`) y envuelto en un `try-catch` (`Log::error()`) para evitar que fallas SMTP aborten el pedido.
- **Testing (Línea Base):** Configurado `phpunit.xml` a base de datos `:memory:` (SQLite). Creadas suites de Feature Tests para asegurar comportamiento del middleware de roles (`RoleMiddlewareTest`), control de transiciones y ventas (`SaleControllerTest`), administración y consistencia del inventario (`ShipmentControllerTest`) y disparo del sistema de correos (`SaleAutoEmailTest`).

## Arquitectura de Correos y PDFs (Producción)
**Fecha:** 2026-08-13
**Decisión:** Uso de procesamiento diferido síncrono (`afterResponse`) vs. Colas de Base de Datos.
**Contexto:** Debido a las restricciones de ejecución persistente y configuración de binarios PHP en hostings compartidos (DirectAdmin/cPanel) que dificultan mantener un Worker activo, se descartó el uso del driver `database` para las colas.
**Implementación:**
1. **Frontend:** Se bloquea el botón de envío en Vue.js tras el primer clic para evitar el doble submit por parte del usuario.
2. **Controlador:** Los BLOBs pesados de firmas se guardan temporalmente como archivos físicos (`storage/app/public`) y los assets locales (logos) se inyectan como Base64 para evadir las restricciones `chroot` de seguridad de DOMPDF.
3. **Despacho Diferido:** La generación intensiva del PDF y la conexión SMTP se ejecutan encapsuladas dentro de `dispatch()->afterResponse()`. Esto libera al usuario en milisegundos entregando el código HTTP 200, mientras el servidor finaliza la tarea pesada en segundo plano de manera segura.

## 0. Hallazgos de la ronda 2 de auditoría (25 jul 2026, con `UserController.php`, `package.json`, `vite.config.js`)

### ✅ Cerrado — `UserController.php` sí existe
La ronda 1 marcó como crítico que `UserController.php` no viniera en el zip pese a estar referenciado en `routes/web.php`. Confirmado con el archivo real: existe, CRUD completo (`index`, `create`, `store`, `edit`, `update`, `destroy`), con protección contra auto-eliminación (`if (auth()->id() == $user->id)`) y `VALID_ROLES = 'admin,vendedor,produccion,inventario,supervisor,financiero'` — coincide exactamente con los 6 roles usados en el resto del sistema. `User::$fillable` incluye `role`. Sin pendientes aquí.

### ✅ Tailwind CSS v4 — Migración Completada

El conflicto de versiones se resolvió satisfactoriamente migrando por completo a la v4. 
- Se eliminaron `postcss.config.js` y `tailwind.config.js`.
- Se desinstalaron las dependencias de `postcss` y `autoprefixer`.
- `app.css` ahora utiliza la nueva sintaxis nativa (`@import "tailwindcss"`, `@plugin "@tailwindcss/forms"` y `@theme` para la fuente Figtree).
- `vite.config.js` ahora registra e invoca directamente el plugin de Tailwind oficial.

---

## 1. Stack Tecnológico

| Capa | Tecnología | Confirmado en |
|------|-----------|-------|
| Backend | Laravel 12.62.0 (PHP 8.2+) | `composer.lock` |
| Frontend | Vue `^3.4.0` (`<script setup>`) | `package.json` |
| Puente | Inertia.js `@inertiajs/vue3 ^2.0.0` | `package.json`, sin rutas `/api/` en `routes/web.php` |
| Build | Vite `^7.0.7` + `laravel-vite-plugin ^2.0.0` + `@vitejs/plugin-vue ^6.0.7` | `package.json`, `vite.config.js` |
| Estilos | Tailwind CSS `^4.3.3` (+ `@tailwindcss/vite ^4.3.3`, `@tailwindcss/forms ^0.5.3`) — ✅ Corregido 29 oct 2026 (antes decía `^4.0.0`) | `package.json`, `resources/css/app.css`, `vite.config.js` |
| BD | MySQL / MariaDB InnoDB | migraciones |
| PDFs | barryvdh/laravel-dompdf 3.1.2 (dompdf/dompdf 3.1.5) | `composer.lock`, confirmado exacto |
| Firma Digital | vue-signature-pad `^3.0.2` | `package.json`, `Sales/Create.vue` |
| Alertas | sweetalert2 `^11.26.17` | `package.json` |
| Utilidades JS | lodash `^4.17.21` | `package.json` |
| Hosting objetivo | Neubox (Hosting Compartido / cPanel) | `.env` de producción documentado en README |

> ~~`vite.config.js` confirmado: solo registra los plugins `laravel()` ... y `vue()` ... No incluye el plugin de Tailwind v4 — consistente con que el proyecto corre en v3 vía PostCSS.~~ **✅ Corregido 29 oct 2026 (nota obsoleta, contradecía la sección "Tailwind CSS v4 — Migración Completada"):** `vite.config.js` real registra `laravel()` (con `resources/css/app.css` y `resources/js/app.js`, `refresh: true`), **`tailwindcss()` (import de `@tailwindcss/vite`)** y `vue()` (con `transformAssetUrls`). `app.css` usa `@import "tailwindcss"` y `@plugin "@tailwindcss/forms"`. No existen `tailwind.config.js` ni `postcss.config.js`. El proyecto corre en **Tailwind v4**.

> **Arquitectura clave:** Inertia.js conecta Laravel con Vue sin API REST. Los controladores devuelven `Inertia::render('Carpeta/Vista', $datos)`.

> ✅ **Confirmado:** Las rutas públicas ya están conectadas a la base de datos real. La ruta raíz `/` devuelve `view('landing.index')` con un preview de 4 categorías, y `/catalogo` devuelve `view('catalogo.index')` con todos los productos reales filtrados de la BD. La Fase 4 está 100% completada y funcional.

## Catálogo Público y Landing Page (Blade SSR)
- **Estructura de Rutas Públicas:** La ruta `/` sirve una Landing Page corporativa (`LandingController@index` → `view('landing.index')`) con favoritos y accesos rápidos, y `/catalogo` sirve el catálogo digital completo (`CatalogController@index` → `view('catalogo.index')`). Ambas operan con Laravel Blade puro (SSR) para maximizar indexación SEO y velocidad de carga, incorporando llamados a la acción dinámicos a WhatsApp (`wa.me`).
- **Filtrado de Catálogo por Configuración:** `CatalogController` consulta la clave `catalog_only_with_images` en `settings`. Cuando está activa (`1`/`true`), filtra estrictamente productos con imagen (`whereNotNull('image')->where('image', '!=', '')`) y excluye categorías sin productos fotográficos, sin afectar la visibilidad en POS o Inventario interno.
- **Normalización de URLs de Imágenes:** Las vistas públicas y el frontend consumen el accesor `$product->image_url`, el cual normaliza rutas locales (con o sin prefijo `products/`) y URLs remotas, aplicando `rawurlencode()` para evitar rotura de enlaces por espacios o caracteres especiales.
- **Control de Conflictos con Inertia:** En `resources/js/app.js`, la iniciación de Inertia está condicionada al wrapper `<div id="app" data-page="...">` para evitar choques con el script nativo de las vistas públicas en Blade.
- **Lógica de Fichas (Vanilla JS):** El modal de `/catalogo` opera sin framework. Conecta directamente con la base de datos, usando las matrices de materiales y acabados para personalizar la presentación. Se reestructuró con navegación tipo libro (carrusel continuo con swipe táctil y controles por teclado) y un diseño 100% responsivo limitado a `max-h-[90vh]`.
- **Despliegue y Compilación en Producción (Neubox / cPanel):** Tras cualquier cambio en frontend o assets públicos, es indispensable ejecutar `npm run build` y limpiar las cachés de Laravel con `php artisan config:cache`, `php artisan route:cache` y `php artisan view:cache`.

---

## 2. Estructura de Base de Datos (Schema Real Completo — confirmado contra migraciones)

### 👤 `users`
```
id, name, email (unique), password,
role (string: 'admin'|'vendedor'|'produccion'|'inventario'|'supervisor'|'financiero', default:'vendedor'),
email_verified_at, remember_token, timestamps
```
- `role` es un string directo en la tabla, NO una tabla separada de roles.
- ~~⚠️ No se pudo confirmar `VALID_ROLES` porque `UserController.php` no está en el código auditado (ver hallazgo crítico, sección 0).~~ ✅ Resuelto (corregido 29 oct 2026): `UserController.php` existe y `VALID_ROLES` coincide con los 6 roles (ver sección 0).

### 👥 `clients`
```
id, name, business_name (nullable), price_tier (int 1-5, default:1),
email (unique), phones,
street_address, neighborhood, city, state, delegation, zip_code (todos nullable),
references (text, nullable), timestamps
```
- `price_tier` 1-5 → se muestra como Listas A, B, C, D, E en el frontend.
- **`catalog_token` NO existe todavía** — Fase 4.2 sigue sin construir.

### 🗂️ `categories`
```
id, name, timestamps
```

### 📦 `products`
```
id, category_id (FK → categories),
name, description (nullable),
image (nullable, ruta relativa al disco public),
is_favorite (boolean, default:false), timestamps
```
> ⚠️ NO existe campo `color` ni `measurements` (movido a `product_variants`, confirmado en migración). El color es atributo de la venta (`sale_details.chosen_color`).

### 🎨 `product_variants`
```
id, product_id (FK → products, cascade delete),
measurements (string, obligatorio), material (string, obligatorio),
stock (int, default:0), min_stock (int, nullable, índice: ninguno),
reserved_stock (int, default:0), damaged_stock (unsignedInteger, default:0),
sku (nullable, sin unique), stock_notes (nullable),
price_1 (decimal, obligatorio), price_2..price_5 (decimal, nullable),
timestamps

```
- Un producto tiene N variantes (material + medida).
- `min_stock`: umbral configurable solo visible en formulario si el producto padre tiene `is_favorite = true`.
- El stock sube al registrar producción (`production_completions`, confirmado en `ProductionController::storeCompletion`) y baja al confirmar un embarque (`ShipmentController::store`, confirmado con `lockForUpdate`).
- ✅ **Confirmado resuelto:** el motor de etapas del Kanban (`SaleController::updateStage`) **ya no toca stock**. Embarques es el único mecanismo de salida. (Antes era un bug crítico documentado; ver sección 5.)
*   **NUEVA REGLA DE NEGOCIO (Stock Reservado / Detallado v2.8):** Para evitar inventarios negativos y disonancias numéricas al enviar remanentes a detallado, se implementó `reserved_stock`. 
    - Al enviar piezas a "Detallado", se incrementa este valor. 
    - El stock real disponible en todo el sistema se calcula como `(stock - reserved_stock)`. 
    - **Remanente de Producción (Matemática Estricta):** El cálculo de `pending_to_fabricate` (lo que falta por producir) en `ProductionController` se estabilizó usando la fórmula: `max(0, Requerimientos - StockFisico - EnDetallado - Enviados)`. Esto garantiza que los pedidos consideren el stock global de la empresa antes de mandar a fabricar. La UI muestra "X pendientes de Y totales" explícitamente.

### 💼 `sales`
```
id, user_id (FK → users), client_id (FK → clients, nullable),
total (decimal), paid_amount (decimal, default:0),
change_amount (decimal, default:0), payment_method (string, default:'Efectivo'),
signature (longText, nullable — base64),
stage (enum: pedido|confirmado|produccion|enviado|entregado|cancelado, default:'pedido'),
promised_date (date, nullable), is_partial_shipping (boolean, default:false),
timestamps
```
⚠️ `promised_date` se captura de forma obligatoria al crear el pedido en el POS (`SaleController::store()`). **Nota:** Actualmente no existe formulario para editarla posteriormente tras la creación del pedido.

### 📋 `sale_details`
```
id, sale_id (FK → sales, cascade), product_variant_id (FK → product_variants),
product_name (snapshot, incluye medida y material), quantity (int),
chosen_color (nullable), custom_notes (text, nullable),
additional_cost (decimal, default:0),
discount_percent (int, default:0), unit_price (decimal), subtotal (decimal),
production_hold (boolean, default:false),
timestamps
```
- `production_hold`: flag de pausa automática que se enciende en envíos parciales si restan piezas por entregar Y por fabricar. Aísla las piezas de la cola de fabricación activa hasta su liberación manual.

### 📜 `sale_histories`
```
id, sale_id (FK → sales, cascade), user_id (FK → users),
from_stage (nullable), to_stage, notes (text, nullable), timestamps
```

### 💳 `sale_payments`
```
id, sale_id (FK → sales, cascade), user_id (FK → users),
amount (decimal), payment_method (string),
reference (nullable), paid_at (timestamp), timestamps
```
- Los pagos están ligados a `sale_id`, no a un cliente directamente. El "estado de cuenta por cliente" (Fase 3.2, Finanzas) se construye agregando esta tabla + `sales.paid_amount` por `client_id` — no requiere cambio de esquema.
- Registrado por `SalePaymentController::store()`, confirmado con validación de deuda (`amount <= total - paid_amount`) y transacción atómica.

### ⚙️ `settings`
```
id, key (unique), value (text, nullable), timestamps
```
Claves activas en `$allowedKeys`: `company_name`, `company_rfc`, `company_address`, `company_phone`, `company_whatsapp`, `notification_emails`, `allow_negative_stock`, `ticket_footer_text`, `auto_email_on_sale`, `catalog_only_with_images`.
> ✅ Confirmado implementado: `auto_email_on_sale` controla el despacho diferido de notas de venta por correo al crear pedidos en el POS.

### 🛠️ `production_completions` (v2.7)
```
id, sale_detail_id (FK → sale_details, nullable),
work_order_id (FK → work_orders, nullable),
quantity_completed (int),
user_id (FK → users),
completed_at (timestamp),
timestamps
```
- Soporta avances originados tanto por pedidos de venta (`sale_detail_id`) como por órdenes de fabricación autónomas (`work_order_id`).
- Es la única fuente confiable de "cuánto se ha fabricado en total" para un pedido u orden. Al registrarse incrementa el `stock` de `product_variants`.

### 🏭 `work_orders` (v2.7)
```
id, product_variant_id (FK → product_variants),
quantity_requested (int),
color (string, nullable — migración 2026_10_13_160500, texto libre; Fase 2.7 lo pasa a `color_id`),
target_date (date, nullable),
status (string: 'pending'|'in_progress'|'completed', default:'pending'),
origin_sale_detail_id (FK → sale_details, nullable),
notes (text, nullable),
created_by (FK → users),
timestamps
```
- Gestionado vía `WorkOrderController::store`. Permite producir piezas para stock de temporada sin requerir una venta previa.
- Al registrar avance en el Plan de Producción, suma directamente al stock de la variante correspondiente.

### 🚚 `shipments` (v2.6)
```
id, driver_name (nullable), license_plate (nullable), destination (nullable),
status (string, default:'en_transito' — valores usados: 'en_transito','entregado','cancelado'),
shipped_at (nullable), pickup_type (string, default:'flota_propia'),
delivered_at (nullable), notes (text, nullable),
user_id (FK → users), timestamps
```
- ✅ **Confirmado:** `pickup_type` (`'flota_propia'` / `'recoleccion_cliente'`) existe en la migración y está **implementado completo** en backend y frontend (ver sección 5, ya no es un pendiente).
- ✅ **Confirmado:** `cancel()` existe en `ShipmentController` — el valor `'cancelado'` del `status` sí se usa en código real, ya no es solo un comentario de migración sin implementar.

### 📦 `sale_deliveries` (v2.6)
```
id, shipment_id (FK → shipments, cascade, obligatorio, no nullable),
sale_detail_id (FK → sale_details, cascade),
quantity_delivered (int),
timestamps
```
- Tabla pivote. Permite envíos parciales del mismo pedido en distintos viajes.
- Solo tiene estas 3 columnas + timestamps. El antiguo `SaleController::storeDelivery()` que intentaba escribir `user_id`/`delivered_at` (columnas inexistentes aquí) **ya no existe en el código** — método, ruta y botón del frontend fueron eliminados.

### 🧾 `detallado_records` (v2.8) — agregada a esta sección el 29 oct 2026 (existía en migraciones, faltaba aquí)
```
id, sale_detail_id (FK → sale_details, cascade), quantity (int),
user_id (FK → users), timestamps
```
- Registra piezas apartadas ("Detallado"); suman a `product_variants.reserved_stock` (`SaleController::sendToDetallado`). Sin índices propios salvo los de FK.

### 🩹 `damaged_stock_logs` (Oct 2026) — agregada el 29 oct 2026
```
id, product_variant_id (FK → product_variants, cascade), user_id (FK → users),
type (string: 'marcar_danada'|'reparada'|'baja_definitiva'),
quantity (unsignedInteger), notes (text, nullable), timestamps
```
- Escrita solo por `DamagedStockController::store` (`POST /damaged-stock`, `role:admin,supervisor`). **No toca `variant_color_stocks.damaged_stock`.**

### 🎨 `variant_color_stocks` (Oct 2026) — agregada el 29 oct 2026
```
id, product_variant_id (FK → product_variants, cascade),
color (string — texto libre; Fase 2.7 lo migra a color_id),
stock (unsignedInteger, default:0), damaged_stock (unsignedInteger, default:0),
reserved_stock (unsignedInteger, default:0), timestamps,
UNIQUE (product_variant_id, color)
```
- Accesor `available_stock = max(0, stock − reserved_stock − damaged_stock)`. Hoy `reserved_stock` y `damaged_stock` por color **no se alimentan** desde Detallado ni Control de Daños (solo `stock`).

### 🗂️ Índices reales (migraciones, 29 oct 2026)
Solo FK (índice automático en MySQL), `users.email` (unique), `settings.key` (unique) y `variant_color_stocks (product_variant_id, color)` (unique). **Sin índice** en `sales.stage`, `sales.promised_date`, `shipments.status`, `work_orders.status`, `product_variants.material`, `sale_details.chosen_color`, `variant_color_stocks.color`. Ver Anexo 0.3-X4.

---

## 3. Relaciones Eloquent (confirmadas contra código real)

```
User          → hasMany(Sale), hasMany(Shipment), hasMany(ProductionCompletion)
Client        → hasMany(Sale)
Category      → hasMany(Product)
Product       → belongsTo(Category) → hasMany(ProductVariant) [cascade delete]
ProductVariant→ belongsTo(Product)
Sale          → belongsTo(User), belongsTo(Client)
              → hasMany(SaleDetail)
              → hasMany(SaleHistory)  ['history']
              → hasMany(SalePayment)  ['payments']
SaleDetail    → belongsTo(Sale)
              → belongsTo(ProductVariant) — el método real es `variant()`, NO `productVariant()`.
                Confirmado en ProductionController y ShipmentController (`->with(['variant.product', ...])`).
                ⚠️ Distinto de la columna cruda: la FK en el JSON hacia el frontend es `product_variant_id`
                (nombre real de la columna en `sale_details`), NO `variant_id`.
              → hasMany(ProductionCompletion) ['completions']
              → hasMany(SaleDelivery) ['deliveries']
SaleHistory   → belongsTo(User)
SalePayment   → belongsTo(Sale), belongsTo(User)
Shipment      → belongsTo(User)
              → hasMany(SaleDelivery) ['deliveries']
SaleDelivery  → belongsTo(Shipment), belongsTo(SaleDetail)
ProductionCompletion → belongsTo(SaleDetail), belongsTo(User)
Setting       → getValue(), setValue(), getAll() [métodos estáticos]
```
> Relaciones **planeadas** (Fases 2.7/2.8, sin construir): `Category ↔ Material` (M:N vía `category_material`), `Material ↔ Color` (M:N vía `material_color`, con `activo`/`orden`), `ProductVariant → belongsTo(Material)`, `VariantColorStock/WorkOrder/SaleDetail → belongsTo(Color)`, `SaleReturn → hasMany(SaleReturnItem)`, `SaleReturnItem → belongsTo(SaleDetail), belongsTo(Shipment), belongsTo(Color)`.
> Modelos existentes no listados arriba (confirmados 29 oct 2026): `WorkOrder`, `DetalladoRecord`, `DamagedStockLog`, `VariantColorStock` (`ProductVariant → hasMany(colorStocks)`).
```text

---

## 4. Rutas (`routes/web.php`) — Estado real confirmado

| Zona | Middleware real | Rutas incluidas |
|------|-----------|----------------|
| Pública | — | `/` (Landing Page Blade), `/catalogo` (Catálogo Blade SSR), login, register |
| Dashboard | `auth,verified` (todos) | `/dashboard` — el controlador decide qué mostrar según rol |
| Perfil | `auth,verified` | `/profile` |
| Ventas | `role:admin,vendedor` | `/pos`, `/sales/*`, `/clients` (crear/editar), pagos |
| Taller | `role:admin,produccion,supervisor` | `/production-plan`, `/production-plan/complete`, `/production-plan/print`, `/work-orders` (store), `/sale-details/{id}/release-hold` |
| Inventario | `role:admin,supervisor` | `/products/*` (CRUD completo y toggle favoritos) |
| Embarques | `role:admin,inventario,supervisor` | `/shipments/*` (index, create, store, show, confirm, print, cancel) |
| Admin | `role:admin` | `/users/*` (CRUD completo), `/clients/{id}` (destroy), `/configuracion` |

> ✅ **Confirmado resuelto:** las rutas de `/shipments/*` ya están restringidas a roles (hoy `admin,inventario,supervisor`; ✅ Corregido 29 oct 2026, antes decía `admin,inventario`). Antes era un bug crítico (cualquier usuario autenticado podía crear/confirmar embarques); ya no es el caso.

### Rutas de Embarques (confirmadas completas)

| Método | URI | Nombre | Descripción |
|--------|-----|---------|-------------|
| GET | `/shipments` | `shipments.index` | Historial de viajes |
| GET | `/shipments/create` | `shipments.create` | UI para armar embarque (soporta `?client_ids[]=`) |
| POST | `/shipments` | `shipments.store` | Guardar embarque (transaccional, con `lockForUpdate`) |
| GET | `/shipments/{id}` | `shipments.show` | Detalle del viaje |
| GET | `/shipments/{id}/print` | `shipments.print` | PDF Remisión — ~~**no agrupa por cliente/pedido, es un listado plano** (ver sección 5)~~ ✅ Corregido 29 oct 2026: agrupa por pedido (`groupBy('saleDetail.sale_id')`) con diseño corporativo |
| PATCH | `/shipments/{id}/confirm` | `shipments.confirm` | Confirmar entrega y cerrar pedidos (revisa TODAS las líneas) |
| PATCH | `/shipments/{id}/cancel` | `shipments.cancel` | ✅ Cancelar embarque y regresar stock — confirmado implementado |

---

## 5. Reglas de Negocio — Estado Real (25 jul 2026)

### ✅ RESUELTO — Doble mecanismo de descuento de stock

`SaleController::updateStage()` confirmado: su `validate()` solo acepta `pedido,confirmado,produccion,cancelado` — ya no permite mover a mano a `enviado` o `entregado`, y no toca stock en absoluto. `ShipmentController::store()` es ahora el único punto que descuenta stock (con `ProductVariant::lockForUpdate()` para evitar condiciones de carrera entre líneas o usuarios concurrentes), y `ShipmentController::cancel()` es el único que lo regresa.

### ✅ RESUELTO — Plan de Producción no descontaba lo ya fabricado

`ProductionController::index()` y `printReport()` confirmados con la fórmula unificada de faltantes:
```php
'pending_to_fabricate' => max(0, $totalNeeded - $stockFisico - $wipDetailed - $totalDelivered),
```
Badge de 4 estados confirmado en `Production/Index.vue` (sin fabricar / parcial / listo para embarcar / fabricado y ya embarcado). Navegación de semana (`« Ant.` / `Sig. »`) confirmada implementada. ⚠️ El toggle "Ver todo acumulado" **no existe** en el código — sigue pendiente.

### ✅ RESUELTO — `storeDelivery()` roto

El método, la ruta `sales.deliveries.store` y el botón correspondiente en `Sales/Show.vue` **ya no existen** en el código.

### ✅ RESUELTO — Sin forma de cancelar un embarque

`ShipmentController::cancel()` confirmado, con las reglas de negocio documentadas respetadas en código:
- Flota propia: cancelable solo mientras `en_transito`, no una vez `entregado`.
- Recolección en mostrador: cancelable incluso después de `entregado` (es la única forma de revertir un error de captura, ya que nace directo en ese estado).
- Regresa stock con `increment()` (variante y, si aplica, color en `variant_color_stocks`) y ~~revierte el `stage` del pedido al `from_stage` real tomado de `sale_histories`~~ ✅ Corregido 29 oct 2026: recalcula **en vivo** las piezas entregadas (sin leer `sale_histories`) y, si queda alguna partida con faltantes y el pedido estaba `enviado`/`entregado`, lo regresa a `produccion`. Aún crea un `SaleHistory` manual (línea 365) además del `SaleObserver`.
- Los `withSum('deliveries as delivered_quantity', ...)` en `ShipmentController::create()`, `SaleController::show()` y `closeOrderIfComplete()` confirmados con `whereHas('shipment', fn($q) => $q->where('status', '!=', 'cancelado'))` — un embarque cancelado no vuelve a contarse como entregado.

### ✅ IMPLEMENTADO COMPLETO — Recolección en mostrador vs. flota propia (`pickup_type`)

A diferencia de lo que indicaba el Backlog anterior (marcado como "backend listo, falta selector en frontend"), **está confirmado completo en ambos lados**:
- Backend: `ShipmentController::store()` crea el `Shipment` directo en `status = 'entregado'` con `delivered_at = now()` cuando `pickup_type === 'recoleccion_cliente'`, y ejecuta `closeOrderIfComplete()` en la misma transacción.
- Frontend: `Shipments/Create.vue` confirmado con un toggle visual (`flota_propia` / `recoleccion_cliente`) que cambia el texto del formulario dinámicamente ("Chofer / Repartidor" vs. "Nombre de quien recoge").

### ✅ RESUELTO (✅ Corregido 29 oct 2026; antes figuraba como "⚠️ PENDIENTE") — Notas de entrega agrupadas por pedido

~~`ShipmentController::printManifest()` y la plantilla `shipment_manifest.blade.php` no tenían agrupación: `@foreach` plano.~~ Hoy `printManifest()` agrupa `$groupedDeliveries` por `saleDetail.sale_id` y la plantilla itera por pedido (`shipment_manifest.blade.php`, `@foreach($groupedDeliveries as $saleId => $deliveries)`), con diseño corporativo y cálculo de importes sobre `quantity_delivered`. Historial: el texto anterior seguía vigente solo hasta ese refactor.

### ✅ IMPLEMENTADO COMPLETO — Filtro multi-cliente en Embarques

`ShipmentController::create()` recibe `client_ids[]` vía query param y `Shipments/Create.vue` cuenta con el selector múltiple integrado en la UI, filtrando correctamente las ventas disponibles por los clientes seleccionados.

### ⚠️ Patrón de rendimiento — modelos completos viajando sin usarse

Confirmado ya corregido en `ShipmentController::create()`, `index()` y `show()`: usan `select()` explícito, sin `signature` (base64 pesado) ni columnas de precio para roles de Inventarios.

**Pendiente confirmado, sin corregir todavía:**
- `ProductController::index()` — `Product::with(['category','variants'])->get()` sin `select()` ni paginación: carga todos los productos con todas sus variantes completas (incluidos los 5 precios) en cada visita al catálogo interno.
- `SaleController::create()` (POS) — `Client::all()` sin límite ni `select()`, se ejecuta en cada apertura del POS.

### Roles y Redirección al Login
- `admin`, `vendedor` → `/dashboard`
- `produccion` → `/production-plan`
- `inventario`, `supervisor`, `financiero` → `/dashboard` (pantalla de bienvenida temporal — sin módulo propio todavía, confirmado en `DashboardController`)

### Modo Oficina / Modo Taller (`Sales/Show.vue`)
✅ Confirmado resuelto: `is_production_mode` se fuerza automáticamente a `true` desde el backend (`SaleController::show()`) para los roles `produccion`, `inventario` y `supervisor`. Adicionalmente, se emplea `$sale->makeHidden()` para garantizar que los importes financieros y precios unitarios no viajen en el JSON hacia el cliente en estos roles.

---

## 6. Componentes Vue — Estado Real (confirmado contra código)

| Componente | Estado | Notas |
|-----------|--------|-------|
| `Dashboard.vue` | ✅ | KPIs por rol; pantalla bienvenida para roles sin dashboard propio |
| `Sales/Create.vue` | ✅ | POS completo |
| `Sales/Index.vue` | ✅ | Kanban — ya no permite mover a mano a `enviado`/`entregado` |
| `Sales/Show.vue` | ✅ | Modo Oficina/Taller (~~por query param, no forzado por rol aún~~ ✅ Corregido 29 oct 2026: el backend fuerza Modo Taller para `produccion`, `inventario` y `supervisor`), abonos |
| `Production/Index.vue` | ✅ | Navegación de semana y badge de 4 estados confirmados. |
| `Products/Create.vue` / `Edit.vue` | ✅ | Variantes, materiales, medidas, imagen, favorito |
| `Products/Index.vue` | ⚠️ | Funcional, pero `ProductController::index()` no pagina server-side |
| `Clients/*`, `Users/*`, `Settings/Index.vue` | ✅ | `Users/*` confirmado funcional contra `UserController.php` real (CRUD completo, `VALID_ROLES` coincide con los 6 roles del sistema) |
| `Shipments/Index.vue` | ✅ | Lista de viajes, imprimir, confirmar, **cancelar** (badge de 3 estados: en tránsito/entregado/cancelado) |
| `Shipments/Create.vue` | ✅ | Armar embarque, validación de stock compartido entre líneas, toggle flota propia / mostrador y **selector multi-cliente**. |
| `Shipments/Show.vue` | ✅ | Detalle con `chosen_color`, fechas y piezas a bordo |

---

## 7. Arquitectura de Piezas Clave

### `SaleObserver.php`
- Al **crear** venta → registra en `sale_histories` automáticamente.
- Al **actualizar** stage → registra `from_stage` / `to_stage` automáticamente.
- No toca stock — eso ahora vive únicamente en `ShipmentController`.

### `CheckRole.php`
- Parámetro variádico: `handle(Request $request, Closure $next, string ...$roles)`. Confirmado correcto en código real.
- Registrado en `bootstrap/app.php` como alias `'role'`.

### `HandleInertiaRequests.php`
- Comparte `auth.user` globalmente → disponible en cualquier `.vue` como `$page.props.auth.user`.

### `AppServiceProvider.php`
- Registra `SaleObserver`, fuerza HTTPS en producción, `Carbon::setLocale('es')`.

### `AuthenticatedSessionController.php`
- Login usa `redirect()->away()` (no `redirect()->intended()`) para forzar recarga completa.
- Logout también usa `redirect()->away('/')` por la misma razón.

---

## 8. Paquetes Instalados (confirmado con `composer.lock` y `package.json` reales)

```
PHP: barryvdh/laravel-dompdf ^3.1.1 (resuelve a 3.1.2), laravel-lang/common,
     laravel/framework ^12.0 (resuelve a 12.62.0)

JS — dependencies (runtime):
     lodash ^4.17.21, sweetalert2 ^11.26.17, vue-signature-pad ^3.0.2

JS — devDependencies (build/tooling):
     @inertiajs/vue3 ^2.0.0, @tailwindcss/forms ^0.5.3, @tailwindcss/vite ^4.3.3 (✅ en uso desde la migración a v4),
     @vitejs/plugin-vue ^6.0.7, axios ^1.18.0, concurrently ^9.0.1,
     laravel-vite-plugin ^2.0.0, tailwindcss ^4.3.3, vite ^7.0.7, vue ^3.4.0
     (~~postcss ^8.4.31, autoprefixer ^10.4.12, tailwindcss ^3.2.1~~ ✅ Corregido 29 oct 2026: ya no están en package.json)
```

---

## 9. Matriz de Roles y Permisos (Propuesta — parcialmente implementada, confirmado contra rutas reales)

6 roles: `admin`, `supervisor`, `vendedor`, `inventario`, `produccion`, `financiero`.

| Módulo | Admin | Supervisor | Vendedor | Inventarios | Producción | Finanzas |
|---|---|---|---|---|---|---|
| Dashboard | ✅ implementado | ❌ sin módulo (pantalla bienvenida) | ✅ implementado | ❌ sin módulo | ❌ sin módulo | ❌ sin módulo |
| POS / Crear pedido | ✅ | ❌ | ✅ | ❌ | ❌ | ❌ |
| Ventas (Kanban, mover etapa) | ✅ todas | ❌ fuera de alcance de la reunión del 04 ago | ✅ solo las suyas | ❌ | ❌ | ❌ sin acceso hoy |
| Clientes (CRUD) | ✅ completo | ❌ sin acceso hoy | ✅ crear/editar | ❌ | ❌ | ❌ sin acceso hoy |
| Plan de Producción | ✅ | ✅ **Implementado — acceso completo** | ❌ | ❌ | ✅ ver + registrar fabricado | ❌ |
| Embarques (crear/confirmar/cancelar) | ✅ | ✅ **Implementado — acceso completo** | ❌ | ✅ | ❌ sin acceso hoy | ❌ sin acceso hoy |
| Pagos/Abonos (`sale_payments`) | ✅ | ❌ | ✅ (de sus ventas) | ❌ | ❌ | ❌ sin acceso hoy |
| Productos/Inventario (CRUD) | ✅ | ✅ **Implementado — acceso completo** | 👁️ ver (para POS) | ❌ sin acceso hoy | ❌ | ❌ |
| Usuarios | ✅ confirmado completo | ❌ | ❌ | ❌ | ❌ | ❌ |
| Configuración | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Catálogos** (categorías, materiales, colores) — Fase 2.7 🔴 *Planeado, sin construir* | ✅ | ✅ (sección propia "Catálogos"; sigue SIN Configuración) | ❌ | ❌ | ❌ | ❌ |
| **Devoluciones** (submenú de Embarques) — Fase 2.8 🔴 *Planeado, sin construir* | ✅ | ✅ | ❌ (403) | ✅ | ❌ (403) | ❌ |

> Las filas **Catálogos** y **Devoluciones** son diseño acordado (secciones 0.3-A y 0.3-B), sin rutas ni menú construidos. Catálogos puede colgar del grupo existente `role:admin,supervisor` (hoy aloja `products` y `damaged-stock`); Devoluciones del grupo `role:admin,inventario,supervisor` de Embarques. **Cobranza (Fase 3.2): ⏸️ EN PAUSA** — `financiero` sigue sin rutas.

**Diferencia importante con la matriz "propuesta" de versiones anteriores de este documento:** antes describía el diseño *deseado* a futuro (Supervisor de solo lectura en todo). **Acuerdo de reunión (04 ago 2026):** eso cambió — el cliente ahora quiere a Supervisor con permisos de edición completos (no solo lectura) en Producción, Almacén y Embarques, al mismo nivel que Admin. Sigue sin acceso a Ventas/Kanban ni Configuración salvo que se amplíe después. `financiero` sigue sin ninguna ruta asignada — Fase 3, sin empezar.

### 🔒 Matriz de Visibilidad de Precios — estado real

Confirmado en código:
- `ShipmentController::create()` ya no manda `price_1..price_5` en el JSON (columnas explícitamente excluidas con `variant:id,product_id,material,measurements,stock`).
- `SaleController::index()` ya usa `select()` explícito sin `signature`.
- **~~Pendiente confirmado:~~ ✅ Parcialmente resuelto (corregido 29 oct 2026):** `Sales/Show.vue` ya fuerza Modo Taller por rol en el backend (`SaleController::show`, líneas 222-231). Sigue pendiente que `SaleController::index()` no condiciona el `select()` por rol (hoy nadie más que admin/vendedor llega a esa ruta, así que no es explotable todavía).

### Diseño del "Estado de Cuenta" por cliente (Finanzas) — sigue siendo diseño, no implementación

No se requiere tabla nueva. Se calcularía agregando `sale_deliveries` (join con `shipments.status = 'entregado'`) contra `sales.paid_amount` + `sale_payments.amount`, agrupado por `client_id`. **Confirmado: no hay ningún controlador ni ruta que calcule esto todavía** — es 100% diseño para la Fase 3.2.

---

## 10. Variables de Entorno

```ini
APP_ENV=local|production
APP_DEBUG=true|false
APP_URL=http://localhost|https://tudominio.com
DB_CONNECTION=mysql
DB_DATABASE=taller360

# Solo en producción (Neubox):
FILESYSTEM_PUBLIC_ROOT=/home/usuario/public_html/storage
```

---

## 11. Cómo Compartir Contexto con IA al Retomar

Al iniciar sesión nueva:
1. Comparte este archivo (`CONTEXTO_TECNICO.md`).
2. Comparte los archivos específicos de la tarea a trabajar (controlador + vista Vue involucrados, y la migración si aplica). Si vas a compartir un zip completo, **excluye `vendor/`, `node_modules/`, `storage/logs`, `storage/framework` y `.git`** — no aportan nada y hacen el zip pesado.
3. Si la tarea toca el frontend/build, recuerda que el proyecto corre en **Tailwind v4** (✅ Corregido 29 oct 2026): no existen `tailwind.config.js` ni `postcss.config.js`; la configuración vive en `resources/css/app.css` y `vite.config.js`.
4. Describe qué quieres hacer.

Para tareas grandes de Fase 3 en adelante, abre sesiones separadas por sub-tarea.


---

## 12. Reglas de SEO y Branding

- **Prohibido el uso hardcoded de 'Taller 360'**: 'Taller 360' es el nombre interno de desarrollo del software. Nunca debe ser expuesto en etiquetas `<title>`, `<meta>`, URLs públicas o texto visible para el cliente final.
- **Uso de la variable de base de datos**: Todos los títulos de página y metadatos SEO deben leer estrictamente la clave `company_name` de la tabla `settings` (ej. 'Industria Santa Cecilia').
- **Inertia/Vue**: Para el backend, la configuración ya se comparte a través del middleware `HandleInertiaRequests.php` (`page.props.settings.company_name`). El sufijo dinámico está configurado en `resources/js/app.js` en lugar de depender de la variable de entorno local (`VITE_APP_NAME`).
- **Vistas Blade (Públicas)**: Las vistas como el Landing y Catálogo utilizan directamente `$settings['company_name']` con fallback explícito a 'Industria Santa Cecilia', omitiendo nombres genéricos.
