# ðŸ§  CONTEXTO TÃ‰CNICO â€” TALLER 360
**VersiÃ³n Real:** 2.8 | **Sincronizado directamente contra el cÃ³digo fuente:** 07 de septiembre 2026
**Para:** Retomar desarrollo con IA o desarrollador nuevo sin perder contexto ni repetir trabajo ya completado.
**IMPORTANTE:** A diferencia de la versiÃ³n anterior de este documento (que auditaba solo texto/reportes previos), esta versiÃ³n se verificÃ³ lÃ­nea por lÃ­nea contra controladores, modelos, migraciones, rutas y componentes Vue reales. Compartir siempre este archivo al iniciar una nueva sesiÃ³n.

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

### C. Control de Existencias Físicas por Color (Melamina - Colores de Fábrica) (COMPLETADO)
- **Problema en producción:** Artículos de Melamina se fabrican a partir de tableros que ya vienen con color de fábrica (Blanco, Gris, Texturizado, Oyamel). No existe la melamina "natural". Si se crearan variantes separadas por color, el catálogo público y el POS se saturarían multiplicando artículos y precios innecesariamente. Sin embargo, almacén, producción y embarques necesitan saber con precisión cuántas piezas hay físicamente en bodega de cada color para no despachar un color equivocado ni dar por cubiertos pedidos de un color cuando solo hay stock de otro.
- **Regla de Negocio:**
  - El producto y la variante (`ProductVariant`) permanecen como entidad comercial única (Medidas, Material, Precios).
  - Exclusivo para `Melamina`: se gestionan de forma fija e inmutable los 4 colores oficiales del cliente (`Blanco`, `Gris`, `Texturizado`, `Oyamel`). Otros materiales (MDF, Madera, etc.) no manejan colores en inventario para mantener la agilidad de captura y evitar bloqueos por muebles fabricados en crudo.
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

## 0.1 Acuerdos de la reuniÃ³n con cliente (04 ago 2026) â€” pendientes de construir

Estos 5 puntos salieron de una reuniÃ³n con el cliente y ya tienen diseÃ±o tÃ©cnico acordado con el desarrollador. Van en orden de prioridad para la siguiente ronda de cÃ³digo.

### A. Supervisor con permisos completos en ProducciÃ³n, AlmacÃ©n y Embarques (COMPLETADO)
- **Rutas y Middleware:** En `routes/web.php`, se agregÃ³ el rol `supervisor` a los grupos de middleware `role:admin,produccion,supervisor` y `role:admin,inventario,supervisor`.
- **Inventario:** Se extrajeron las rutas de `products` (CRUD y favoritos) de la zona exclusiva de admin hacia un nuevo grupo compartido `role:admin,supervisor`.
- **Restricciones:** El supervisor tiene prohibido el acceso a Clientes, ConfiguraciÃ³n, Usuarios y Ventas (corroborado vÃ­a tests con `assertForbidden()`).
- **Frontend:** Se validÃ³ que no existen directivas `v-if` restrictivas por rol en los componentes Vue involucrados, por lo que los botones y acciones operan de forma nativa basados en el acceso del backend.


### B. Bug â€” pedido "entregado" cancelado cae en limbo (`enviado` invisible) (COMPLETADO)
- **Causa raÃ­z solucionada:** Se eliminÃ³ la lectura estÃ¡tica a `SaleHistory` en `ShipmentController::cancel()` reemplazÃ¡ndola por un recÃ¡lculo en vivo de piezas entregadas. Al cancelar un viaje, si un pedido previamente "entregado" aÃºn tiene piezas pendientes en alguna de sus lÃ­neas, la etapa vuelve de forma dinÃ¡mica a `produccion`.
- **Limpieza de Historial:** Se suprimiÃ³ la creaciÃ³n manual y duplicada de registros de `SaleHistory` en el mÃ©todo `store()`, consolidando a `SaleObserver` como la Ãºnica fuente de verdad para el tracking de etapas.
- **Fix Secundario (Modelo):** Se aÃ±adiÃ³ el campo `pickup_type` al array `$fillable` del modelo `Shipment` para garantizar el guardado del tipo de entrega y evitar choques con validaciones de flota propia al cancelar remisiones de mostrador.


### C. Ã“rdenes de Trabajo â€” producciÃ³n sin pedido + pausa de remanentes parciales (COMPLETADO)
**Necesidad del cliente:** (1) puede producir por anticipado sin que exista un pedido todavÃ­a (conoce la demanda de temporada), y (2) cuando un envÃ­o parcial deja piezas sin fabricar, no quiere que el sistema las marque automÃ¡ticamente como urgentes en el taller â€” quiere decidir Ã©l cuÃ¡ndo se fabrica el remanente.

**DiseÃ±o acordado â€” nueva tabla `work_orders`:**
```
id, product_variant_id (FK), quantity_requested (int),
target_date (date, nullable), status (string: 'abierta'|'cerrada', default:'abierta'),
origin_sale_detail_id (FK â†’ sale_details, nullable â€” si nace de un remanente parcial),
notes (text, nullable), created_by (FK â†’ users), timestamps
```
- Aparece en el Plan de ProducciÃ³n mezclada con las necesidades de pedidos normales (mismo query, uniÃ³n de dos fuentes).
- Al cerrarla se registra cuÃ¡nto se terminÃ³ realmente y esas piezas entran directo a `product_variants.stock` â€” mismo mecanismo que ya usa `production_completions`, pero sin requerir una venta.
- `production_completions.sale_detail_id` pasa a ser nullable; se agrega `work_order_id` (FK, nullable) â€” una fila de avance pertenece a una u otra fuente, nunca a ambas.

