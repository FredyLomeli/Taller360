# ESTATUS DEL PROYECTO: SISTEMA POS MUEBLERÍA

## Tecnologías
- Laravel 12 (Backend)
- Vue 3 + Inertia.js (Frontend)
- Tailwind CSS (Estilos)
- MySQL (Base de Datos)
- DomPDF (Reportes PDF)
- SweetAlert2 (Alertas)

## Objetivo Actual
Desarrollar un punto de venta completo, priorizando la operación de mostrador y control de inventario básico.

## 🟢 FUNCIONALIDADES TERMINADAS (NO TOCAR SALVO ERRORES)
1.  [x] **Buscador de productos:** Funciona por Nombre y SKU.
2.  [x] **Categorías:** Filtrado por botones en el POS.
3.  [x] **Manejo de Stock:** Input manual y botones +/-.
4.  [x] **Modal de Cobro:** Soporta Efectivo, Tarjeta, Transferencia y detecta Créditos.
5.  [x] **Descuentos:** Porcentaje por partida individual.
6.  [x] **Buscador de Clientes:** Autocomplete funcional.
7.  [x] **Documentos:** Generación de Ticket (Térmico) y Nota de Remisión (Carta PDF) con Folio.
8.  [x] **Envío de Correo:** Lógica implementada (falta config de remitentes).
9.  [x] **Cancelación:** Botón en historial que devuelve stock automáticamente.
10. [x] **Zona Horaria:** Configurado a America/Mexico_City.

## 🟠 LISTA DE PENDIENTES PRIORITARIOS (ORDEN DE EJECUCIÓN)

### 1. Gestión de Clientes en POS (Punto 6 original)
- **Tarea:** Agregar botón "+" en el buscador de clientes del POS.
- **Detalle:** Permitir registrar un cliente nuevo (Nombre, RFC, Dirección, Teléfono) mediante un modal sin salir de la pantalla de venta ni perder el carrito.

### 2. Traducción y UX (Puntos 8 y 9 original)
- **Tarea:** "Españolizar" el sistema.
- **Detalle:** - Instalar paquete de idioma (mensajes de error "Field is required").
    - Traducir Login, Perfil y Fechas (Carbon).
    - Reemplazar "confirm()" nativos por SweetAlert2 en eliminación de Productos y Clientes.

### 3. Configuraciones Generales (Soporte para Punto 11 original)
- **Tarea:** Crear módulo de Configuración (`Settings`).
- **Detalle:** Formulario para subir Logo (usado en PDF), Nombre Empresa, Dirección y **Correos Administrativos** (para el envío de copias de ventas).

### 4. Roles y Permisos (Punto 10 original)
- **Tarea:** Diferenciar Admin vs Vendedor.
- **Detalle:** El vendedor solo puede vender. No puede ver configuraciones, ni borrar productos, ni ver costos (si los hubiera).

### 5. Dashboard y KPIs (Punto 12 nuevo)
- **Tarea:** Poblar la pantalla de inicio.
- **Detalle:** Tarjetas de Ventas del día, Gráfica de semana, Tabla de Stock Bajo.

### 6. Corte de Caja (Sugerencia aceptada)
- **Tarea:** Cierre de turno.
- **Detalle:** Reporte de cuánto dinero debe haber en caja.

---
## NOTAS TÉCNICAS RECIENTES
- El PDF de Nota de Venta usa estilos CSS en línea para compatibilidad con DomPDF.
- La zona horaria es Mexico_City, recordar limpiar caché (`php artisan config:clear`) si hay desfases.
- Los botones del modal de venta usan un Grid 2x2.