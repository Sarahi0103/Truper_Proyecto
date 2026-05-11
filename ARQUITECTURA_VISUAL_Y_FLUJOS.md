# 🎨 ARQUITECTURA VISUAL Y FLUJOS DE LA PLATAFORMA TRUPER

**Documento Visual para Comprensión Rápida**

---

## 📐 DIAGRAMA DE ARQUITECTURA GENERAL

```
┌─────────────────────────────────────────────────────────────────────┐
│                        NAVEGADOR DEL USUARIO                         │
│                    (Cliente Web - Responsive)                        │
└────────────────────────┬────────────────────────────────────────────┘
                         │ HTTPS/HTTP
        ┌────────────────┼────────────────┐
        │                │                │
   ┌────▼────┐    ┌──────▼──────┐  ┌─────▼──────┐
   │  Views  │    │   Assets    │  │  Public    │
   │ (PHP)   │    │ (CSS/JS)    │  │ (Uploads)  │
   └────┬────┘    └──────┬──────┘  └─────┬──────┘
        │                │                │
        └────────────────┼────────────────┘
                         │
        ┌────────────────▼────────────────┐
        │     APACHE/NGINX WEB SERVER     │
        │   (Interpreta PHP, Enruta)      │
        └────────────────┬────────────────┘
                         │
        ┌────────────────▼────────────────────────┐
        │         BACKEND PHP APPLICATION         │
        │  (Controllers + Models + Utilities)     │
        └────────────────┬─────────────────────────┘
                         │
         ┌───────────────┼───────────────┐
         │               │               │
    ┌────▼────┐   ┌──────▼──────┐  ┌────▼─────┐
    │ Database │   │   Cache     │  │  Logging  │
    │PostgreSQL│   │  (Optional) │  │   File    │
    └──────────┘   └─────────────┘  └───────────┘
```

---

## 🌳 ESTRUCTURA DE CARPETAS VISUAL

```
PROYECTO/
│
├─ 🔧 CONFIG/               (Configuración centralizada)
│  ├─ config.php            ← Variables globales
│  ├─ database.php          ← Conexión BD
│  └─ security.php          ← Headers CSRF
│
├─ 🎮 BACKEND/              (Lógica de aplicación)
│  ├─ controllers/          ← Manejo de requests
│  │  ├─ auth_controller.php
│  │  ├─ products_controller.php
│  │  └─ orders_controller.php
│  ├─ models/               ← Interacción con BD
│  │  ├─ User.php
│  │  ├─ Product.php
│  │  └─ Order.php
│  └─ utils/                ← Funciones reutilizables
│     ├─ Logger.php
│     └─ Mailer.php
│
├─ 🖼️ VIEWS/                (Páginas PHP - Frontend)
│  ├─ index.php             ← Home
│  ├─ products.php          ← Catálogo
│  ├─ dashboard.php         ← Panel usuario
│  ├─ login.php             ← Autenticación
│  ├─ wholesale.php         ← Mayoreo
│  └─ (más páginas...)
│
├─ 🎨 ASSETS/               (Recursos estáticos)
│  ├─ css/                  ← Estilos
│  │  ├─ style.css          ← Base
│  │  ├─ responsive.css     ← Mobile
│  │  └─ theme.css          ← Variables color
│  ├─ js/                   ← Scripts
│  │  ├─ main.js            ← Global
│  │  ├─ cart.js            ← Carrito
│  │  └─ forms.js           ← Validación
│  └─ img/                  ← Imágenes
│
├─ 👨‍💼 ADMIN/                (Panel administrativo)
│  ├─ dashboard.php         ← Inicio admin
│  ├─ products/
│  │  ├─ list.php           ← Tabla de productos
│  │  ├─ create.php         ← Nuevo producto
│  │  └─ edit.php           ← Editar producto
│  └─ (más módulos...)
│
├─ 📦 PUBLIC/               (Assets públicos)
│  ├─ uploads/              ← User-generated files
│  └─ js/
│     └─ jspdf.umd.min.js   ← Generador de PDFs
│
├─ 💾 DB/                   (Base de datos)
│  └─ schema.sql            ← Definición de tablas
│
├─ 🐳 DOCKER/               (Containerización)
│  ├─ Dockerfile            ← Definición del container
│  └─ docker-compose.yml    ← Orquestación
│
└─ 📋 (Archivos raíz)
   ├─ index.php             ← Punto de entrada
   ├─ .env                  ← Configuración sensible
   ├─ composer.json         ← Dependencias PHP
   └─ README.md             ← Documentación
```