**DiseÃ±o acordado â€” pausa de remanentes (`sale_details.production_hold`):**
```
sale_details.production_hold (boolean, default: false)
```
- Se activa automÃ¡ticamente la primera vez que una lÃ­nea recibe un envÃ­o parcial (queda pieza sin entregar Y sin fabricar todavÃ­a).
- Mientras estÃ¡ en `true`, `ProductionController::index()` excluye esa cantidad remanente de "pendiente de fabricar" â€” aparece en una secciÃ³n aparte ("en espera de decisiÃ³n"), no mezclada con lo urgente.
- Cualquier usuario con acceso a ProducciÃ³n (`role:admin,produccion,supervisor` tras el cambio A) puede "liberar a producciÃ³n" â€” apaga el flag y/o genera un `work_order` con `origin_sale_detail_id` apuntando a esa lÃ­nea.

### D. Stock mÃ­nimo por variante, solo para productos preferentes (COMPLETADO)
**DecisiÃ³n:** el campo nuevo vive en `product_variants`, no en `products` â€” el stock siempre se ha manejado por variante (confirmado: la alerta actual de Dashboard ya consulta `ProductVariant`, no `Product`). "Preferente" sigue siendo el `is_favorite` que ya existe; no se crea concepto nuevo, solo se usa como filtro de visibilidad del campo.
```
product_variants.min_stock (int, nullable)
```
- El formulario de variantes solo pide/muestra `min_stock` cuando el producto padre tiene `is_favorite = true`.
- La alerta de stock crÃ­tico del Dashboard cambia de `stock <= 5` (fijo) a `stock <= COALESCE(min_stock, 5)` â€” mantiene el comportamiento actual como default si un producto se marca preferente sin definir aÃºn su mÃ­nimo por variante.
- Cambiar de temporada es simplemente desmarcar/marcar `is_favorite` â€” no requiere migrar ni tocar `min_stock` de variantes que ya no importan.

### E. EnvÃ­o automÃ¡tico de la nota de venta al crear el pedido (COMPLETADO)
- **ConfiguraciÃ³n (Settings):** Se agregÃ³ la clave `auto_email_on_sale` (boolean, default `true`) a `$allowedKeys` en `SettingController`. Determina el envÃ­o automÃ¡tico de correos en el POS.
- **Ventas:** En `SaleController`, se refactorizÃ³ el envÃ­o de correos al mÃ©todo `private function sendSaleNoteMail(Sale $sale)`. En el mÃ©todo `store()`, se llama al envÃ­o de correos fuera del bloque de transacciÃ³n (`DB::transaction`) y envuelto en un `try-catch` (`Log::error()`) para evitar que fallas SMTP aborten el pedido.
- **Testing (LÃ­nea Base):** Configurado `phpunit.xml` a base de datos `:memory:` (SQLite). Creadas suites de Feature Tests para asegurar comportamiento del middleware de roles (`RoleMiddlewareTest`), control de transiciones y ventas (`SaleControllerTest`), administraciÃ³n y consistencia del inventario (`ShipmentControllerTest`) y disparo del sistema de correos (`SaleAutoEmailTest`).

## Arquitectura de Correos y PDFs (ProducciÃ³n)
**Fecha:** 2026-08-13
**DecisiÃ³n:** Uso de procesamiento diferido sÃ­ncrono (`afterResponse`) vs. Colas de Base de Datos.
**Contexto:** Debido a las restricciones de ejecuciÃ³n persistente y configuraciÃ³n de binarios PHP en hostings compartidos (DirectAdmin/cPanel) que dificultan mantener un Worker activo, se descartÃ³ el uso del driver `database` para las colas.
**ImplementaciÃ³n:**
1. **Frontend:** Se bloquea el botÃ³n de envÃ­o en Vue.js tras el primer clic para evitar el doble submit por parte del usuario.
2. **Controlador:** Los BLOBs pesados de firmas se guardan temporalmente como archivos fÃ­sicos (`storage/app/public`) y los assets locales (logos) se inyectan como Base64 para evadir las restricciones `chroot` de seguridad de DOMPDF.
3. **Despacho Diferido:** La generaciÃ³n intensiva del PDF y la conexiÃ³n SMTP se ejecutan encapsuladas dentro de `dispatch()->afterResponse()`. Esto libera al usuario en milisegundos entregando el cÃ³digo HTTP 200, mientras el servidor finaliza la tarea pesada en segundo plano de manera segura.

## 0. Hallazgos de la ronda 2 de auditorÃ­a (25 jul 2026, con `UserController.php`, `package.json`, `vite.config.js`)

### âœ… Cerrado â€” `UserController.php` sÃ­ existe
La ronda 1 marcÃ³ como crÃ­tico que `UserController.php` no viniera en el zip pese a estar referenciado en `routes/web.php`. Confirmado con el archivo real: existe, CRUD completo (`index`, `create`, `store`, `edit`, `update`, `destroy`), con protecciÃ³n contra auto-eliminaciÃ³n (`if (auth()->id() == $user->id)`) y `VALID_ROLES = 'admin,vendedor,produccion,inventario,supervisor,financiero'` â€” coincide exactamente con los 6 roles usados en el resto del sistema. `User::$fillable` incluye `role`. Sin pendientes aquÃ­.

### âœ… Tailwind CSS v4 â€” MigraciÃ³n Completada

El conflicto de versiones se resolviÃ³ satisfactoriamente migrando por completo a la v4. 
- Se eliminaron `postcss.config.js` y `tailwind.config.js`.
- Se desinstalaron las dependencias de `postcss` y `autoprefixer`.
- `app.css` ahora utiliza la nueva sintaxis nativa (`@import "tailwindcss"`, `@plugin "@tailwindcss/forms"` y `@theme` para la fuente Figtree).
- `vite.config.js` ahora registra e invoca directamente el plugin de Tailwind oficial.

