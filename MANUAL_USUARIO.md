# 📖 Manual de Usuario y Operación - Truper Platform

Bienvenido al **Manual Oficial de Usuario y Operación** de **Truper Platform**. Este documento está estructurado de manera didáctica y detallada, dividido exactamente en dos secciones principales: la **Portada Principal (Vista Pública)** y el **Panel de Administración (Vista Administrativa)**.

---

# 🌐 PARTE 1: PORTADA PRINCIPAL Y CLIENTES (Vista Pública)

Esta sección describe todo el funcionamiento del sitio web al que acceden los clientes y el público en general desde la página principal (`index.php`).

---

## 1.1 Barra de Navegación Superior (Header)
Ubicada en la parte superior de la pantalla, permite una navegación fluida entre las secciones públicas:
* **Logo Truper**: Al hacer clic sobre el logotipo, te redirige inmediatamente al catálogo principal.
* **Productos**: Enlace directo al catálogo de herramientas nuevas.
* **Marketplace CE**: Acceso al catálogo de artículos reacondicionados o de remate.
* **Carrito**: Abre el cajón desplegable lateral para revisar los artículos seleccionados y cotizar.
* **Dudas por WhatsApp**: Abre una conversación directa en WhatsApp con el equipo de atención al cliente.
* **Solo para administradores**: Botón de acceso al formulario de inicio de sesión para el personal de la empresa.

---

## 1.2 Banner Portada Hero
Muestra la portada de bienvenida con un diseño oscuro elegante y la imagen de fondo oficial de herramientas Truper (`fondo_portada_truper.png`). Presenta el título **Catálogo Truper** y un botón directo hacia el Marketplace CE.

---

## 1.3 Carrusel de Novedades y Promociones
* **Visualización de Anuncios**: Desliza tarjetas con noticias, eventos y promociones creadas desde la administración.
* **Ampliación de Imágenes (Lightbox)**: Al hacer clic en cualquier imagen del carrusel, se abrirá una ventana emergente en pantalla completa para apreciar la fotografía a detalle.
* *Nota*: Si no existen promociones activas registradas por la empresa, esta sección se oculta automáticamente para mantener una portada limpia.

---

## 1.4 Buscador y Filtros de Categorías
* **Píldoras de Categorías**: Permite filtrar instantáneamente el catálogo con un solo clic entre las categorías disponibles: **Todas**, **Material eléctrico**, **Fontanería**, **Cerrajería** y **Herrería**.
* **Barra de Búsqueda Inteligente**: Escribe cualquier término (ej. *"23032"*, *"martillo"*, *"pinza"*) y el catálogo mostrará las coincidencias en tiempo real sin recargar la página.
* **Filtros Adicionales**: Opciones para ordenar los productos por precio (Menor a Mayor / Mayor a Menor), orden alfabético o disponibilidad de stock.

---

## 1.5 Tarjetas de Productos en el Catálogo
Cada recuadro de producto en el catálogo contiene la siguiente información y funciones:
* **Fotografía Principal y Galería**: Muestra la foto del producto. Si tiene más imágenes disponibles, aparecerán flechas para explorarlas.
* **Etiqueta de Categoría**: Clasificación a la que pertenece la herramienta.
* **Código del Producto (SKU)**: Código numérico identificador en negrita.
* **Nombre y Descripción Técnica**: Título completo del artículo y sus especificaciones de fábrica.
* **Variantes de Producto**: Etiquetas con detalles del modelo (ej. *Modelo Estándar*, medidas, voltajes).
* **Badge de Disponibilidad**: Indica si el producto tiene *"Stock disponible"* (en verde) o *"Stock bajo"* (en rojo).
* **Precio**: Monto de venta público expresado en pesos mexicanos (MXN).
* **Botón "Agregar"**: Añade una unidad del producto al carrito de cotización.

---

## 1.6 Cajón de Carrito y Cotizaciones (PDF y WhatsApp)
Al presionar el botón flotante **"Carrito (N)"** en la esquina inferior derecha, se despliega un panel lateral con los siguientes controles:
* **Modificación de Cantidades**: Botones `+` y `-` para ajustar el número de piezas de cada artículo, o el botón `🗑️` para eliminarlo del carrito.
* **⬇️ Enviar cotización (PDF)**: Descarga automáticamente un documento en formato PDF con la cotización formal en hoja membretada de Truper, desglosando productos, precios y el total estimado.
* **📱 Enviar cotización por WhatsApp**: Redirige a WhatsApp enviando un mensaje redactado automáticamente con el listado completo de productos, códigos y el total para la atención en mostrador.
* **🗑️ Vaciar Carrito**: Limpia todos los artículos seleccionados.

---

## 1.7 Módulo Marketplace CE (Segunda Mano)
Accesible desde `/marketplace_ce.php`. Está destinado a herramientas reacondicionadas, saldos o de segunda mano. Muestra distintivos de condición de la herramienta y se integra directamente con el mismo carrito para elaborar cotizaciones mixtas.

---
---

# 🔐 PARTE 2: PANEL DE ADMINISTRACIÓN Y PERSONAL (Vista Administrativa)

Esta sección explica el funcionamiento interno del sistema exclusivo para el personal de la empresa (`Admin` y `Personal`).

---

## 2.1 Formulario de Inicio de Sesión (`admin_login.php`)
* **Acceso Confidencial**: Presenta un campo limpio etiquetado como **"Usuario o correo"** y **"Contraseña"**.
* **Ingreso por Usuario**: Permite autenticarse ingresando el nombre de usuario asignado (`Admin` o `Personal`) o el correo electrónico institucional.

---

