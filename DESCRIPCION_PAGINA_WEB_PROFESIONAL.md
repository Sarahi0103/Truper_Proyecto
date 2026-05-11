# 📊 DESCRIPCIÓN PROFESIONAL DE LA PLATAFORMA TRUPER
**Documento de Análisis Detallado por Secciones**

---

## 🎯 RESUMEN EJECUTIVO

**Truper Platform** es una plataforma web integral de distribución y ventas especializada en herramientas y productos industriales. Ofrece una experiencia multi-canal que integra:

- **Canal Retail**: Catálogo digital con acceso público limitado
- **Canal Mayorista**: Sistema de cotizaciones y ventas al por mayor
- **Gestión Administrativa**: Panel de control interno para operaciones
- **Experiencia Omnichannel**: Seamless integration entre todos los canales

---

## 🏗️ ARQUITECTURA GENERAL

### Stack Tecnológico
| Componente | Tecnología |
|-----------|-----------|
| Backend | PHP 7.4+ |
| Base de Datos | PostgreSQL 15 |
| Frontend | Vue.js 3 + Vite |
| Diseño Responsivo | Mobile-First (Tailwind CSS / Bootstrap Vue) |
| Seguridad | CSRF Protection, Session Management, Headers de Seguridad |
| Hosting | Servidor Web Tradicional (Apache/Nginx) |