---

## 1. Stack TecnolÃ³gico

| Capa | TecnologÃ­a | Confirmado en |
|------|-----------|-------|
| Backend | Laravel 12.62.0 (PHP 8.2+) | `composer.lock` |
| Frontend | Vue `^3.4.0` (`<script setup>`) | `package.json` |
| Puente | Inertia.js `@inertiajs/vue3 ^2.0.0` | `package.json`, sin rutas `/api/` en `routes/web.php` |
| Build | Vite `^7.0.7` + `laravel-vite-plugin ^2.0.0` + `@vitejs/plugin-vue ^6.0.7` | `package.json`, `vite.config.js` |
| Estilos | Tailwind CSS `^4.0.0` | `package.json`, `resources/css/app.css`, `vite.config.js` |
| BD | MySQL / MariaDB InnoDB | migraciones |
| PDFs | barryvdh/laravel-dompdf 3.1.2 (dompdf/dompdf 3.1.5) | `composer.lock`, confirmado exacto |
| Firma Digital | vue-signature-pad `^3.0.2` | `package.json`, `Sales/Create.vue` |
| Alertas | sweetalert2 `^11.26.17` | `package.json` |
| Utilidades JS | lodash `^4.17.21` | `package.json` |
| Hosting objetivo | Neubox (Hosting Compartido / cPanel) | `.env` de producciÃ³n documentado en README |

> `vite.config.js` confirmado: solo registra los plugins `laravel()` (apuntando a `resources/css/app.css` y `resources/js/app.js`, con `refresh: true`) y `vue()` (con `transformAssetUrls` configurado). No incluye el plugin de Tailwind v4 â€” consistente con que el proyecto corre en v3 vÃ­a PostCSS.

> **Arquitectura clave:** Inertia.js conecta Laravel con Vue sin API REST. Los controladores devuelven `Inertia::render('Carpeta/Vista', $datos)`.

> âœ… **Confirmado:** Las rutas pÃºblicas ya estÃ¡n conectadas a la base de datos real. La ruta raÃ­z `/` devuelve `view('landing.index')` con un preview de 4 categorÃ­as, y `/catalogo` devuelve `view('catalogo.index')` con todos los productos reales filtrados de la BD. La Fase 4 estÃ¡ 100% completada y funcional.

## CatÃ¡logo PÃºblico y Landing Page (Blade SSR)
- **Estructura de Rutas PÃºblicas:** La ruta `/` sirve una Landing Page corporativa (`LandingController@index` â†’ `view('landing.index')`) con favoritos y accesos rÃ¡pidos, y `/catalogo` sirve el catÃ¡logo digital completo (`CatalogController@index` â†’ `view('catalogo.index')`). Ambas operan con Laravel Blade puro (SSR) para maximizar indexaciÃ³n SEO y velocidad de carga, incorporando llamados a la acciÃ³n dinÃ¡micos a WhatsApp (`wa.me`).
- **Filtrado de CatÃ¡logo por ConfiguraciÃ³n:** `CatalogController` consulta la clave `catalog_only_with_images` en `settings`. Cuando estÃ¡ activa (`1`/`true`), filtra estrictamente productos con imagen (`whereNotNull('image')->where('image', '!=', '')`) y excluye categorÃ­as sin productos fotogrÃ¡ficos, sin afectar la visibilidad en POS o Inventario interno.
- **NormalizaciÃ³n de URLs de ImÃ¡genes:** Las vistas pÃºblicas y el frontend consumen el accesor `$product->image_url`, el cual normaliza rutas locales (con o sin prefijo `products/`) y URLs remotas, aplicando `rawurlencode()` para evitar rotura de enlaces por espacios o caracteres especiales.
- **Control de Conflictos con Inertia:** En `resources/js/app.js`, la iniciaciÃ³n de Inertia estÃ¡ condicionada al wrapper `<div id="app" data-page="...">` para evitar choques con el script nativo de las vistas pÃºblicas en Blade.
- **LÃ³gica de Fichas (Vanilla JS):** El modal de `/catalogo` opera sin framework. Conecta directamente con la base de datos, usando las matrices de materiales y acabados para personalizar la presentaciÃ³n. Se reestructurÃ³ con navegaciÃ³n tipo libro (carrusel continuo con swipe tÃ¡ctil y controles por teclado) y un diseÃ±o 100% responsivo limitado a `max-h-[90vh]`.
- **Despliegue y CompilaciÃ³n en ProducciÃ³n (Neubox / cPanel):** Tras cualquier cambio en frontend o assets pÃºblicos, es indispensable ejecutar `npm run build` y limpiar las cachÃ©s de Laravel con `php artisan config:cache`, `php artisan route:cache` y `php artisan view:cache`.

---

## 2. Estructura de Base de Datos (Schema Real Completo â€” confirmado contra migraciones)

### ðŸ‘¤ `users`
```
id, name, email (unique), password,
role (string: 'admin'|'vendedor'|'produccion'|'inventario'|'supervisor'|'financiero', default:'vendedor'),
email_verified_at, remember_token, timestamps
```
- `role` es un string directo en la tabla, NO una tabla separada de roles.
- âš ï¸� No se pudo confirmar `VALID_ROLES` porque `UserController.php` no estÃ¡ en el cÃ³digo auditado (ver hallazgo crÃ­tico, secciÃ³n 0).

