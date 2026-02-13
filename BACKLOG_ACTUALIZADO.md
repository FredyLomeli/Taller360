# 📋 BACKLOG MAESTRO - TALLER 360 (v2.5)
**Estado:** Pre-Producción / Listo para Despliegue
**Fecha de Actualización:** 13 Febrero 2026 (Post-Sprint Finanzas & Manufactura)

---

## ✅ 1. COMPLETADO (Hitos Recientes v2.5)
*Módulos desplegados y listos para subir a Neubox.*

* **🏭 Plan Maestro de Producción:**
    * [x] **Explosión de Insumos:** Algoritmo que agrupa pedidos en "Producción" por Modelo + Material.
    * [x] **Vista de Taller:** Interfaz limpia sin precios para impresión/tablet (`Production/Index.vue`).
    * [x] **Acceso Directo:** Botón en Sidebar y Tablero Kanban.
* **💰 Ciclo Financiero (Abonos):**
    * [x] **Bitácora de Pagos:** Backend (`SalePaymentController`) para registrar abonos parciales.
    * [x] **Validación de Saldo:** Bloqueo de abonos que excedan la deuda pendiente.
    * [x] **Interfaz de Cobranza:** Modal de pago y tabla de historial dentro del detalle de venta.
* **📄 Detalle de Venta Híbrido (`Sales/Show.vue`):**
    * [x] **Switch de Contexto:** Botón para alternar entre "Modo Oficina" (Financiero) y "Modo Taller" (Técnico).
    * [x] **Navegación:** Enlaces directos desde el Folio en el Kanban.
* **🛡️ Optimización Hosting Compartido:**
    * [x] **PDFs Blindados:** Lógica `FILESYSTEM_PUBLIC_ROOT` para leer logos sin bloqueos HTTP.
    * [x] **HTTPS Force:** Configuración en `AppServiceProvider` para producción.
    * [x] **Seeders v2:** Corrección de lectura de CSVs compactos (Precios).

---

## 🔥 2. PRIORIDAD INMEDIATA (Deuda Técnica Crítica)
*Lo único que falta para que el sistema sea autosuficiente sin CSVs.*

### 1. 📦 Refactor Formulario Productos (`Products/Create.vue`) - **[CRÍTICO]**
* **Estado:** 🛑 **ROTO / DESACTUALIZADO**.
* **Problema:** El formulario actual intenta guardar el campo `color` (que ya no existe en la tabla productos) y le faltan los campos clave de la v2.
* **Acción Requerida:**
    * [ ] Eliminar input "Color" (Ahora es atributo de venta).
    * [ ] Agregar Select "Material" (MDF, Madera, Melamina) -> Esto define la variante.
    * [ ] Agregar Checkbox "Favorito" (Vital para el widget de Stock Bajo).
    * [ ] Permitir carga de imagen (Input File).

### 2. 📉 Reporte de Corte de Caja (Excel)
* **Estado:** 🚧 **PENDIENTE**.
* **Objetivo:** El cliente necesita saber cuánto dinero entró hoy.
* **Acción:**
    * Crear botón "Exportar Corte" en Dashboard.
    * Generar Excel sumando `sale_payments` del día filtrado por método (Efectivo vs Banco).

### 3. 📦 Ajuste Manual de Inventario
* **Estado:** 🚧 **PENDIENTE**.
* **Problema:** Actualmente el stock solo baja con ventas. No hay forma de "reponer" stock cuando el taller termina de fabricar sin venderlo inmediatamente o registrar mermas.
* **Acción:** Crear vista simple de "Entradas/Salidas" (+10 / -5).

---

## 🐛 3. BUGS CONOCIDOS
*Errores visuales o funcionales detectados.*

1.  **🔄 Logout "Inception":** Al cerrar sesión por inactividad (419 Page Expired), el Login carga dentro de un modal o iframe en lugar de redireccionar toda la página. (Fix: Forzar `window.location` en el interceptor de Axios/Inertia).
2.  **💵 Input Moneda (Safari/iOS):** Validar que los campos de dinero no permitan caracteres no numéricos que rompan el cálculo en dispositivos Apple.

---

## 🚀 4. FUTURO CERCANO (Fase 3.0)

1.  **✉️ Notificaciones Automáticas:** Enviar correo al cliente cuando su pedido pase a estado "Enviado".
2.  **🧾 Facturación CFDI 4.0:** Integración para timbrado fiscal (México).
3.  **🚚 Envíos Parciales:** Capacidad de marcar como "Entregado" solo una parte de la orden (ej. Entregar sillas hoy, mesa mañana).
4.  **📱 PWA:** Convertir el sistema en "App Instalable" para que los choferes puedan marcar entregas desde el celular.