### Paleta de Colores
- **Primario**: Naranja (#FF8C00) - Energía, confianza, dinamismo
- **Secundario**: Negro (#000000) - Profesionalismo, elegancia
- **Neutro**: Blanco (#FFFFFF) - Claridad, limpieza
- **Estados**: Verde (#28a745), Rojo (#dc3545), Amarillo (#ffc107)

---

## 📑 DESCRIPCIÓN POR SECCIONES

### 1️⃣ NAVEGACIÓN PRINCIPAL

#### Barra de Navegación (Navbar)
**Ubicación**: Fixed en la parte superior (z-index: 1000)

**Componentes**:
- **Logo Truper**: Ícono estilizado en naranja con letras, posicionado a la izquierda. Clickeable para volver a inicio
- **Menú Principal** (horizontal, derecha):
  - **Catálogo** → Acceso a `/public/index.php` (catálogo principal)
  - **Marketplace CE** → Acceso a `/public/marketplace_ce.php` (segundo canal - productos reacondicionados)
  - **Carrito** → Icono flotante mostrando cantidad de items `/public/cart.php`
  - **Dashboard** → Panel personal/administrativo `/public/dashboard.php`
  - **Pedidos** → Historial de órdenes `/public/orders.php`
  - **Mayoreo** → Canal especializado `/public/wholesale.php`
  - **Caja** → Punto de venta (módulo Cashier) `/public/cashier.php`
  - **Abastecimiento** → Módulo de supply (solo admin) `/public/admin_supply.php`
  - **Tareas** → Gestión de tareas para empleados `/public/tasks.php`
  - **Estadísticas** → Analytics y reportes `/public/analytics.php`
  - **Perfil** → Gestión de perfil de usuario `/public/profile.php`

**Características**:
- Fondo negro (#000000) con texto blanco
- Hover en links: cambio a naranja (#FF8C00)
- Responsive: Collapsa a menú hamburguesa en dispositivos móviles
- Sticky: Se mantiene visible al scroll
- Modo oscuro/claro: Toggle button visible

#### Sistema de Tema (Modo Claro/Obscuro)
**Implementación Sofisticada**:
- **Toggle Button**: Botón prominente en esquina superior derecha ("Modo obscuro" / "Modo claro")
- **Tecnología**: Sistema CSS con variables definidas en `theme.css`
- **Data Attribute**: Usa `data-theme="dark"` o `data-theme="light"` en elemento `<html>`
- **Variables CSS Globales**: 
  - `--theme-bg` (fondo)
  - `--theme-surface` (superficies)
  - `--theme-text` (texto)
  - `--theme-accent` (acentos)
  - Y 5+ más para colores secundarios
- **Persistencia**: localStorage (clave: `theme`)
- **Transición Suave**: Cambios de color animados (0.3s ease)
- **Compatibilidad**: Funciona en todos los navegadores modernos

---

### 2️⃣ PÁGINA DE INICIO (HOME)

#### Hero Section
**Propósito**: Impacto visual inmediato, call-to-action principal

**Composición**:
- **Headline**: "Truper - Tu Distribuidor de Herramientas y Productos de Confianza"
- **Subheadline**: Tagline descriptivo del valor de marca
- **CTA Primario**: Botón "Ver Catálogo" → Redirecciona a `/views/products.php`
- **Fondo**: Gradiente o imagen de hero con overlay oscuro
- **Animación**: Parallax scroll opcional

#### Sección de Características (Features Grid)
**Diseño**: Grid de 4 columnas (2x2 en tablet, 1 en móvil)

**Tarjetas de Características**:

| # | Característica | Descripción | Ícono |
|---|---|---|---|
| 1 | 📦 Catálogo Digital | Acceso rápido a catálogo completo de productos | Caja |
| 2 | 🎁 Programa de Puntos | Acumula puntos en cada compra y disfruta bonos especiales | Regalo |
| 3 | 🚀 Pedidos Rápidos | Ordena fácilmente desde navegador | Cohete |
| 4 | 💰 Ventas Mayoreo | Cotizaciones especiales para negocios mayoristas | Dinero |

**Estilo**:
- Fondo blanco con sombra ligera (box-shadow)
- Ícono emoji grande (3rem) en color naranja
- Título en negro, descripción en gris
- Hover: Elevación (transform: translateY(-5px))

#### Sección "Novedades del Punto de Venta"
**Propósito**: Mantener contenido actualizado y relevante

**Características**:
- **Carrusel Automático**: Rotación cada 5 segundos
- **Controles**: Botones "Anterior" y "Siguiente" manuales
- **Indicadores**: Puntos de progresión del carrusel
- **Contenido Destacado**: Imagen + Texto sobre promociones actuales

#### Sección "Publica tus Promociones"
**Contexto**: Módulo de Abastecimiento (Portal administrativo)

**Descripción**:
- Permite crear tarjetas promocionales con imagen, título y contenido
- Dirigido a administrators del sistema
- Facilita comunicación de ofertas especiales a clientes

#### Footer
**Ubicación**: Base de todas las páginas

**Contenido**:
- Copyright: "© 2026 Truper. Todos los derechos reservados."


**Estilo**:
- Fondo: Negro (#000000)
- Texto: Blanco (#FFFFFF)
- Alineación: Centro
- Padding: 2rem

---

### 3️⃣ CATÁLOGO DE PRODUCTOS

#### Estructura General
**Layout**: 2-column (Sidebar + Main Content)

#### Columna Izquierda: Panel de Filtros
**Componentes**:

1. **Búsqueda de Productos**
   - Input de texto con placeholder: "Buscar producto..."
   - Búsqueda en tiempo real (JavaScript)
   - Busca en: nombre, SKU, descripción

2. **Filtro por Categoría**
   - Select dropdown con opciones:
     - Todas las categorías
     - Herramientas
     - Hardware
     - Electrónica
     - Industrial
   - Aplica filtro dinámicamente

3. **Filtros Adicionales** (Extensibles):
   - Rango de precio (slider)
   - Disponibilidad (stock/agotado)
   - Ordenamiento: Relevancia, Precio (asc/desc), Nombre

#### Columna Derecha: Cuadrícula de Productos

**Header de la Sección**:
- Título: "Catálogo de Productos"
- Subtítulo: "Amplia selección de herramientas y productos de calidad"

**Grid de Productos**:
- **Layout**: 4 columnas (responsive: 2 en tablet, 1 en móvil)
- **Cards de Producto**: Cada tarjeta contiene:

```
┌─────────────────────┐
│   Imagen Producto   │ (Placeholder gris por defecto)
├─────────────────────┤
│ Nombre del Producto │ (Título bold, negro)
│ SKU: ABC-123-XYZ    │ (Gris, pequeño)
│ Descripción truncada │ (100 caracteres + ellipsis)
│ a 100 caracteres... │
├─────────────────────┤
│  $1,234.50          │ (Naranja, bold, izquierda)
│ [Agregar al Carrito]│ (Botón naranja, derecha)
└─────────────────────┘
```

**Interactividad**:
- Click en "Agregar": Añade a carrito (localStorage)
- Hover en card: Sombra aumentada, bordes redondeados destacados
- Si no está autenticado: Muestra enlace "Login" en lugar de "Agregar"

---

### 4️⃣ CARRITO DE COMPRAS

#### Carrito Flotante (FAB - Floating Action Button)
**Ubicación**: Esquina inferior derecha, fixed

**Estados**:
- **Oculto**: Cuando carrito está vacío
- **Visible**: Cuando hay items agregados
- **Mostrar contador**: Número de items entre paréntesis

**Comportamiento**:
- Click: Abre drawer lateral del carrito
- Animación: Entrada desde derecha (slide-in)

#### Drawer (Panel Lateral del Carrito)
**Composición**:

1. **Header**:
   - Título: "Tu Carrito"
   - Botón cerrar (✕) en esquina superior derecha

2. **Lista de Items**:
   - Por cada producto agregado:
     ```
     Nombre Producto × Cantidad = $Subtotal
     [Reducir] [Aumentar] [Eliminar]
     ```
   - Scroll si hay muchos items

3. **Resumen del Carrito**:
   - Subtotal
   - Total (con impuestos si aplica)
   - Bloque de botones:
     - **⬇️ Descargar Ticket**: Genera PDF con detalles
     - **📱 Enviar cotización por WhatsApp**: Share a WhatsApp
     - **🗑️ Vaciar Carrito**: Limpia todos los items

**Datos Persistidos**: localStorage (truper_cart)

---

### 5️⃣ AUTENTICACIÓN Y AUTORIZACIÓN

#### Página de Login
**Ruta**: `/views/login.php`

**Formulario**:
```
┌─────────────────────────────────┐
│         TRUPER LOGO             │
│    INICIAR SESIÓN               │
├─────────────────────────────────┤
│ Email: [____________]           │
│ Contraseña: [____________]      │
│ ☐ Recuérdame                   │
│ [Ingresar] (Botón Naranja)     │
├─────────────────────────────────┤
│ ¿No tienes cuenta? Regístrate   │
│ ¿Olvidaste tu contraseña?       │
└─────────────────────────────────┘
```

**Componentes**:
- Input Email (requerido, validación)
- Input Contraseña (requerido, masked)
- Checkbox "Recuérdame" (mantiene sesión)
- CSRF Token (seguridad)
- Links: Registro, Recuperación de contraseña

**Validación**:
- Backend: Verificación contra base de datos
- Frontend: Validación básica HTML5

#### Página de Registro
**Ruta**: `/views/register.php`

**Campos**:
- Nombre completo
- Email
- Teléfono (opcional)
- Contraseña
- Confirmar contraseña
- Términos y condiciones (checkbox)

**Flujo**:
1. Validación frontend (email único, contraseña fuerte)
2. Envío POST a `/backend/controllers/auth_controller.php`
3. Creación de usuario en BD
4. Redirección a login o dashboard

---

### 6️⃣ PANEL DE USUARIO (DASHBOARD)

**Ruta**: `/views/dashboard.php` (Requiere autenticación)

#### Layout
**Structure**: 2-column (Sidebar + Main)

#### Sidebar
**Contenidos**:
- Avatar/Foto de usuario
- Nombre de usuario
- Email de usuario
- **Menú de navegación**:
  - 📊 Dashboard (activo/highlighted)
  - 📋 Mis Pedidos
  - 👤 Mi Perfil
  - ⭐ Mis Puntos
  - 🏪 Solicitar Mayoreo

**Estilo**: Fondo gris claro, texto oscuro, indicador de sección activa en naranja

#### Main Content

**1. Header de Bienvenida**:
```
¡Bienvenido, [Nombre Usuario]!
```

**2. Grid de Tarjetas KPI** (3 columnas, responsive):

| Tarjeta | Contenido | CTA |
|---------|-----------|-----|
| **Puntos Acumulados** | Número grande | "Redimibles en próxima compra" |
| **Pedidos Totales** | Contador | Link a "Ver todos" |
| **Información de Cuenta** | "Miembro desde DD/MM/YYYY" | Link "Editar perfil" |

**3. Tabla de Pedidos Recientes**:
- Últimos 5 pedidos
- Columnas: ID, Fecha, Total, Estado, Estado de Pago, Acciones
- Click en "Ver": Redirecciona a `/views/order_detail.php?id=[ID]`
- Estados con badges de color (pending=amarillo, completed=verde, cancelled=rojo)

---

### 7️⃣ MIS PEDIDOS

**Ruta**: `/views/my_orders.php`

**Composición**:
- **Header**: "Mis Pedidos"
- **Filtros**: Por fecha, estado, monto
- **Tabla de Pedidos**: Todas las órdenes del usuario
- **Paginación**: Si hay muchos pedidos

**Interactividad**:
- Click en fila: Expande detalles o navega a detalle completo
- Opción de repetir pedido
- Opción de descargar factura (PDF)

---

### 8️⃣ DETALLE DE PEDIDO

**Ruta**: `/views/order_detail.php?id=[ID]`

**Secciones**:

1. **Información de Envío**:
   - Dirección de envío
   - Fecha de envío estimada
   - Número de tracking (si aplica)

2. **Información de Factura**:
   - ID Pedido
   - Fecha de creación
   - Total
   - Estado de pago

3. **Items del Pedido**:
   - Tabla con: Producto, Cantidad, Precio Unitario, Subtotal
   - Total de la orden al final

4. **Acciones**:
   - Descargar comprobante (PDF)
   - Volver a ordernar
   - Contactar soporte

---

### 9️⃣ MIS PUNTOS

**Ruta**: `/views/my_points.php`

**Contenido**:
- **Saldo de Puntos**: Número grande y destacado
- **Historial de Puntos**: Tabla con:
  - Fecha de transacción
  - Descripción (Compra, Regalo, Redención)
  - Puntos ganados/utilizados
  - Saldo después de transacción

- **Opciones de Redención**:
  - Ver canjeables disponibles
  - Información de cómo usar puntos
  - Relación: X puntos = $ descuento

---

### 🔟 MI PERFIL

**Ruta**: `/views/profile.php`

**Formulario de Edición**:
```
Nombre: [_________]
Email: [_________]
Teléfono: [_________]
Dirección: [_________]
Ciudad: [_________]
Código Postal: [_________]
País: [_________]

[Guardar Cambios] [Cancelar]
```

**Secciones Adicionales**:
- Cambio de contraseña
- Información de facturación
- Dirección de envío por defecto
- Preferencias de notificación

---

### 1️⃣1️⃣ MÓDULO MAYORISTA

**Ruta**: `/views/wholesale.php`

**Propósito**: Solicitar acceso a precios mayoristas

#### Formulario de Solicitud
```
Nombre de la Empresa: [_________]
Email de Contacto: [_________]
Teléfono: [_________]

Tipo de Negocio: [Dropdown]
- Ferretería
- Tienda de Herramientas
- Empresa de Construcción
- Distribuidor Industrial
- Otro

Descripción del Negocio:
[_________________________________________
_________________________________________
_________________________________________]

[Enviar Solicitud]
```

**Backend**:
- Crea registro en tabla `wholesale_requests`
- Email de confirmación al usuario
- Notificación a administrador para revisión
- Cambio de estado cuando se aprueba

#### Acceso Mayorista (Post-Aprobación)
**Cambios en plataforma**:
- Visualización de precios mayoristas
- Cantidades mínimas de compra
- Términos de pago especiales
- Cotizaciones personalizadas

---

### 1️⃣2️⃣ PANEL ADMINISTRATIVO

**Ruta**: `/admin_login.php` → `/admin/dashboard.php`

**Restricción**: Solo usuarios con rol 'admin'

#### Página de Login Administrativo
```
┌─────────────────────────────────┐
│    SOLO ADMINISTRADORES         │
│    (Con restricción de acceso)  │
├─────────────────────────────────┤
│ Email o teléfono: [__________]  │
│ Contraseña: [__________]        │
│ [Entrar al panel]               │
├─────────────────────────────────┤
│ Acceso exclusivo para admin     │
└─────────────────────────────────┘
```

#### Dashboard Administrativo

**Módulos principales**:

1. **📊 Analítica General**:
   - Ventas totales (período)
   - Órdenes completadas
   - Usuarios activos
   - Ingresos recurrentes

2. **📦 Gestión de Inventario**:
   - Agregar/editar/eliminar productos
   - Stock disponible
   - Alertas de bajo stock
   - Códigos de barras (barcode scanner)

3. **💼 Gestión de Pedidos**:
   - Estado de pedidos
   - Asignación a proveedores
   - Actualización de estado de envío
   - Generación de documentos

4. **👥 Gestión de Usuarios**:
   - Lista de usuarios
   - Roles y permisos
   - Historial de actividades
   - Bloqueo/desbloqueo de cuentas

5. **💰 Gestión de Pagos**:
   - Transacciones
   - Métodos de pago
   - Reconciliación
   - Reportes

6. **📅 Gestión de Tareas**:
   - Asignación de tareas a empleados
   - Seguimiento de progreso
   - Recordatorios

7. **🎁 Sistema de Puntos**:
   - Configuración de reglas
   - Historial de puntos
   - Redenciones pendientes

8. **📢 Gestión de Promociones**:
   - Crear/editar promociones
   - "Publica tus promociones desde Abastecimiento"
   - Visualización en portada

---

### 1️⃣4️⃣ MARKETPLACE CE (SEGUNDA MANO)

**Ruta**: `/public/marketplace_ce.php`

**Propósito**: Canal de venta de productos reacondicionados o segunda mano

**Características**:
- **Catálogo Separado**: Tabla dedicada `marketplace_ce_products` en BD
- **Filtrado por Categorías**: Clasificación de productos CE
- **Etiqueta de Condición**: "Seminuevo", "Reacondicionado", etc.
- **Variantes**: Soporte para variantes de productos (JSON)
- **Stock Independiente**: Control de inventario separado del catálogo principal
- **Precios Especiales**: Descuentos por condición de producto
- **Visibilidad**: Control de activo/inactivo por producto
- **Imagen por Producto**: URL personalizada con fallback a default

**Tabla en BD**: `marketplace_ce_products`
```sql
- id, sku (UNIQUE), name, description
- condition_label (Seminuevo, Reacondicionado, etc)
- category, unit_price, stock_quantity
- image_url, variants_json
- is_active, created_by, updated_by
- created_at, updated_at
```

---

### 1️⃣5️⃣ SISTEMA DE TICKETS

**Rutas**: 
- `/public/tickets.php` (Admin - gestión general)
- `/public/my_tickets.php` (Cliente - historial de tickets)
- `/public/ticket_client.php` (Detalle de ticket de cliente)
- `/public/ticket_quote.php` (Cotización/ticket de venta)
- `/public/ticket_supplier.php` (Ticket de proveedor)

**Propósito**: Documentación y trazabilidad completa de transacciones

#### Componentes del Sistema de Tickets

**1. Tickets de Cliente (my_tickets.php)**:
- Historial completo de compras
- Sidebar con estadísticas:
  - Total de compras
  - Monto total gastado
  - Número de devoluciones
- Tarjetas de tickets con:
  - Folio único (monoespaciado)
  - Fecha de compra
  - Monto total
  - Estado (Completado, Pendiente, Cancelado)
- Detalle completo del ticket:
  - Items comprados (nombre, cantidad, precio unitario)
  - Cálculos de totales
  - Historial de cambios
  - Opción de descargar PDF
  - Opción de compartir por WhatsApp
- Modal para envío por WhatsApp con número telefónico

**2. Tickets de Cotización (ticket_quote.php)**:
- Generación de cotizaciones formales
- Documentación para mayoristas
- Formato profesional e imprimible

**3. Tickets de Proveedor (ticket_supplier.php)**:
- Órdenes de compra a proveedores
- Documentación de recepción
- Vinculación con módulo de Abastecimiento

**4. Panel de Gestión de Tickets (tickets.php)**:
- Vista administrativa de todos los tickets
- Generación de órdenes y tickets
- Seguimiento de estado

**Características Transversales**:
- Folio único generado automáticamente
- Código de cliente (user_code)
- Timestamp de creación/actualización
- Historial de cambios con timestamps
- Generación de PDF para impresión
- Integración WhatsApp para compartir

---

### 1️⃣6️⃣ MÓDULO ANALYTICS Y ESTADÍSTICAS

**Ruta**: `/public/analytics.php`

**Propósito**: Análisis y visualización de datos de negocio

#### Componentes

**1. Dashboard de Estadísticas**:
- **Grid de KPIs**: Cards con métricas clave
  - Ventas totales (período)
  - Número de órdenes
  - Ticket promedio
  - Usuarios activos
  - Y más...

**2. Visualizaciones**:
- **Charts.js Integration**: Gráficos interactivos
  - Gráficos de línea (ventas en el tiempo)
  - Gráficos de barras (categorías top)
  - Gráficos de pastel (distribución)
  - Gráficos de área

**3. Calendario de Actividad**:
- **Grid de 7 columnas** (lunes a domingo)
- **Celdas por día** con mini información
- **Activity dots**: Puntos de actividad naranja
- **Hover states**: Interactividad
- Información de transacciones por día

**4. Filtros**:
- Período de tiempo (hoy, semana, mes, año)
- Rango de fechas personalizado
- Filtro por categoría
- Filtro por tipo de venta (retail, mayoreo, CE)

**5. Exportación**:
- Descarga de reportes en CSV/Excel
- Generación de PDF

**Acceso**: Solo administradores y usuarios con permiso

---

### 1️⃣7️⃣ MÓDULO DE TAREAS

**Ruta**: `/public/tasks.php`

**Propósito**: Gestión de tareas y asignaciones para empleados

#### Características

**1. Vista de Tareas**:
- **Mi Dashboard de Tareas**: Tareas asignadas al usuario actual
- **Panel Administrativo**: Vista de todas las tareas (si es admin)

**2. Estados de Tarea**:
- Pendiente (Azul)
- En Progreso (Naranja)
- Completada (Verde)
- Cancelada (Rojo)

**3. Prioridades**:
- Baja
- Normal
- Alta
- Urgente

**4. Creación de Tareas**:
- Título de tarea
- Descripción detallada
- Asignado a (selector de usuario)
- Fecha de vencimiento
- Prioridad
- Etiquetas/Tags

**5. Seguimiento**:
- Historial de cambios
- Comentarios en tareas
- Adjuntos de archivo
- Recordatorios
- Notificaciones

**6. Reportes**:
- Tareas completadas
- Productividad por empleado
- Cumplimiento de deadlines

---

### 1️⃣8️⃣ MÓDULO CASHIER (CAJA/POS)

**Ruta**: `/public/cashier.php`

**Propósito**: Sistema de Punto de Venta para ventas directas/mostrador

#### Características Principales

**1. Interface POS**:
- **Búsqueda de Productos**: Por nombre, SKU o código de barras
- **Carrito Visual**: Visualización en tiempo real
- **Cálculo Automático**: Subtotal, impuestos, total

**2. Captura de Productos**:
- Lectura de código de barras (integración)
- Búsqueda rápida (autocomplete)
- Selección de cantidad
- Aplicación de descuentos

**3. Métodos de Pago**:
- Efectivo
- Tarjeta de crédito/débito
- Transferencia bancaria
- Vale/Cupón
- Múltiples métodos en una venta

**4. Cálculos Financieros**:
- Subtotal
- Impuestos (configurables)
- Descuentos (% o $)
- Total a pagar
- Vuelto (efectivo)
- Comisiones

**5. Generación de Documentos**:
- Recibos fiscales
- Comprobantes
- Resumen de transacción
- Envío por correo

**6. Métricas de Caja**:
- **Cash Metrics**: Grid de KPIs
  - Transacciones del día
  - Monto total
  - Ticket promedio
  - Métodos de pago más usados

**7. Cierre de Caja**:
- Resumen de dinero recibido
- Registro de cierre
- Diferencias detectadas
- Auditoría de transacciones

**8. Autenticación**:
- Requiere login de admin
- Restricción por turno/vendedor
- Registro de quién procesa cada venta

---

### 1️⃣9️⃣ MÓDULO ABASTECIMIENTO (ADMIN SUPPLY)

**Ruta**: `/public/admin_supply.php`

**Propósito**: Gestión integral de inventario y compras a proveedores

#### Estructura Modular

**1. Gestión de Inventario**:
- CRUD de productos
- Edición masiva
- Actualización de precios
- Control de stock
- Alertas de bajo stock

**2. Gestión de Proveedores**:
- Registro de proveedores
- Contactos y datos bancarios
- Historiales de compra
- Evaluación de proveedores

**3. Órdenes de Compra**:
- Creación de POs (Purchase Orders)
- Estado: Pendiente, Confirmada, Recibida, Cancelada
- Seguimiento de entregas
- Generación de tickets

**4. Recepción de Productos**:
- Ingreso de stock
- Validación contra OC
- Ajuste de cantidades
- Registro de daños/devoluciones

**5. Catálogo CE (Segunda Mano)**:
- Gestión de productos reacondicionados
- Control de condición y variantes
- Fijación de precios CE
- Activación/desactivación

**6. Reportes de Abastecimiento**:
- Movimiento de inventario
- Rotación de stock
- Proveedores top
- Costos de compra

**7. Actualizaciones de Homepage**:
- Creación de promociones
- Banners destacados
- "Novedades del Punto de Venta"
- Gestión de orden de visualización
- Control de activación

---

### 2️⃣0️⃣ MÓDULO CHECKOUT

**Ruta**: `/public/checkout.php`

**Propósito**: Proceso de compra y pago

#### Flujo

1. **Revisión de Carrito**:
   - Items seleccionados
   - Cantidades y precios
   - Opción de modificar antes de confirmar

2. **Datos de Envío**:
   - Dirección principal/secundaria
   - Ciudad, código postal, país
   - Teléfono de contacto

3. **Datos de Facturación**:
   - Iguales a envío o diferentes
   - Información fiscal (RUT/RFC)

4. **Selección de Método de Pago**:
   - Tarjeta de crédito
   - Transferencia bancaria
   - Otra billetera digital

5. **Resumen Final**:
   - Total a pagar
   - Detalles de envío
   - Confirmación de términos

6. **Procesamiento**:
   - Validación de pago
   - Creación de orden
   - Generación de ticket
   - Envío de confirmación por email

---

### 2️⃣1️⃣ MÓDULO WHOLESALE (MAYOREO)

**Ruta**: `/public/wholesale.php`

**Propósito**: Ventas y gestión de relaciones mayoristas

#### Características

**1. Solicitud de Mayoreo**:
- Formulario de solicitud empresarial
- Validación y aprobación
- Asignación de rol `wholesale_customer`

**2. Experiencia Mayorista**:
- Catálogo especial con precios mayoristas
- Cantidades mínimas de compra
- Mejores términos de pago
- Descuentos por volumen

**3. Gestión de Mayoristas**:
- Panel administrativo
- Control de precios por mayorista
- Límites de crédito
- Historial de transacciones

**4. Cotizaciones**:
- Generación personalizada
- Envío por correo
- Validez temporal
- Comparación de opciones

---

### 2️⃣2️⃣ CUENTA DE USUARIO

**Ruta**: `/public/account.php`

**Propósito**: Gestión integral del perfil y preferencias de usuario

#### Secciones

**1. Información Personal**:
- Nombre completo
- Email
- Teléfono
- Foto de perfil

**2. Dirección de Envío**:
- Dirección principal
- Direcciones secundarias
- Selección por defecto

**3. Datos de Facturación**:
- Información fiscal
- RUT/RFC
- Empresa (si aplica)

**4. Configuración de Privacidad**:
- Notificaciones por email
- Preferencias de marketing
- Visibilidad de perfil

**5. Seguridad**:
- Cambio de contraseña
- Sesiones activas
- Acceso de terceros

**6. Métodos de Pago**:
- Tarjetas guardadas
- Cuentas bancarias
- Datos de billetera

---

## 🎨 SISTEMA DE DISEÑO

### Tipografía
- **Font Family Principal**: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif
- **Heading 1 (H1)**: 2.5rem, bold, color primario
- **Heading 2 (H2)**: 2rem, bold, color secundario
- **Heading 3 (H3)**: 1.5rem, bold, color oscuro
- **Body Text**: 1rem, regular, color gris oscuro
- **Small Text**: 0.875rem, regular, color gris

### Espaciado
- **Padding Estándar**: 1rem (16px)
- **Margin Estándar**: 1rem (16px)
- **Separación entre secciones**: 2rem
- **Gap en grids**: 2rem
- **Padding en cards**: 1.5rem

### Bordes y Sombras
- **Radio de borde**: 4px (cards, inputs), 8px (buttons)
- **Box Shadow ligera**: `0 2px 8px rgba(0,0,0,0.1)`
- **Box Shadow media**: `0 4px 16px rgba(0,0,0,0.15)`
- **Box Shadow oscura**: `0 8px 24px rgba(0,0,0,0.2)`

### Responsive Design
- **Desktop**: 1200px width máx
- **Tablet**: 768px - 1199px
- **Mobile**: < 768px

**Breakpoints**:
```css
@media (max-width: 768px) { /* Tablet/Mobile */ }
@media (max-width: 480px) { /* Mobile pequeño */ }
@media (min-width: 1200px) { /* Desktop */ }
```

### Botones
**Tipos**:

1. **Primario** (Naranja):
   - Background: #FF8C00
   - Texto: Blanco
   - Padding: 0.75rem 1.5rem
   - Hover: #e67e00, shadow aumentada

2. **Secundario** (Negro):
   - Background: #000000
   - Texto: Blanco
   - Padding: 0.75rem 1.5rem

3. **Ghost** (Outline):
   - Background: Transparente
   - Border: 2px naranja
   - Texto: Naranja
   - Hover: Background naranja, texto blanco

4. **Pequeño**:
   - Padding: 0.5rem 1rem
   - Font-size: 0.875rem

### Formularios
- **Input styling**: Border 1px gris, padding 0.75rem, radius 4px
- **Focus state**: Border naranja, shadow naranja
- **Label**: Negrita, margen inferior 0.5rem
- **Error**: Color rojo, mensaje de error visible

### Transiciones
- **Default**: `all 0.3s ease`
- **Rápida**: `0.15s ease`
- **Lenta**: `0.5s ease`

---

## 🔒 CARACTERÍSTICAS DE SEGURIDAD

### Autenticación
- Session-based (no JWT)
- Password hashing (bcrypt)
- CSRF tokens en formularios
- Remember me functionality

### Autorización
- Roles basados en permisos (RBAC):
  - `admin`: Acceso total
  - `employee`: Acceso a módulos asignados
  - `customer`: Acceso a compras y perfil
  - `wholesale_customer`: Acceso a mayoreo

### Headers de Seguridad
```
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000
Content-Security-Policy: default-src 'self'
```

---

## 📱 CARACTERÍSTICAS MÓVILES

### Diseño Responsivo
- Navbar collapsa a hamburguesa en móvil
- Grid de productos: 4 cols → 2 cols → 1 col
- Sidebar del carrito optimizado (full width móvil)
- Touch-friendly buttons (mín 44x44px)

### Performance Mobile
- Lazy loading de imágenes
- Compresión gzip
- Caché de navegador
- Optimización de CSS/JS

---

## 🚀 FUNCIONALIDADES AVANZADAS

### Sistema de Puntos
- 1 punto por cada $1 gastado (configurable)
- Promociones especiales durante cumpleaños
- Redención en descuentos
- Puntos expirados después de X meses

### Tareas de Empleados
- Asignación automática
- Seguimiento de progreso
- Recordatorios
- Historial de completadas

### Predicción de Demanda (IA)
- Análisis de patrones estacionales
- Factores externos (clima, eventos)
- Recomendaciones de inventario

### Análisis Estadístico
- Reportes por período
- Top productos
- Clientes más activos
- Tendencias de compra

---

## 📊 ESTRUCTURA DE BASE DE DATOS (Resumen)

### Tablas Principales
```
users
├── id, email, password, name, phone, role
├── points, created_at, updated_at

products
├── id, name, sku, description
├── sell_price, cost_price, stock
├── category, created_at

orders
├── id, user_id, total, status, payment_status
├── shipping_address, created_at

order_items
├── id, order_id, product_id, quantity, unit_price

wholesale_requests
├── id, company_name, business_type, status
├── contact_email, contact_phone

tasks
├── id, assigned_to, title, status, due_date

points_transactions
├── id, user_id, points, transaction_type, reference_id
```

---

## 🔄 FLUJOS PRINCIPALES

### 1. Flujo de Compra (Retail)
```
Usuario No Auth → Ver Catálogo (limitado)
                    ↓
              Hacer Login/Registro
                    ↓
              Ver Catálogo Completo
                    ↓
              Agregar productos a Carrito
                    ↓
              Procesar Pago
                    ↓
              Confirmación (Email)
                    ↓
              Acceder a Dashboard
                    ↓
              Ver Orden en "Mis Pedidos"
                    ↓
              Ganar Puntos (1 por $1)
```

### 2. Flujo de Mayoreo
```
Cliente Mayorista (No Aprobado) → Solicitud en Wholesale
                                        ↓
                                  Envío de formulario
                                        ↓
                              Admin revisa solicitud
                                        ↓
                            Aprobación y cambio de rol
                                        ↓
                        Acceso a precios mayoristas
                                        ↓
                              Realizar cotizaciones
                                        ↓
                          Generar órdenes con términos
```

### 3. Flujo Administrativo
```
Admin Login → Dashboard Principal
                    ↓
         Seleccionar módulo
    ↙        ↓          ↓        ↖
Inv.      Pedidos   Usuarios   Tareas
  ↓         ↓          ↓         ↓
CRUD    Gestión      RBAC    Asignación
```

---

## 📈 MÉTRICAS Y KPIs

### Dashboard Administrativo muestra:
- **Ventas**: $ totales, número de órdenes, ticket promedio
- **Usuarios**: Activos, nuevos, retención
- **Inventario**: Stock total, bajo stock, rotación
- **Performance**: Tiempo respuesta, conversión, CAC

---

## 🎯 ESPECIFICACIONES TÉCNICAS CLAVE

| Aspecto | Especificación |
|--------|----------------|
| **Protocolo** | HTTPS requerido en producción |
| **Session Timeout** | 1 hora (3600 segundos) |
| **CORS** | Solo origen autorizado |
| **Rate Limiting** | No implementado (recomendación: agregar) |
| **Logging** | Auditoria de cambios críticos |
| **Backup** | Diario de base de datos |
| **Compresión** | gzip habilitada |
| **Caché HTTP** | 24h para assets estáticos |

---

## 📋 COMPONENTES REUTILIZABLES

### Botones
```html
<button class="btn btn-primary">Primario</button>
<button class="btn btn-secondary">Secundario</button>
<button class="btn btn-ghost">Ghost</button>
<button class="btn btn-small">Pequeño</button>
```

### Cards
```html
<div class="card">
  <div class="card-header">Título</div>
  <div class="card-body">Contenido</div>
  <div class="card-footer">Pie</div>
</div>
```

### Alerts
```html
<div class="alert alert-success">✓ Éxito</div>
<div class="alert alert-error">✗ Error</div>
<div class="alert alert-warning">⚠ Advertencia</div>
```

### Badges de Estado
```html
<span class="badge badge-success">Completado</span>
<span class="badge badge-warning">Pendiente</span>
<span class="badge badge-error">Cancelado</span>
```


---

## 📞 INFORMACIÓN DE CONTACTO Y SOPORTE

**Email**: info@truper.com  
**Teléfono**: +1-234-567-8900  
**Horario**: Lunes - Viernes, 8:00 AM - 6:00 PM

---

**Documento preparado**: 2026-05-09  
**Versión**: 1.0  
**Clasificación**: Confidencial - Uso Interno y Partners