### ðŸ‘¥ `clients`
```
id, name, business_name (nullable), price_tier (int 1-5, default:1),
email (unique), phones,
street_address, neighborhood, city, state, delegation, zip_code (todos nullable),
references (text, nullable), timestamps
```
- `price_tier` 1-5 â†’ se muestra como Listas A, B, C, D, E en el frontend.
- **`catalog_token` NO existe todavÃ­a** â€” Fase 4.2 sigue sin construir.

### ðŸ—‚ï¸� `categories`
```
id, name, timestamps
```

### ðŸ“¦ `products`
```
id, category_id (FK â†’ categories),
name, description (nullable),
image (nullable, ruta relativa al disco public),
is_favorite (boolean, default:false), timestamps
```
> âš ï¸� NO existe campo `color` ni `measurements` (movido a `product_variants`, confirmado en migraciÃ³n). El color es atributo de la venta (`sale_details.chosen_color`).

### ðŸŽ¨ `product_variants`
```
id, product_id (FK â†’ products, cascade delete),
measurements (string, obligatorio), material (string, obligatorio),
stock (int, default:0), min_stock (int, nullable), sku (nullable), stock_notes (nullable),
price_1 (decimal, obligatorio), price_2..price_5 (decimal, nullable),
timestamps

```
- Un producto tiene N variantes (material + medida).
- `min_stock`: umbral configurable solo visible en formulario si el producto padre tiene `is_favorite = true`.
- El stock sube al registrar producciÃ³n (`production_completions`, confirmado en `ProductionController::storeCompletion`) y baja al confirmar un embarque (`ShipmentController::store`, confirmado con `lockForUpdate`).
- âœ… **Confirmado resuelto:** el motor de etapas del Kanban (`SaleController::updateStage`) **ya no toca stock**. Embarques es el Ãºnico mecanismo de salida. (Antes era un bug crÃ­tico documentado; ver secciÃ³n 5.)
*   **NUEVA REGLA DE NEGOCIO (Stock Reservado / Detallado v2.8):** Para evitar inventarios negativos y disonancias numÃ©ricas al enviar remanentes a detallado, se implementÃ³ `reserved_stock`. 
    - Al enviar piezas a "Detallado", se incrementa este valor. 
    - El stock real disponible en todo el sistema se calcula como `(stock - reserved_stock)`. 
    - **Remanente de ProducciÃ³n (MatemÃ¡tica Estricta):** El cÃ¡lculo de `pending_to_fabricate` (lo que falta por producir) en `ProductionController` se estabilizÃ³ usando la fÃ³rmula: `max(0, Requerimientos - StockFisico - EnDetallado - Enviados)`. Esto garantiza que los pedidos consideren el stock global de la empresa antes de mandar a fabricar. La UI muestra "X pendientes de Y totales" explÃ­citamente.

### ðŸ’¼ `sales`
```
id, user_id (FK â†’ users), client_id (FK â†’ clients, nullable),
total (decimal), paid_amount (decimal, default:0),
change_amount (decimal, default:0), payment_method (string, default:'Efectivo'),
signature (longText, nullable â€” base64),
stage (enum: pedido|confirmado|produccion|enviado|entregado|cancelado, default:'pedido'),
promised_date (date, nullable), is_partial_shipping (boolean, default:false),
timestamps
```
âš ï¸� `promised_date` se captura de forma obligatoria al crear el pedido en el POS (`SaleController::store()`). **Nota:** Actualmente no existe formulario para editarla posteriormente tras la creaciÃ³n del pedido.

### ðŸ“‹ `sale_details`
```
id, sale_id (FK â†’ sales, cascade), product_variant_id (FK â†’ product_variants),
product_name (snapshot, incluye medida y material), quantity (int),
chosen_color (nullable), custom_notes (text, nullable),
additional_cost (decimal, default:0),
discount_percent (int, default:0), unit_price (decimal), subtotal (decimal),
production_hold (boolean, default:false),
timestamps
```
- `production_hold`: flag de pausa automÃ¡tica que se enciende en envÃ­os parciales si restan piezas por entregar Y por fabricar. AÃ­sla las piezas de la cola de fabricaciÃ³n activa hasta su liberaciÃ³n manual.

### ðŸ“œ `sale_histories`
```
id, sale_id (FK â†’ sales, cascade), user_id (FK â†’ users),
from_stage (nullable), to_stage, notes (text, nullable), timestamps
```

### ðŸ’³ `sale_payments`
```
id, sale_id (FK â†’ sales, cascade), user_id (FK â†’ users),
amount (decimal), payment_method (string),
reference (nullable), paid_at (timestamp), timestamps
```
- Los pagos estÃ¡n ligados a `sale_id`, no a un cliente directamente. El "estado de cuenta por cliente" (Fase 3.2, Finanzas) se construye agregando esta tabla + `sales.paid_amount` por `client_id` â€” no requiere cambio de esquema.
- Registrado por `SalePaymentController::store()`, confirmado con validaciÃ³n de deuda (`amount <= total - paid_amount`) y transacciÃ³n atÃ³mica.

### âš™ï¸� `settings`
```
id, key (unique), value (text, nullable), timestamps
```
Claves activas en `$allowedKeys`: `company_name`, `company_rfc`, `company_address`, `company_phone`, `company_whatsapp`, `notification_emails`, `allow_negative_stock`, `ticket_footer_text`, `auto_email_on_sale`, `catalog_only_with_images`.
> âœ… Confirmado implementado: `auto_email_on_sale` controla el despacho diferido de notas de venta por correo al crear pedidos en el POS.