---

## 🔄 FLUJO DE COMPRA (Customer Journey)

```
┌─────────────────────────────────────────────────────────────────┐
│                      INICIO USUARIO                              │
└────────────────────────────┬────────────────────────────────────┘
                             │
                    ¿Registrado?
                    /         \
                 Sí/           \No
                  │             │
        ┌─────────▼──┐   ┌──────▼────────┐
        │ IR A LOGIN │   │ IR A REGISTRO │
        └─────────┬──┘   └───────┬───────┘
                  │              │
                  │    (llenar form)
                  │              │
        ┌─────────▼──────────────▼──────────┐
        │  VALIDAR CREDENCIALES EN BD        │
        │  (Modelo: User->authenticate())   │
        └─────────┬──────────────────────────┘
                  │
            Válido? ¿
           /         \
        Sí/           \No
         │             └─► Mensaje de error
         │
    ┌────▼──────────────────┐
    │ CREAR SESSION/COOKIE  │
    │ $_SESSION['user_id']  │
    └────┬───────────────────┘
         │
    ┌────▼──────────────────────────────┐
    │    REDIRIGIR A /DASHBOARD.PHP      │
    └────┬───────────────────────────────┘
         │
    ┌────▼──────────────────────────────────────┐
    │  USUARIO EN DASHBOARD                     │
    │  - Ver puntos acumulados                  │
    │  - Ver pedidos recientes                  │
    │  - Acciones: Catálogo, Perfil, etc.     │
    └────┬───────────────────────────────────────┘
         │
    ┌────▼──────────────────────────────┐
    │   NAVEGAR A CATÁLOGO DE PRODUCTOS │
    │   /VIEWS/PRODUCTS.PHP             │
    └────┬───────────────────────────────┘
         │
    ┌────▼──────────────────────────────────────┐
    │  BUSCAR Y FILTRAR PRODUCTOS               │
    │  - Categoría: [Dropdown]                  │
    │  - Búsqueda: [Input text]                 │
    │  - Ordenar: [Dropdown]                    │
    └────┬───────────────────────────────────────┘
         │
    ┌────▼──────────────────────────────────────┐
    │  VER GRID DE PRODUCTOS                    │
    │  - Imagen                                 │
    │  - Nombre                                 │
    │  - SKU                                    │
    │  - Descripción                            │
    │  - Precio                                 │
    │  - [Botón Agregar al Carrito]            │
    └────┬───────────────────────────────────────┘
         │
    ┌────▼──────────────────────────────────────┐
    │  HACER CLIC EN "AGREGAR AL CARRITO"      │
    │  - JavaScript: addToCart(id, nombre, $) │
    │  - Guardar en localStorage                │
    │  - Mostrar FAB (Float Button) Carrito   │
    └────┬───────────────────────────────────────┘
         │
    ┌────▼──────────────────────────────────────┐
    │  (OPCIONAL) CONTINUAR COMPRANDO           │
    │  - Agregar más productos                  │
    │  - O proceder al carrito                  │
    └────┬───────────────────────────────────────┘
         │
    ┌────▼──────────────────────────────────────┐
    │  CLICK EN FAB CARRITO (Esquina inferior)  │
    └────┬───────────────────────────────────────┘
         │
    ┌────▼──────────────────────────────────────┐
    │  VER DRAWER DEL CARRITO                   │
    │  ┌─────────────────────────────────────┐  │
    │  │ Tu Carrito                      [✕] │  │
    │  ├─────────────────────────────────────┤  │
    │  │ Prod 1 × 2 = $100                  │  │
    │  │ [−] [+] [✕]                        │  │
    │  │ Prod 2 × 1 = $50                   │  │
    │  │ [−] [+] [✕]                        │  │
    │  ├─────────────────────────────────────┤  │
    │  │ TOTAL: $150                         │  │
    │  │ [⬇ Descargar Ticket]               │  │
    │  │ [📱 WhatsApp]                       │  │
    │  │ [🗑 Vaciar Carrito]                │  │
    │  └─────────────────────────────────────┘  │
    └────┬───────────────────────────────────────┘
         │
    ┌────▼───────────────────────────────────┐
    │  CREAR ORDEN EN LA BD                    │
    │  - INSERT INTO orders (user_id, total)  │
    │  - INSERT INTO order_items (...)         │
    └────┬───────────────────────────────────┘
         │
    ┌────▼───────────────────────────────────┐
    │  AGREGAR PUNTOS AL USUARIO              │
    │  - 1 punto por cada $1 gastado          │
    │  - INSERT INTO points_transactions      │
    └────┬───────────────────────────────────┘
         │
    ┌────▼───────────────────────────────────┐
    │  VACIAR CARRITO (localStorage)          │
    │  - Mostrar confirmación                 │
    └────┬───────────────────────────────────┘
         │
    ┌────▼───────────────────────────────────┐
    │  REDIRIGIR A CONFIRMACIÓN               │
    │  - Ver ID pedido                        │
    │  - Ver total                            │
    │  - Opciones: Volver a comprar, Perfil  │
    └───────────────────────────────────────┘
```