## 2.2 Panel de Control (Dashboard - `dashboard.php`)
Es el resumen financiero y operativo que se muestra al iniciar sesión:
* **Encabezado Inteligente**: Muestra la hora local en tiempo real y un saludo adaptado al momento del día (*Buenos días*, *Buenas tardes*, *Buenas noches*).
* **Indicadores KPI en Tiempo Real**:
  * **📦 Órdenes este mes**: Contador total de ventas registradas en el mes.
  * **💰 Ingresos**: Suma total acumulada de cobros.
  * **💸 Gastos este mes**: Suma de egresos registrados.
  * **📈 Ganancia Neta**: Cálculo dinámico en tiempo real (`Ingresos - Gastos`). Se resalta en verde si hay utilidad o en rojo si hay déficit.
  * **⏳ Pagos pendientes / ✅ Tareas**: Alertas de cobros y actividades pendientes.
* **Filtros por Fecha y Exportación**: Selecciona un rango de fechas (**Fecha Inicio** / **Fecha Fin**) y presiona **"🔍 Aplicar Filtros"** para recalcular las tablas, o presiona **"📥 Exportar Datos"** para descargar un archivo Excel (`.csv`).

---

## 2.3 Módulo de Abastecimiento e Inventario (`admin_supply.php`)
Es el corazón de la gestión de productos y cuenta con 8 sub-pestañas especializadas en la barra superior:

### 📦 Pestaña 1: "Stock" (Gestión de Productos e Imágenes)
* **Formulario "Agregar Producto"**:
  * **Código del producto**: Campo para ingresar el código numérico único de 5 o 6 dígitos (ej. `23032`).
  * **Nombre**: Título completo del artículo.
  * **Categorías**: Selección múltiple de las categorías asociadas.
  * **Descripción y Ficha Técnica**: Información detallada de uso y características de fábrica.
  * **Precios y Almacén**: Registro de Precio Costo, Precio Venta Público y Cantidad en Stock.
* **Gestor de Galería por Producto**: Haz clic sobre cualquier tarjeta de producto para abrir la ventana de carga de fotos. Permite subir múltiples imágenes para que el cliente explore diferentes ángulos en el catálogo.

### 📅 Pestaña 2: "Calendario"
* Permite programar y consultar las fechas de visitas de logística de los proveedores y entregas programadas.

### 📋 Pestaña 3: "Orden Proveedor"
* Registro y seguimiento de las órdenes de compra emitidas hacia los proveedores de mercancía.

### 🖼️ Pestaña 4: "Portada"
* Creación de las tarjetas informativas para el carrusel de la página de inicio. Puedes redactar el título, descripción, adjuntar una imagen promocional y clasificarlo como *Noticia*, *Promoción* o *Evento*.

### 👥 Pestaña 5: "Clientes"
* Directorio de clientes registrados, consulta de historial de compras y asignación de códigos de cliente.

### 🏷️ Pestaña 6: "Precios"
* Módulo para ajustes masivos de listas de precios y márgenes de ganancia.

### ♻️ Pestaña 7: "Marketplace CE"
* Alta, modificación de precios y gestión de existencias de las herramientas de segunda mano o remate.

### 🗂️ Pestaña 8: "Categorías"
* Alta y administración de las categorías del catálogo (activar, desactivar o cambiar el orden de aparición).

---

## 2.4 Módulo de Caja y Punto de Venta (`cashier.php`)
Optimizado para el cobro en mostrador y control del flujo de dinero en efectivo.

* **1. Apertura de Turno**: Al comenzar el día, ingresa el **Fondo de Caja Inicial** destinado para dar cambio a los clientes.
* **2. Cobro de Ventas**: Introduce o escanea el folio del ticket. Selecciona el método de pago:
  * **Efectivo**: Calcula automáticamente el cambio exacto a entregar.
  * **Tarjeta / Transferencia**: Registra la clave o folio de comprobación.
* **3. Cierre de Turno y Arqueo**: Al finalizar la jornada, haz clic en **"Cerrar Turno"**, ingresa el conteo de dinero físico realizado en caja y el sistema calculará automáticamente el corte y las diferencias.

---

## 2.5 Módulo de Tickets de Venta Mostrador (`tickets.php` / `guest_tickets.php`)
* Emisión rápida de notas de venta para clientes de mostrador que no requieren registro previo de cuenta.
* Genera un folio único autogenerado y permite imprimir el ticket en formato térmico (80mm) o guardarlo en PDF.

---

## 2.6 Módulo de Coordinación de Tareas (`tasks.php`)
* **Asignación**: El administrador crea una tarea asignando un título, descripción, responsable, prioridad (*Baja*, *Media*, *Alta*, *Urgente*) y fecha límite.
* **Actualización**: El empleado ingresa a su panel y actualiza el estado de la actividad a *"En Progreso"* o *"Completada"*.

---

## 2.7 Módulo de Control de Gastos (`gastos.php`)
* Permite registrar todos los egresos del negocio (renta, luz, internet, nómina, fletes).
* Cada gasto registrado se resta automáticamente en la tarjeta de **Ganancia Neta** del Dashboard.

---

## 2.8 Portal de Mayoreo y Crédito (`wholesale.php`)
* Módulo para clientes comerciales registrados. Aplica descuentos automáticos por volumen de compra y permite consultar saldos y límites de crédito disponibles.

---

## 2.9 Estadísticas Avanzadas e Inteligencia de Mercado (`analytics.php`)
* Exclusivo para el Administrador General. Presenta gráficas de ventas mensuales, análisis de rotación de productos por temporada y sugerencias automáticas para reabastecimiento de inventario.