### ðŸ› ï¸� `production_completions` (v2.7)
```
id, sale_detail_id (FK â†’ sale_details, nullable),
work_order_id (FK â†’ work_orders, nullable),
quantity_completed (int),
user_id (FK â†’ users),
completed_at (timestamp),
timestamps
```
- Soporta avances originados tanto por pedidos de venta (`sale_detail_id`) como por Ã³rdenes de fabricaciÃ³n autÃ³nomas (`work_order_id`).
- Es la Ãºnica fuente confiable de "cuÃ¡nto se ha fabricado en total" para un pedido u orden. Al registrarse incrementa el `stock` de `product_variants`.

### ðŸ�­ `work_orders` (v2.7)
```
id, product_variant_id (FK â†’ product_variants),
quantity_requested (int),
target_date (date, nullable),
status (string: 'pending'|'in_progress'|'completed', default:'pending'),
origin_sale_detail_id (FK â†’ sale_details, nullable),
notes (text, nullable),
created_by (FK â†’ users),
timestamps
```
- Gestionado vÃ­a `WorkOrderController::store`. Permite producir piezas para stock de temporada sin requerir una venta previa.
- Al registrar avance en el Plan de ProducciÃ³n, suma directamente al stock de la variante correspondiente.

### ðŸšš `shipments` (v2.6)
```
id, driver_name (nullable), license_plate (nullable), destination (nullable),
status (string, default:'en_transito' â€” valores usados: 'en_transito','entregado','cancelado'),
shipped_at (nullable), pickup_type (string, default:'flota_propia'),
delivered_at (nullable), notes (text, nullable),
user_id (FK â†’ users), timestamps
```
- âœ… **Confirmado:** `pickup_type` (`'flota_propia'` / `'recoleccion_cliente'`) existe en la migraciÃ³n y estÃ¡ **implementado completo** en backend y frontend (ver secciÃ³n 5, ya no es un pendiente).
- âœ… **Confirmado:** `cancel()` existe en `ShipmentController` â€” el valor `'cancelado'` del `status` sÃ­ se usa en cÃ³digo real, ya no es solo un comentario de migraciÃ³n sin implementar.

### ðŸ“¦ `sale_deliveries` (v2.6)
```
id, shipment_id (FK â†’ shipments, cascade, obligatorio, no nullable),
sale_detail_id (FK â†’ sale_details, cascade),
quantity_delivered (int),
timestamps
```
- Tabla pivote. Permite envÃ­os parciales del mismo pedido en distintos viajes.
- Solo tiene estas 3 columnas + timestamps. El antiguo `SaleController::storeDelivery()` que intentaba escribir `user_id`/`delivered_at` (columnas inexistentes aquÃ­) **ya no existe en el cÃ³digo** â€” mÃ©todo, ruta y botÃ³n del frontend fueron eliminados.

---

## 3. Relaciones Eloquent (confirmadas contra cÃ³digo real)

```
User          â†’ hasMany(Sale), hasMany(Shipment), hasMany(ProductionCompletion)
Client        â†’ hasMany(Sale)
Category      â†’ hasMany(Product)
Product       â†’ belongsTo(Category) â†’ hasMany(ProductVariant) [cascade delete]
ProductVariantâ†’ belongsTo(Product)
Sale          â†’ belongsTo(User), belongsTo(Client)
              â†’ hasMany(SaleDetail)
              â†’ hasMany(SaleHistory)  ['history']
              â†’ hasMany(SalePayment)  ['payments']
SaleDetail    â†’ belongsTo(Sale)
              â†’ belongsTo(ProductVariant) â€” el mÃ©todo real es `variant()`, NO `productVariant()`.
                Confirmado en ProductionController y ShipmentController (`->with(['variant.product', ...])`).
                âš ï¸� Distinto de la columna cruda: la FK en el JSON hacia el frontend es `product_variant_id`
                (nombre real de la columna en `sale_details`), NO `variant_id`.
              â†’ hasMany(ProductionCompletion) ['completions']
              â†’ hasMany(SaleDelivery) ['deliveries']
SaleHistory   â†’ belongsTo(User)
SalePayment   â†’ belongsTo(Sale), belongsTo(User)
Shipment      â†’ belongsTo(User)
              â†’ hasMany(SaleDelivery) ['deliveries']
SaleDelivery  â†’ belongsTo(Shipment), belongsTo(SaleDetail)
ProductionCompletion â†’ belongsTo(SaleDetail), belongsTo(User)
Setting       â†’ getValue(), setValue(), getAll() [mÃ©todos estÃ¡ticos]
```

---

## 4. Rutas (`routes/web.php`) â€” Estado real confirmado

| Zona | Middleware real | Rutas incluidas |
|------|-----------|----------------|
| PÃºblica | â€” | `/` (Landing Page Blade), `/catalogo` (CatÃ¡logo Blade SSR), login, register |
| Dashboard | `auth,verified` (todos) | `/dashboard` â€” el controlador decide quÃ© mostrar segÃºn rol |
| Perfil | `auth,verified` | `/profile` |
| Ventas | `role:admin,vendedor` | `/pos`, `/sales/*`, `/clients` (crear/editar), pagos |
| Taller | `role:admin,produccion,supervisor` | `/production-plan`, `/production-plan/complete`, `/production-plan/print`, `/work-orders` (store), `/sale-details/{id}/release-hold` |
| Inventario | `role:admin,supervisor` | `/products/*` (CRUD completo y toggle favoritos) |
| Embarques | `role:admin,inventario,supervisor` | `/shipments/*` (index, create, store, show, confirm, print, cancel) |
| Admin | `role:admin` | `/users/*` (CRUD completo), `/clients/{id}` (destroy), `/configuracion` |

> âœ… **Confirmado resuelto:** las rutas de `/shipments/*` ya estÃ¡n restringidas a `admin,inventario`. Antes era un bug crÃ­tico (cualquier usuario autenticado podÃ­a crear/confirmar embarques); ya no es el caso.