---

## 👤 FLUJO ADMINISTRATIVO

```
┌──────────────────────────────────────────────────┐
│         ADMIN ACCEDE A /ADMIN_LOGIN.PHP          │
└────────────────────┬─────────────────────────────┘
                     │
        ┌────────────▼────────────┐
        │ INGRESA CREDENCIALES    │
        │ Email/Teléfono + Pass   │
        └────────────┬────────────┘
                     │
        ┌────────────▼────────────────────────┐
        │ VALIDAR EN TABLA USERS (rol=admin)  │
        └────────────┬────────────────────────┘
                     │
            ¿Válido?
            /       \
         Sí/         \No
          │           └─► Error
          │
    ┌─────▼─────────────────────────────┐
    │   ADMIN DASHBOARD                  │
    │   /ADMIN/DASHBOARD.PHP             │
    └─────┬─────────────────────────────┘
          │
    ┌─────▼────────────────────────────────────────┐
    │ VER KPIs Y ANALÍTICA:                         │
    │ ┌────────────┬─────────────┬─────────────┐   │
    │ │Ventas: $X  │Órdenes: N   │Usuarios: M  │   │
    │ └────────────┴─────────────┴─────────────┘   │
    └─────┬────────────────────────────────────────┘
          │
    ┌─────▼────────────────────────────────────────┐
    │ MENÚ ADMINISTRATIVO:                          │
    │ ├─ 📦 Gestión de Productos                   │
    │ │  ├─ [Ver Lista]    → list.php              │
    │ │  ├─ [Nuevo]        → create.php            │
    │ │  └─ [Editar]       → edit.php              │
    │ ├─ 📋 Gestión de Pedidos                     │
    │ │  ├─ [Ver Todos]    → list.php              │
    │ │  ├─ [Cambiar Estado]                       │
    │ │  └─ [Ver Detalles]                         │
    │ ├─ 👥 Gestión de Usuarios                    │
    │ │  ├─ [Ver Lista]                            │
    │ │  ├─ [Cambiar Roles]                        │
    │ │  └─ [Bloquear/Desbloquear]                 │
    │ ├─ 💰 Gestión de Pagos                       │
    │ │  ├─ [Transacciones]                        │
    │ │  └─ [Reconciliación]                       │
    │ ├─ 📊 Reportes                               │
    │ │  ├─ [Ventas por período]                   │
    │ │  ├─ [Top productos]                        │
    │ │  └─ [Clientes activos]                     │
    │ └─ ⚙️ Configuración                           │
    │    ├─ [Reglas de puntos]                     │
    │    └─ [Promociones]                          │
    └──────────────────────────────────────────────┘
         │
    ┌────▼──────────────────────────────┐
    │ SELECCIONAR MÓDULO                 │
    │ (Ej: Gestión de Productos)        │
    └────┬──────────────────────────────┘
         │
    ┌────▼──────────────────────────────────────┐
    │ VISTA DE LISTA DE PRODUCTOS               │
    │ ┌───────────────────────────────────────┐ │
    │ │ Producto  │ SKU    │ Precio │ Stock   │ │
    │ │ Martillo  │HR-001  │ $45.99 │ 120     │ │
    │ │ Destornillador │ │ │         │         │ │
    │ │ ...       │        │        │         │ │
    │ │ [Editar]  │ [Elim] │        │         │ │
    │ └───────────────────────────────────────┘ │
    └────┬──────────────────────────────────────┘
         │
         ├─ [Nuevo Producto]
         │  └─► FORMULARIO DE CREACIÓN
         │      - Nombre
         │      - SKU
         │      - Descripción
         │      - Precio costo
         │      - Precio venta
         │      - Stock
         │      - [Guardar] → INSERT en BD
         │
         ├─ [Editar Producto]
         │  └─► FORMULARIO PRE-LLENADO
         │      - Modificar campos
         │      - [Guardar] → UPDATE en BD
         │
         └─ [Eliminar Producto]
            └─► CONFIRMACIÓN
                - [Confirmar] → DELETE en BD
                - [Cancelar]
```