### Rutas de Embarques (confirmadas completas)

| MÃ©todo | URI | Nombre | DescripciÃ³n |
|--------|-----|---------|-------------|
| GET | `/shipments` | `shipments.index` | Historial de viajes |
| GET | `/shipments/create` | `shipments.create` | UI para armar embarque (soporta `?client_ids[]=`) |
| POST | `/shipments` | `shipments.store` | Guardar embarque (transaccional, con `lockForUpdate`) |
| GET | `/shipments/{id}` | `shipments.show` | Detalle del viaje |
| GET | `/shipments/{id}/print` | `shipments.print` | PDF RemisiÃ³n â€” **no agrupa por cliente/pedido, es un listado plano** (ver secciÃ³n 5) |
| PATCH | `/shipments/{id}/confirm` | `shipments.confirm` | Confirmar entrega y cerrar pedidos (revisa TODAS las lÃ­neas) |
| PATCH | `/shipments/{id}/cancel` | `shipments.cancel` | âœ… Cancelar embarque y regresar stock â€” confirmado implementado |

---

## 5. Reglas de Negocio â€” Estado Real (25 jul 2026)

### âœ… RESUELTO â€” Doble mecanismo de descuento de stock

`SaleController::updateStage()` confirmado: su `validate()` solo acepta `pedido,confirmado,produccion,cancelado` â€” ya no permite mover a mano a `enviado` o `entregado`, y no toca stock en absoluto. `ShipmentController::store()` es ahora el Ãºnico punto que descuenta stock (con `ProductVariant::lockForUpdate()` para evitar condiciones de carrera entre lÃ­neas o usuarios concurrentes), y `ShipmentController::cancel()` es el Ãºnico que lo regresa.

### âœ… RESUELTO â€” Plan de ProducciÃ³n no descontaba lo ya fabricado

`ProductionController::index()` y `printReport()` confirmados con la fÃ³rmula unificada de faltantes:
```php
'pending_to_fabricate' => max(0, $totalNeeded - $stockFisico - $wipDetailed - $totalDelivered),
```
Badge de 4 estados confirmado en `Production/Index.vue` (sin fabricar / parcial / listo para embarcar / fabricado y ya embarcado). NavegaciÃ³n de semana (`Â« Ant.` / `Sig. Â»`) confirmada implementada. âš ï¸� El toggle "Ver todo acumulado" **no existe** en el cÃ³digo â€” sigue pendiente.

### âœ… RESUELTO â€” `storeDelivery()` roto

El mÃ©todo, la ruta `sales.deliveries.store` y el botÃ³n correspondiente en `Sales/Show.vue` **ya no existen** en el cÃ³digo.

### âœ… RESUELTO â€” Sin forma de cancelar un embarque

`ShipmentController::cancel()` confirmado, con las reglas de negocio documentadas respetadas en cÃ³digo:
- Flota propia: cancelable solo mientras `en_transito`, no una vez `entregado`.
- RecolecciÃ³n en mostrador: cancelable incluso despuÃ©s de `entregado` (es la Ãºnica forma de revertir un error de captura, ya que nace directo en ese estado).
- Regresa stock con `increment()`, revierte el `stage` del pedido al `from_stage` real tomado de `sale_histories` (no un valor fijo adivinado).
- Los `withSum('deliveries as delivered_quantity', ...)` en `ShipmentController::create()`, `SaleController::show()` y `closeOrderIfComplete()` confirmados con `whereHas('shipment', fn($q) => $q->where('status', '!=', 'cancelado'))` â€” un embarque cancelado no vuelve a contarse como entregado.

### âœ… IMPLEMENTADO COMPLETO â€” RecolecciÃ³n en mostrador vs. flota propia (`pickup_type`)

A diferencia de lo que indicaba el Backlog anterior (marcado como "backend listo, falta selector en frontend"), **estÃ¡ confirmado completo en ambos lados**:
- Backend: `ShipmentController::store()` crea el `Shipment` directo en `status = 'entregado'` con `delivered_at = now()` cuando `pickup_type === 'recoleccion_cliente'`, y ejecuta `closeOrderIfComplete()` en la misma transacciÃ³n.
- Frontend: `Shipments/Create.vue` confirmado con un toggle visual (`flota_propia` / `recoleccion_cliente`) que cambia el texto del formulario dinÃ¡micamente ("Chofer / Repartidor" vs. "Nombre de quien recoge").

### âš ï¸� PENDIENTE (confirmado, sin cambios respecto a lo documentado) â€” Notas de entrega no agrupadas por pedido

`ShipmentController::printManifest()` y la plantilla `resources/views/pdf/shipment_manifest.blade.php` fueron revisados: **no hay agrupaciÃ³n por cliente/pedido**, es un `@foreach($shipment->deliveries as $del)` plano. Si un viaje agrupa piezas de varios clientes, la remisiÃ³n los mezcla en una sola lista. Sigue pendiente construir la agrupaciÃ³n real.

### âœ… IMPLEMENTADO COMPLETO â€” Filtro multi-cliente en Embarques

`ShipmentController::create()` recibe `client_ids[]` vÃ­a query param y `Shipments/Create.vue` cuenta con el selector mÃºltiple integrado en la UI, filtrando correctamente las ventas disponibles por los clientes seleccionados.

### âš ï¸� PatrÃ³n de rendimiento â€” modelos completos viajando sin usarse

Confirmado ya corregido en `ShipmentController::create()`, `index()` y `show()`: usan `select()` explÃ­cito, sin `signature` (base64 pesado) ni columnas de precio para roles de Inventarios.