---

## 💱 FLUJO MAYORISTA

```
┌──────────────────────────────────────────────────┐
│   USUARIO REGULAR QUIERE SER MAYORISTA           │
└────────────────┬─────────────────────────────────┘
                 │
        ┌────────▼────────┐
        │ NAVEGA A:        │
        │ /VIEWS/          │
        │ WHOLESALE.PHP    │
        └────────┬────────┘
                 │
        ┌────────▼──────────────────────────────┐
        │ VE FORMULARIO DE SOLICITUD:           │
        │ ┌──────────────────────────────────┐  │
        │ │ Solicitud de Compra Mayoreo      │  │
        │ ├──────────────────────────────────┤  │
        │ │ Empresa: [_____________]         │  │
        │ │ Email: [_____________]           │  │
        │ │ Teléfono: [_____________]        │  │
        │ │ Tipo Negocio: [Dropdown]         │  │
        │ │  - Ferretería                    │  │
        │ │  - Tienda Herramientas           │  │
        │ │  - Construcción                  │  │
        │ │  - Industrial                    │  │
        │ │  - Otro                          │  │
        │ │ Descripción: [_____________]     │  │
        │ │ [Enviar Solicitud]               │  │
        │ └──────────────────────────────────┘  │
        └────────┬──────────────────────────────┘
                 │
        ┌────────▼──────────────────────────────┐
        │ LLENA Y ENVÍA FORMULARIO              │
        │ (POST → wholesale_controller.php)     │
        └────────┬──────────────────────────────┘
                 │
        ┌────────▼──────────────────────────────┐
        │ BD: INSERT wholesale_requests         │
        │ Status: 'pending'                    │
        └────────┬──────────────────────────────┘
                 │
        ┌────────▼──────────────────────────────┐
        │ EMAIL: Confirmación a usuario         │
        │ - Tu solicitud fue recibida           │
        │ - Seguimiento en X horas              │
        └────────┬──────────────────────────────┘
                 │
        ┌────────▼────────────────────────────────┐
        │ ADMIN VE SOLICITUD EN PANEL            │
        │ /ADMIN/WHOLESALE/REQUESTS.PHP          │
        ├────────────────────────────────────────┤
        │ ┌──────────────────────────────────┐   │
        │ │ ID │ Empresa │ Email   │ Estado │   │
        │ │ 1  │ Mi Ltda │ xxx@... │Pending│   │
        │ │    │         │         │Aprobar│   │
        │ └──────────────────────────────────┘   │
        └────────┬────────────────────────────────┘
                 │
        ┌────────▼──────────────────────────────────┐
        │ ADMIN REVISA SOLICITUD                    │
        │ - Valida datos de empresa                 │
        │ - Verifica legitimidad                    │
        │ - [APROBAR] O [RECHAZAR]                  │
        └────────┬──────────────────────────────────┘
                 │
            ¿Aprobar?
           /        \
        Sí/          \No
         │            │
    ┌────▼────┐   ┌───▼──────────┐
    │ APROBAR  │   │ RECHAZAR     │
    └────┬────┘   └───┬──────────┘
         │            │
    ┌────▼─────────────────────────────────┐
    │ BD: UPDATE wholesale_requests        │
    │ Status = 'approved' / 'rejected'     │
    │ user_id = user.id                    │
    └────┬──────────────────────────────────┘
         │
    ┌────▼──────────────────────────────────┐
    │ BD: UPDATE users (si aprobado)        │
    │ role = 'wholesale_customer'           │
    └────┬──────────────────────────────────┘
         │
    ┌────▼──────────────────────────────────┐
    │ EMAIL: Respuesta a usuario            │
    │ - Solicitud aprobada/rechazada        │
    └────┬──────────────────────────────────┘
         │
         ├─ Si APROBADO:
         │  └─► Usuario ahora ve:
         │      - Precios mayoristas en catálogo
         │      - Cantidades mínimas
         │      - Términos de pago especiales
         │      - Acceso a cotizaciones
         │
         └─ Si RECHAZADO:
            └─► Puede intentar de nuevo más tarde
```

---

## 🎨 FLUJO DE TEMA (Modo Oscuro/Claro)

```
┌──────────────────────────────────────┐
│  Usuario hace clic en "Modo Obscuro"  │
└───────────────┬──────────────────────┘
                │
        ┌───────▼────────────────────────────────┐
        │ JavaScript: toggleTheme()              │
        │ - Lee localStorage[theme]              │
        │ - Si = 'light' → cambiar a 'dark'      │
        │ - Si = 'dark' → cambiar a 'light'      │
        └───────┬────────────────────────────────┘
                │
        ┌───────▼────────────────────────────────┐
        │ Aplicar clases CSS:                    │
        │ - document.body.classList.toggle(...)  │
        │ - Cambiar --color-primary              │
        │ - Cambiar --color-secondary            │
        │ - Cambiar --color-light                │
        │ - Cambiar background de cards          │
        │ - Cambiar color de texto               │
        └───────┬────────────────────────────────┘
                │
        ┌───────▼────────────────────────────────┐
        │ Guardar en localStorage:               │
        │ localStorage.setItem('theme', modo)    │
        └───────┬────────────────────────────────┘
                │
        ┌───────▼────────────────────────────────┐
        │ Próxima vez que accede:                │
        │ - Lee localStorage[theme]              │
        │ - Aplica tema automáticamente          │
        └───────────────────────────────────────┘
```

---

## 📊 DIAGRAMA DE ENTIDADES (BD)