**Pendiente confirmado, sin corregir todavÃ­a:**
- `ProductController::index()` â€” `Product::with(['category','variants'])->get()` sin `select()` ni paginaciÃ³n: carga todos los productos con todas sus variantes completas (incluidos los 5 precios) en cada visita al catÃ¡logo interno.
- `SaleController::create()` (POS) â€” `Client::all()` sin lÃ­mite ni `select()`, se ejecuta en cada apertura del POS.

### Roles y RedirecciÃ³n al Login
- `admin`, `vendedor` â†’ `/dashboard`
- `produccion` â†’ `/production-plan`
- `inventario`, `supervisor`, `financiero` â†’ `/dashboard` (pantalla de bienvenida temporal â€” sin mÃ³dulo propio todavÃ­a, confirmado en `DashboardController`)

### Modo Oficina / Modo Taller (`Sales/Show.vue`)
âœ… Confirmado resuelto: `is_production_mode` se fuerza automÃ¡ticamente a `true` desde el backend (`SaleController::show()`) para los roles `produccion`, `inventario` y `supervisor`. Adicionalmente, se emplea `$sale->makeHidden()` para garantizar que los importes financieros y precios unitarios no viajen en el JSON hacia el cliente en estos roles.

---

## 6. Componentes Vue â€” Estado Real (confirmado contra cÃ³digo)

| Componente | Estado | Notas |
|-----------|--------|-------|
| `Dashboard.vue` | âœ… | KPIs por rol; pantalla bienvenida para roles sin dashboard propio |
| `Sales/Create.vue` | âœ… | POS completo |
| `Sales/Index.vue` | âœ… | Kanban â€” ya no permite mover a mano a `enviado`/`entregado` |
| `Sales/Show.vue` | âœ… | Modo Oficina/Taller (por query param, no forzado por rol aÃºn), abonos |
| `Production/Index.vue` | âœ… | NavegaciÃ³n de semana y badge de 4 estados confirmados. |
| `Products/Create.vue` / `Edit.vue` | âœ… | Variantes, materiales, medidas, imagen, favorito |
| `Products/Index.vue` | âš ï¸� | Funcional, pero `ProductController::index()` no pagina server-side |
| `Clients/*`, `Users/*`, `Settings/Index.vue` | âœ… | `Users/*` confirmado funcional contra `UserController.php` real (CRUD completo, `VALID_ROLES` coincide con los 6 roles del sistema) |
| `Shipments/Index.vue` | âœ… | Lista de viajes, imprimir, confirmar, **cancelar** (badge de 3 estados: en trÃ¡nsito/entregado/cancelado) |
| `Shipments/Create.vue` | âœ… | Armar embarque, validaciÃ³n de stock compartido entre lÃ­neas, toggle flota propia / mostrador y **selector multi-cliente**. |
| `Shipments/Show.vue` | âœ… | Detalle con `chosen_color`, fechas y piezas a bordo |

---

## 7. Arquitectura de Piezas Clave

### `SaleObserver.php`
- Al **crear** venta â†’ registra en `sale_histories` automÃ¡ticamente.
- Al **actualizar** stage â†’ registra `from_stage` / `to_stage` automÃ¡ticamente.
- No toca stock â€” eso ahora vive Ãºnicamente en `ShipmentController`.

### `CheckRole.php`
- ParÃ¡metro variÃ¡dico: `handle(Request $request, Closure $next, string ...$roles)`. Confirmado correcto en cÃ³digo real.
- Registrado en `bootstrap/app.php` como alias `'role'`.

### `HandleInertiaRequests.php`
- Comparte `auth.user` globalmente â†’ disponible en cualquier `.vue` como `$page.props.auth.user`.

### `AppServiceProvider.php`
- Registra `SaleObserver`, fuerza HTTPS en producciÃ³n, `Carbon::setLocale('es')`.

### `AuthenticatedSessionController.php`
- Login usa `redirect()->away()` (no `redirect()->intended()`) para forzar recarga completa.
- Logout tambiÃ©n usa `redirect()->away('/')` por la misma razÃ³n.

---

## 8. Paquetes Instalados (confirmado con `composer.lock` y `package.json` reales)

```
PHP: barryvdh/laravel-dompdf ^3.1.1 (resuelve a 3.1.2), laravel-lang/common,
     laravel/framework ^12.0 (resuelve a 12.62.0)

JS â€” dependencies (runtime):
     lodash ^4.17.21, sweetalert2 ^11.26.17, vue-signature-pad ^3.0.2

JS â€” devDependencies (build/tooling):
     @inertiajs/vue3 ^2.0.0, @tailwindcss/forms ^0.5.3, @tailwindcss/vite ^4.0.0 (âš ï¸� sin usar, ver secciÃ³n 0),
     @vitejs/plugin-vue ^6.0.7, autoprefixer ^10.4.12, axios ^1.18.0, concurrently ^9.0.1,
     laravel-vite-plugin ^2.0.0, postcss ^8.4.31, tailwindcss ^3.2.1, vite ^7.0.7, vue ^3.4.0
```

---

## 9. Matriz de Roles y Permisos (Propuesta â€” parcialmente implementada, confirmado contra rutas reales)

6 roles: `admin`, `supervisor`, `vendedor`, `inventario`, `produccion`, `financiero`.