```
┌─────────────────────────────────────────────────────────────────────┐
│                          USERS                                       │
├──────────────────────────────────────────────────────────────────────┤
│ PK id                                                                │
│    email (UNIQUE)              ◄─── LOGIN/REGISTRO                  │
│    password_hash                                                     │
│    name                                                              │
│    phone                                                             │
│    role (admin|employee|customer|wholesale_customer)                │
│    points                      ─────► SYSTEM DE PUNTOS              │
│    created_at, updated_at                                           │
└───┬──────────────────────────────────────────────────────────────────┘
    │
    │ 1 (one)
    │
    │ ┌────────────────────────┐         ┌────────────────────────┐
    │ │                        │ (many)  │                        │
    │ └────────────────────────┘─────────┤    ORDERS              │
    │                                    │ ┌──────────────────────┤
    │                                    │ │ PK id                │
    │                                    │ │ FK user_id          │
    │                                    │ │    total            │
    │                                    │ │    status           │
    │                                    │ │    payment_status   │
    │                                    │ │    shipping_address │
    │                                    │ │    created_at       │
    │                                    │ └──────────────────────┘
    │                                    │      │
    │                                    │      │ 1 (one)
    │                                    │      │
    │                                    │      │ (many)
    │                                    │      │
    │                                    │      ▼
    │                                    │ ┌──────────────────────┐
    │                                    │ │ ORDER_ITEMS          │
    │                                    │ ├──────────────────────┤
    │                                    │ │ PK id                │
    │                                    │ │ FK order_id         │
    │                                    │ │ FK product_id       │
    │                                    │ │    quantity         │
    │                                    │ │    unit_price       │
    │                                    │ └──────────────────────┘
    │                                    │
    │        ┌────────────────────────────┘
    │        │
    │        │ (many)
    │        │
    ▼        ▼
┌─────────────────────────────────────────────────────────────────────┐
│                       PRODUCTS                                       │
├──────────────────────────────────────────────────────────────────────┤
│ PK id                                                                │
│    name                                                              │
│    sku (UNIQUE)                                                      │
│    description                                                       │
│    category                                                          │
│    cost_price                                                        │
│    sell_price                                                        │
│    wholesale_price  (para mayoristas)                               │
│    stock                                                             │
│    image_url                                                         │
│    created_at, updated_at                                           │
└─────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│                 POINTS_TRANSACTIONS                                  │
├──────────────────────────────────────────────────────────────────────┤
│ PK id                                                                │
│ FK user_id  ────────► (ref to USERS)                               │
│    points (+ o -)                                                    │
│    transaction_type (purchase|redemption|bonus|gift)                │
│    reference_id (ref a orden)                                       │
│    description                                                       │
│    created_at                                                        │
└──────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│                 WHOLESALE_REQUESTS                                   │
├──────────────────────────────────────────────────────────────────────┤
│ PK id                                                                │
│    company_name                                                      │
│    contact_email                                                     │
│    contact_phone                                                     │
│    business_type                                                     │
│    description                                                       │
│    status (pending|approved|rejected)                               │
│ FK user_id  ────────► (ref to USERS) - nullable                    │
│    created_at, updated_at                                           │
└──────────────────────────────────────────────────────────────────────┘
```

---

## 🎯 MAPA DE COMPONENTES UI