| MÃ³dulo | Admin | Supervisor | Vendedor | Inventarios | ProducciÃ³n | Finanzas |
|---|---|---|---|---|---|---|
| Dashboard | âœ… implementado | â�Œ sin mÃ³dulo (pantalla bienvenida) | âœ… implementado | â�Œ sin mÃ³dulo | â�Œ sin mÃ³dulo | â�Œ sin mÃ³dulo |
| POS / Crear pedido | âœ… | â�Œ | âœ… | â�Œ | â�Œ | â�Œ |
| Ventas (Kanban, mover etapa) | âœ… todas | â�Œ fuera de alcance de la reuniÃ³n del 04 ago | âœ… solo las suyas | â�Œ | â�Œ | â�Œ sin acceso hoy |
| Clientes (CRUD) | âœ… completo | â�Œ sin acceso hoy | âœ… crear/editar | â�Œ | â�Œ | â�Œ sin acceso hoy |
| Plan de ProducciÃ³n | âœ… | âœ… **Implementado â€” acceso completo** | â�Œ | â�Œ | âœ… ver + registrar fabricado | â�Œ |
| Embarques (crear/confirmar/cancelar) | âœ… | âœ… **Implementado â€” acceso completo** | â�Œ | âœ… | â�Œ sin acceso hoy | â�Œ sin acceso hoy |
| Pagos/Abonos (`sale_payments`) | âœ… | â�Œ | âœ… (de sus ventas) | â�Œ | â�Œ | â�Œ sin acceso hoy |
| Productos/Inventario (CRUD) | âœ… | âœ… **Implementado â€” acceso completo** | ðŸ‘�ï¸� ver (para POS) | â�Œ sin acceso hoy | â�Œ | â�Œ |
| Usuarios | âœ… confirmado completo | â�Œ | â�Œ | â�Œ | â�Œ | â�Œ |
| ConfiguraciÃ³n | âœ… | â�Œ | â�Œ | â�Œ | â�Œ | â�Œ |

**Diferencia importante con la matriz "propuesta" de versiones anteriores de este documento:** antes describÃ­a el diseÃ±o *deseado* a futuro (Supervisor de solo lectura en todo). **Acuerdo de reuniÃ³n (04 ago 2026):** eso cambiÃ³ â€” el cliente ahora quiere a Supervisor con permisos de ediciÃ³n completos (no solo lectura) en ProducciÃ³n, AlmacÃ©n y Embarques, al mismo nivel que Admin. Sigue sin acceso a Ventas/Kanban ni ConfiguraciÃ³n salvo que se amplÃ­e despuÃ©s. `financiero` sigue sin ninguna ruta asignada â€” Fase 3, sin empezar.

### ðŸ”’ Matriz de Visibilidad de Precios â€” estado real

Confirmado en cÃ³digo:
- `ShipmentController::create()` ya no manda `price_1..price_5` en el JSON (columnas explÃ­citamente excluidas con `variant:id,product_id,material,measurements,stock`).
- `SaleController::index()` ya usa `select()` explÃ­cito sin `signature`.
- **Pendiente confirmado:** `SaleController::index()` no condiciona el `select()` por rol (hoy nadie mÃ¡s que admin/vendedor llega a esa ruta, asÃ­ que no es explotable todavÃ­a, pero tampoco estÃ¡ la lÃ³gica lista para cuando se abra a Supervisor). `Sales/Show.vue` no fuerza Modo Taller por rol en el backend.

### DiseÃ±o del "Estado de Cuenta" por cliente (Finanzas) â€” sigue siendo diseÃ±o, no implementaciÃ³n

No se requiere tabla nueva. Se calcularÃ­a agregando `sale_deliveries` (join con `shipments.status = 'entregado'`) contra `sales.paid_amount` + `sale_payments.amount`, agrupado por `client_id`. **Confirmado: no hay ningÃºn controlador ni ruta que calcule esto todavÃ­a** â€” es 100% diseÃ±o para la Fase 3.2.

---

## 10. Variables de Entorno

```ini
APP_ENV=local|production
APP_DEBUG=true|false
APP_URL=http://localhost|https://tudominio.com
DB_CONNECTION=mysql
DB_DATABASE=taller360

# Solo en producciÃ³n (Neubox):
FILESYSTEM_PUBLIC_ROOT=/home/usuario/public_html/storage
```

---

## 11. CÃ³mo Compartir Contexto con IA al Retomar

Al iniciar sesiÃ³n nueva:
1. Comparte este archivo (`CONTEXTO_TECNICO.md`).
2. Comparte los archivos especÃ­ficos de la tarea a trabajar (controlador + vista Vue involucrados, y la migraciÃ³n si aplica). Si vas a compartir un zip completo, **excluye `vendor/`, `node_modules/`, `storage/logs`, `storage/framework` y `.git`** â€” no aportan nada y hacen el zip pesado.
3. Si la tarea toca el frontend/build, incluye `tailwind.config.js` y `postcss.config.js` â€” no se auditaron todavÃ­a (ver hallazgo del conflicto de versiones de Tailwind, secciÃ³n 0).
4. Describe quÃ© quieres hacer.

Para tareas grandes de Fase 3 en adelante, abre sesiones separadas por sub-tarea.


---

## 12. Reglas de SEO y Branding

- **Prohibido el uso hardcoded de 'Taller 360'**: 'Taller 360' es el nombre interno de desarrollo del software. Nunca debe ser expuesto en etiquetas `<title>`, `<meta>`, URLs públicas o texto visible para el cliente final.
- **Uso de la variable de base de datos**: Todos los títulos de página y metadatos SEO deben leer estrictamente la clave `company_name` de la tabla `settings` (ej. 'Industria Santa Cecilia').
- **Inertia/Vue**: Para el backend, la configuración ya se comparte a través del middleware `HandleInertiaRequests.php` (`page.props.settings.company_name`). El sufijo dinámico está configurado en `resources/js/app.js` en lugar de depender de la variable de entorno local (`VITE_APP_NAME`).
- **Vistas Blade (Públicas)**: Las vistas como el Landing y Catálogo utilizan directamente `$settings['company_name']` con fallback explícito a 'Industria Santa Cecilia', omitiendo nombres genéricos.