```
PÁGINA: index.php (HOME)
┌─────────────────────────────────────────────────────────┐
│ NAVBAR (sticky)                                         │
│ [Logo Truper] [Inicio] [Catálogo] [Admin] [Mode]      │
├─────────────────────────────────────────────────────────┤
│ HERO SECTION                                            │
│ ┌───────────────────────────────────────────────────┐  │
│ │  Truper - Tu Distribuidor...                      │  │
│ │  [Ver Catálogo] (btn-primary)                     │  │
│ └───────────────────────────────────────────────────┘  │
├─────────────────────────────────────────────────────────┤
│ FEATURES GRID (4 cols)                                  │
│ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐  │
│ │📦 Digital│ │🎁 Puntos │ │🚀 Rápido │ │💰 Mayoreo│  │
│ └──────────┘ └──────────┘ └──────────┘ └──────────┘  │
├─────────────────────────────────────────────────────────┤
│ SECCIÓN "NOVEDADES" (Carrusel)                          │
│ [<<] Imagen + Título [>>]                               │
│      Descripción                                        │
├─────────────────────────────────────────────────────────┤
│ SECCIÓN "PUBLICA PROMOCIONES"                           │
│ Usa módulo Abastecimiento para crear tarjetas...       │
├─────────────────────────────────────────────────────────┤
│ FOOTER                                                  │
│ © 2024 Truper | info@truper.com | +1-234-567-8900     │
└─────────────────────────────────────────────────────────┘

PÁGINA: views/products.php (CATÁLOGO)
┌─────────────────────────────────────────────────────────┐
│ NAVBAR                                                  │
├──────────────┬──────────────────────────────────────────┤
│ FILTROS      │ GRID DE PRODUCTOS (4 cols)              │
│              │                                          │
│ Búsqueda     │ ┌─────────┐ ┌─────────┐ ┌─────────┐   │
│ [_____]      │ │Martillo │ │Destorn..│ │Taladro  │   │
│              │ │$45.99   │ │$32.50   │ │$125.00  │   │
│ Categoría    │ │[Agregar]│ │[Agregar]│ │[Agregar]│   │
│ [Dropdown]   │ └─────────┘ └─────────┘ └─────────┘   │
│              │                                          │
│ Precio       │ ┌─────────┐ ┌─────────┐ ┌─────────┐   │
│ [slider]     │ │Tornillos│ │Clavos   │ │Tuercas  │   │
│              │ └─────────┘ └─────────┘ └─────────┘   │
│              │                                          │
│              │ [Siguiente >]                            │
├──────────────┴──────────────────────────────────────────┤
│ FAB CARRITO (abajo derecha): [Carrito (0)]             │
├──────────────────────────────────────────────────────────┤
│ FOOTER                                                  │
└──────────────────────────────────────────────────────────┘

PÁGINA: views/login.php (AUTENTICACIÓN)
┌─────────────────────────────────────────────────────────┐
│ CENTERED AUTH FORM:                                     │
│ ┌───────────────────────────────────────────────────┐  │
│ │             TRUPER LOGO                           │  │
│ │         INICIAR SESIÓN                            │  │
│ │                                                   │  │
│ │ Email:       [_____________________]             │  │
│ │ Contraseña:  [_____________________]             │  │
│ │ ☐ Recuérdame                                     │  │
│ │                                                   │  │
│ │ [Ingresar]                                        │  │
│ │                                                   │  │
│ │ ¿No tienes cuenta? Regístrate                    │  │
│ │ ¿Olvidaste tu contraseña?                        │  │
│ └───────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘

PÁGINA: views/dashboard.php (USUARIO)
┌───────────────────────────────────────────────────────┐
│ NAVBAR                                                │
├──────────────────┬──────────────────────────────────┤
│ SIDEBAR          │ MAIN CONTENT                     │
│                  │                                  │
│ Avatar [User]    │ ¡Bienvenido, [Name]!            │
│ [user@email]     │                                  │
│                  │ ┌────────┬────────┬────────┐    │
│ ▶ Dashboard      │ │Puntos: │Pedidos:│Miembro │    │
│   Mis Pedidos    │ │ 450    │   5    │ desde  │    │
│   Mi Perfil      │ │        │        │ 01/2024│    │
│   Mis Puntos     │ └────────┴────────┴────────┘    │
│   Mayoreo        │                                  │
│                  │ Pedidos Recientes               │
│                  │ ┌──────────────────────────┐    │
│                  │ │#001│01/05│$150│Entregado│   │
│                  │ │#002│30/04│$87 │Pendiente │   │
│                  │ └──────────────────────────┘    │
├──────────────────┴──────────────────────────────────┤
│ FOOTER                                              │
└───────────────────────────────────────────────────────┘

DRAWER CARRITO:
┌─────────────────────────────────┐
│ Tu Carrito              [✕]     │
├─────────────────────────────────┤
│ Martillo × 2 = $91.98           │
│ [−] [+] [✕]                     │
│                                 │
│ Taladro × 1 = $125.00           │
│ [−] [+] [✕]                     │
├─────────────────────────────────┤
│ TOTAL: $216.98                  │
├─────────────────────────────────┤
│ [⬇ Descargar Ticket]            │
│ [📱 WhatsApp]                   │
│ [🗑 Vaciar Carrito]             │
└─────────────────────────────────┘
```

---

## 🔐 MATRIZ DE PERMISOS POR ROL

```
                    │ Customer │ Wholesale │ Employee │ Admin
────────────────────┼──────────┼───────────┼──────────┼──────
Ver Catálogo        │    ✓     │     ✓     │    ✓     │  ✓
Hacer Compras (Retail)│  ✓     │     ✓     │    -     │  ✓
Acceder Mayoreo     │    -     │     ✓     │    -     │  ✓
Ver Dashboard       │    ✓     │     ✓     │    ✓     │  ✓
Ver Mis Pedidos     │    ✓     │     ✓     │    -     │  ✓
Solicitar Tareas    │    -     │     -     │    ✓     │  ✓
Ver Reportes        │    -     │     -     │    -     │  ✓
Gestionar Usuarios  │    -     │     -     │    -     │  ✓
CRUD Productos      │    -     │     -     │    -     │  ✓
Cambiar Estados     │    -     │     -     │    -     │  ✓
Aprobar Mayoreos    │    -     │     -     │    -     │  ✓
Ver Analítica       │    -     │     -     │    -     │  ✓
```

---

**Documento Visual - Arquitectura Truper**  
**Versión**: 1.0 | **Fecha**: 2026-05-09
