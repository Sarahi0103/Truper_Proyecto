# Documentación Completa del Sistema Ferretería FOX / Truper Platform

## Índice General

### Parte 1: Interfaces del Cliente
1. [Tienda Online (tienda.php)](#1-tienda-online-tiendaphp)
2. [Carrito de Compras](#2-carrito-de-compras)
3. [Checkout (checkout.php)](#3-checkout-checkoutphp)
4. [Mi Cuenta (account.php)](#4-mi-cuenta-accountphp)
5. [Dashboard del Cliente (customer_dashboard.php)](#5-dashboard-del-cliente-customer-dashboardphp)

### Parte 2: Interfaces de Administración - Tienda
6. [Pedidos/Ventas (orders.php)](#6-pedidosventas-ordersphp)
7. [Seguimiento/Logística (order_tracking.php)](#7-seguimientologística-order-trackingphp)
8. [Devoluciones RMA (rma_manager.php)](#8-devoluciones-rma-rma-managerphp)
9. [Facturación & Pagos SAT (admin_online_billing.php)](#9-facturación--pagos-sat-admin-online-billingphp)
10. [Configuración de Pagos (admin_payment_config.php)](#10-configuración-de-pagos-admin-payment-configphp)

### Parte 3: Interfaces de Administración - Local
11. [Caja/Punto de Venta (cashier.php)](#11-cajapunto-de-venta-cashierphp)
12. [Aprobación B2B (b2b_approval.php)](#12-aprobación-b2b-b2b-approvalphp)
13. [Tickets y Cotizaciones (tickets.php)](#13-tickets-y-cotizaciones-ticketsphp)
14. [Validación de Tickets (ticket_validation.php)](#14-validación-de-tickets-ticket-validationphp)
15. [Tareas de Empleados (tasks.php)](#15-tareas-de-empleados-tasksphp)

### Parte 4: Interfaces de Administración - Solo Admin
16. [Abastecimiento/Precios (admin_supply.php)](#16-abastecimientoprecios-admin-supplyphp)
17. [Reportes Contables (accounting_reports.php)](#17-reportes-contables-accounting-reportsphp)
18. [Egresos/Gastos (gastos.php)](#18-egresosgastos-gastosphp)
19. [Estadísticas (analytics.php)](#19-estadísticas-analyticsphp)
20. [Analíticas Avanzadas (admin_analytics.php)](#20-analíticas-avanzadas-admin-analyticsphp)

### Parte 5: APIs del Sistema
21. [API de Autenticación](#21-api-de-autenticación)
22. [API de Carrito](#22-api-de-carrito)
23. [API de Checkout](#23-api-de-checkout)
24. [API de Pedidos](#24-api-de-pedidos)
25. [API de Productos](#25-api-de-productos)
26. [API de Inventario](#26-api-de-inventario)
27. [API de Clientes](#27-api-de-clientes)
28. [API de Facturación](#28-api-de-facturación)
29. [API de Pagos](#29-api-de-pagos)
30. [API de Reportes](#30-api-de-reportes)

---

## PARTE 1: INTERFACES DEL CLIENTE

---

## 1. Tienda Online (tienda.php)

**URL**: `/tienda.php`

**Propósito**: Catálogo de productos online donde los clientes pueden navegar, buscar y agregar productos al carrito.

**Requisitos de Acceso**:
- Público (no requiere autenticación)
- Opcional: Login para ver precios especiales

### Estructura de la Página

#### Header de Navegación
```
┌─────────────────────────────────────────────────────────┐
│ [Logo] [Categorías ▼] [Buscar...] [🛒 0] [Login]       │
└─────────────────────────────────────────────────────────┘
```

**Componentes**:
- **Logo**: Enlace a página principal
- **Dropdown de Categorías**: Lista de categorías de productos
- **Buscador**: Input de búsqueda con autocompletado
- **Carrito**: Icono con contador de items
- **Login/Usuario**: Botón de login o nombre de usuario si está logueado

#### Hero Section
```
┌─────────────────────────────────────────────────────────┐
│ [Banner Promocional]                                     │
│ "Ofertas Especiales - Descuentos de hasta 30%"          │
└─────────────────────────────────────────────────────────┘
```

#### Filtros de Productos
```
┌─────────────────────────────────────────────────────────┐
│ Categorías: [Todas] [Herramientas] [Construcción] ...   │
│ Precio: [Min] - [Max]                                    │
│ Ordenar: [Relevancia ▼]                                  │
└─────────────────────────────────────────────────────────┘
```

**Filtros Disponibles**:
- **Categorías**: Todas, Herramientas Manuales, Herramientas Eléctricas, Construcción, Plomería, Electricidad, Pinturas, Jardinería, Seguridad
- **Rango de Precio**: Slider o inputs min/max
- **Ordenamiento**: Relevancia, Precio (asc/desc), Nombre, Más vendidos
- **Búsqueda**: Texto libre con autocompletado

#### Grid de Productos
```
┌─────────────────────────────────────────────────────────┐
│ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐         │
│ │ [Imagen]    │ │ [Imagen]    │ │ [Imagen]    │         │
│ │ Taladro     │ │ Martillo    │ │ Sierra      │         │
│ │ $1,299.00   │ │ $249.00     │ │ $899.00     │         │
│ │ [Agregar]   │ │ [Agregar]   │ │ [Agregar]   │         │
│ └─────────────┘ └─────────────┘ └─────────────┘         │
└─────────────────────────────────────────────────────────┘
```

**Card de Producto**:
- **Imagen**: URL de imagen del producto
- **Nombre**: Nombre del producto
- **SKU**: Código de producto
- **Precio**: Precio online (o precio especial si cliente B2B)
- **Stock**: Disponibilidad (En stock, Agotado, Pocas unidades)
- **Botón Agregar**: Agrega al carrito
- **Botón Comparar**: Agrega a comparador
- **Botón Wishlist**: Agrega a lista de deseos

#### Footer
```
┌─────────────────────────────────────────────────────────┐
│ [Contacto] [Envíos] [Devoluciones] [Términos]           │
│ [Facebook] [Instagram] [WhatsApp]                        │
└─────────────────────────────────────────────────────────┘
```

### Funcionalidades JavaScript

**Carga de Productos**:
```javascript
// Cargar productos desde API
fetch('/api/products.php')
    .then(response => response.json())
    .then(data => {
        products = data.products;
        renderProducts();
    });
```

**Búsqueda con Autocompletado**:
```javascript
// Autocompletado de búsqueda
searchInput.addEventListener('input', debounce((e) => {
    const query = e.target.value;
    if (query.length >= 2) {
        fetch(`/api/search.php?q=${query}`)
            .then(response => response.json())
            .then(data => showSuggestions(data.suggestions));
    }
}, 300));
```

**Filtrado**:
```javascript
// Filtrar productos por categoría
function filterByCategory(category) {
    filteredProducts = products.filter(p => p.category === category);
    renderProducts(filteredProducts);
}

// Filtrar por precio
function filterByPrice(min, max) {
    filteredProducts = products.filter(p => 
        p.price >= min && p.price <= max
    );
    renderProducts(filteredProducts);
}
```

**Agregar al Carrito**:
```javascript
// Agregar producto al carrito
function addToCart(productId, quantity = 1) {
    const product = products.find(p => p.id === productId);
    cart.push({
        productId: product.id,
        name: product.name,
        price: product.price,
        quantity: quantity,
        sku: product.sku
    });
    updateCartBadge();
    saveCartToLocalStorage();
}
```

**Comparación de Productos**:
```javascript
// Agregar a comparador
function addToCompare(productId) {
    if (compareList.length < 4) {
        compareList.push(productId);
        updateCompareBadge();
    } else {
        alert('Máximo 4 productos para comparar');
    }
}
```

**Wishlist**:
```javascript
// Agregar a lista de deseos
function addToWishlist(productId) {
    if (isLogged) {
        fetch('/api/wishlist.php', {
            method: 'POST',
            body: JSON.stringify({ productId })
        });
    } else {
        // Guardar en localStorage
        wishlist.push(productId);
        saveWishlistToLocalStorage();
    }
}
```

### Flujo de Usuario

```
┌─────────────────────────────────────────────────────────┐
│ 1. Cliente accede a tienda.php                           │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 2. Sistema carga productos desde base de datos           │
│    - Filtra productos activos                           │
│    - Filtra productos visibles en online                │
│    - Carga hasta 5000 productos                         │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 3. Cliente navega por categorías                        │
│    - Sistema filtra productos por categoría             │
│    - Renderiza grid de productos                        │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 4. Cliente usa buscador                                 │
│    - Sistema muestra sugerencias                        │
│    - Cliente selecciona producto                       │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 5. Cliente agrega producto al carrito                    │
│    - Sistema valida stock                               │
│    - Agrega a carrito en memoria/localStorage           │
│    - Actualiza contador de carrito                     │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 6. Cliente va al carrito o checkout                     │
└─────────────────────────────────────────────────────────┘
```

### Configuración de Productos

**Campos de Producto**:
- `id`: ID único del producto
- `name`: Nombre del producto
- `sku`: Código de producto
- `unit_price`: Precio unitario
- `price_online`: Precio online (opcional)
- `net_price`: Precio neto (después de descuentos)
- `discount_percentage`: Porcentaje de descuento
- `category`: Categoría del producto
- `description`: Descripción del producto
- `stock_quantity`: Cantidad en stock
- `image_url`: URL de imagen
- `variants_json`: Variantes (color, tamaño, etc.)
- `product_group`: Grupo de producto
- `color`: Color del producto
- `is_active`: Producto activo
- `show_in_online`: Mostrar en tienda online

---

## 2. Carrito de Compras

**Propósito**: Gestión temporal de productos seleccionados por el cliente antes de completar la compra.

**Ubicación**: Integrado en tienda.php y checkout.php

### Estructura del Carrito

#### Modal del Carrito
```
┌─────────────────────────────────────────────────────────┐
│ 🛒 Tu Carrito (3 items)                    [Cerrar ✕]     │
├─────────────────────────────────────────────────────────┤
│ ┌───────────────────────────────────────────────────┐  │
│ │ [Img] Taladro Percutor 18V                          │  │
│ │       SKU: TAL-1800                                 │  │
│ │       $1,299.00 x [1] = $1,299.00                  │  │
│ │       [Eliminar]                                   │  │
│ └───────────────────────────────────────────────────┘  │
│ ┌───────────────────────────────────────────────────┐  │
│ │ [Img] Martillo Profesional                         │  │
│ │       SKU: MAR-0500                                 │  │
│ │       $249.00 x [2] = $498.00                      │  │
│ │       [Eliminar]                                   │  │
│ └───────────────────────────────────────────────────┘  │
├─────────────────────────────────────────────────────────┤
│ Subtotal: $1,797.00                                      │
│ Envío: $0.00 (se calcula en checkout)                   │
│ Total: $1,797.00                                         │
├─────────────────────────────────────────────────────────┤
│ [Seguir Comprando]  [Ir a Checkout →]                  │
└─────────────────────────────────────────────────────────┘
```

### Funcionalidades

**Agregar al Carrito**:
```javascript
function addToCart(productId, quantity = 1) {
    const existingItem = cart.find(item => item.productId === productId);
    
    if (existingItem) {
        existingItem.quantity += quantity;
    } else {
        cart.push({
            productId: productId,
            quantity: quantity,
            // ... otros datos del producto
        });
    }
    
    saveCart();
    updateCartBadge();
    showNotification('Producto agregado al carrito');
}
```

**Actualizar Cantidad**:
```javascript
function updateQuantity(productId, newQuantity) {
    if (newQuantity <= 0) {
        removeFromCart(productId);
    } else {
        const item = cart.find(item => item.productId === productId);
        if (item) {
            item.quantity = newQuantity;
            saveCart();
            updateCartTotal();
        }
    }
}
```

**Eliminar del Carrito**:
```javascript
function removeFromCart(productId) {
    cart = cart.filter(item => item.productId !== productId);
    saveCart();
    updateCartBadge();
    renderCart();
}
```

**Calcular Totales**:
```javascript
function calculateTotals() {
    const subtotal = cart.reduce((sum, item) => 
        sum + (item.price * item.quantity), 0
    );
    
    const discount = applyDiscounts(subtotal);
    const shipping = calculateShipping(subtotal);
    const total = subtotal - discount + shipping;
    
    return { subtotal, discount, shipping, total };
}
```

**Aplicar Cupón**:
```javascript
function applyCoupon(couponCode) {
    fetch('/api/coupons.php?action=validate', {
        method: 'POST',
        body: JSON.stringify({ couponCode })
    })
    .then(response => response.json())
    .then(data => {
        if (data.valid) {
            cart.coupon = data.coupon;
            saveCart();
            updateCartTotal();
            showNotification('Cupón aplicado');
        } else {
            showNotification('Cupón inválido', 'error');
        }
    });
}
```

### Persistencia del Carrito

**LocalStorage** (para usuarios no logueados):
```javascript
function saveCart() {
    localStorage.setItem('cart', JSON.stringify(cart));
}

function loadCart() {
    const saved = localStorage.getItem('cart');
    if (saved) {
        cart = JSON.parse(saved);
    }
}
```

**Base de Datos** (para usuarios logueados):
```javascript
function saveCartToDatabase() {
    if (isLogged) {
        fetch('/api/cart.php', {
            method: 'POST',
            body: JSON.stringify({ cart })
        });
    }
}
```

---

## 3. Checkout (checkout.php)

**URL**: `/checkout.php`

**Propósito**: Proceso final de compra donde el cliente completa datos de envío, pago y facturación.

**Requisitos de Acceso**:
- Opcional: Login para datos prellenados
- Opcional: Login para facturación

### Estructura de la Página

#### Header
```
┌─────────────────────────────────────────────────────────┐
│ [← Regresar]  Checkout  [🛒 3 items]                    │
└─────────────────────────────────────────────────────────┘
```

#### Secciones del Formulario

**1. Información de Contacto**
```
┌─────────────────────────────────────────────────────────┐
│ 📧 Información de Contacto                              │
│                                                          │
│ Nombre: [_________________________]                     │
│ Email: [_________________________]                     │
│ Teléfono: [_________________________]                   │
└─────────────────────────────────────────────────────────┘
```

**Campos**:
- `first_name`: Nombre
- `last_name`: Apellido
- `email`: Correo electrónico
- `phone`: Teléfono

**Validaciones**:
- Email formato válido
- Teléfono 10 dígitos (México)
- Campos requeridos

**2. Dirección de Entrega**
```
┌─────────────────────────────────────────────────────────┐
│ 📍 Dirección de Entrega                                 │
│                                                          │
│ Calle y número: [_________________________]             │
│ Colonia: [_________________________]                    │
│ Ciudad: [_________________________]                     │
│ Estado: [Seleccionar ▼]                                 │
│ Código Postal: [_____]                                  │
│ Referencias: [_________________________]                │
│                                                          │
│ [📍 Usar mi ubicación actual]                           │
│ [Seleccionar de direcciones guardadas ▼]               │
└─────────────────────────────────────────────────────────┘
```

**Funcionalidades**:
- Geolocalización del navegador
- Autocompletado de dirección
- Selección de direcciones guardadas (si usuario logueado)
- Validación de código postal

**3. Método de Envío**
```
┌─────────────────────────────────────────────────────────┐
│ 🚚 Método de Envío                                      │
│                                                          │
│ ○ Estándar (3-5 días hábiles) - $150.00                 │
│ ○ Express (1-2 días hábiles) - $250.00                  │
│ ○ Recoger en tienda (Gratis)                            │
└─────────────────────────────────────────────────────────┘
```

**Cálculo de Envío**:
```javascript
function calculateShipping(method, address) {
    const baseRate = method === 'express' ? 250 : 150;
    const freeShippingThreshold = 2000;
    const cartTotal = calculateCartTotal();
    
    if (cartTotal >= freeShippingThreshold || method === 'pickup') {
        return 0;
    }
    
    // Ajuste por zona (CDMX, interior, frontera)
    const zone = getShippingZone(address.zipCode);
    const zoneMultiplier = zone === 'cdmx' ? 1 : zone === 'interior' ? 1.2 : 1.5;
    
    return baseRate * zoneMultiplier;
}
```

**4. Código Promocional**
```
┌─────────────────────────────────────────────────────────┐
│ 🎟️ Código Promocional                                   │
│                                                          │
│ [Ingresar código______] [Aplicar]                        │
│                                                          │
│ Cupón "BIENVENIDO10" aplicado: -$179.70                  │
└─────────────────────────────────────────────────────────┘
```

**5. Notas del Pedido**
```
┌─────────────────────────────────────────────────────────┐
│ 📝 Notas del Pedido                                     │
│                                                          │
│ [Instrucciones especiales para tu pedido______________] │
└─────────────────────────────────────────────────────────┘
```

**6. Datos Fiscales CFDI 4.0** (Opcional)
```
┌─────────────────────────────────────────────────────────┐
│ 📄 Facturación (Opcional)                               │
│                                                          │
│ [¿Requieres factura?] ☑                                 │
│                                                          │
│ RFC: [_________________________]                       │
│ Razón Social: [_________________________]              │
│ Régimen Fiscal: [Seleccionar ▼]                         │
│ Uso de CFDI: [G03 - Gastos en general ▼]               │
│ Código Postal Fiscal: [_____]                            │
│ Email de Facturación: [_________________________]        │
└─────────────────────────────────────────────────────────┘
```

**Catálogos SAT**:
- Regímenes fiscales: 601, 603, 606, 612, 626
- Usos de CFDI: G01, G02, G03, P01

**Validación de RFC**:
```javascript
function validateRFC(rfc) {
    // Personas morales: AAAA000000A00
    const moralRegex = /^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/;
    // Personas físicas: AAAA000000AAA
    const fisicaRegex = /^[A-Z&Ñ]{4}[0-9]{6}[A-Z0-9]{3}$/;
    
    return moralRegex.test(rfc) || fisicaRegex.test(rfc);
}
```

**7. Método de Pago**
```
┌─────────────────────────────────────────────────────────┐
│ 💳 Método de Pago                                      │
│                                                          │
│ ○ Tarjeta de Crédito/Débito                             │
│ ○ SPEI (Transferencia Electrónica)                      │
│ ○ Transferencia Bancaria                                │
│ ○ Mercado Pago                                          │
│ ○ Efectivo (Pago contra entrega)                        │
└─────────────────────────────────────────────────────────┘
```

**Selección Dinámica de Banco (SPEI)**:
```
┌─────────────────────────────────────────────────────────┐
│ Selecciona tu banco para SPEI:                          │
│ [BBVA Bancomer ▼]                                       │
│                                                          │
│ Información del banco:                                   │
│ Banco: BBVA Bancomer                                     │
│ CLABE: 012180001234567890                                │
│ Titular: Ferretería FOX S.A. de C.V.                    │
│                                                          │
│ Instrucciones:                                           │
│ 1. Realiza la transferencia SPEI a la CLABE indicada    │
│ 2. Usa tu número de pedido como referencia              │
│ 3. El pago se confirmará en 1-2 horas hábiles          │
└─────────────────────────────────────────────────────────┘
```

**Formulario de Tarjeta**:
```
┌─────────────────────────────────────────────────────────┐
│ Datos de la Tarjeta                                     │
│                                                          │
│ Número de tarjeta: [____________________]                │
│ Fecha de expiración: [MM/AA]                            │
| CVV: [___]                                              │
│ Nombre del titular: [____________________]               │
│                                                          │
│ [💳 Pagar $1,797.00]                                     │
└─────────────────────────────────────────────────────────┘
```

**8. Resumen del Pedido**
```
┌─────────────────────────────────────────────────────────┐
│ 📋 Resumen del Pedido                                   │
│                                                          │
│ Taladro Percutor 18V x 1        $1,299.00               │
│ Martillo Profesional x 2         $498.00                 │
│ ───────────────────────────────────────                 │
│ Subtotal                       $1,797.00                │
│ Envío (Estándar)                $150.00                  │
│ Cupón BIENVENIDO10             -$179.70                 │
│ ───────────────────────────────────────                 │
│ Total                          $1,767.30                │
│                                                          │
│ [✓ Confirmar y Pagar]                                   │
└─────────────────────────────────────────────────────────┘
```

### Flujo de Checkout

```
┌─────────────────────────────────────────────────────────┐
│ 1. Cliente accede a checkout.php                         │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 2. Sistema valida carrito                                │
│    - Verifica que haya productos                        │
│    - Valida stock disponible                            │
│    - Calcula subtotal                                   │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 3. Cliente completa datos de contacto                   │
│    - Sistema valida formato de email                    │
│    - Sistema valida teléfono                             │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 4. Cliente ingresa dirección de envío                  │
│    - Sistema valida código postal                       │
│    - Sistema calcula costo de envío                     │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 5. Cliente selecciona método de envío                  │
│    - Sistema actualiza total con envío                  │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 6. Cliente aplica cupón (opcional)                       │
│    - Sistema valida cupón                                │
│    - Sistema aplica descuento                           │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 7. Cliente ingresa datos fiscales (opcional)            │
│    - Sistema valida RFC                                  │
│    - Sistema valida catálogos SAT                        │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 8. Cliente selecciona método de pago                    │
│    - Si tarjeta: Muestra formulario de tarjeta           │
│    - Si SPEI: Muestra bancos disponibles                 │
│    - Si transferencia: Muestra bancos disponibles       │
│    - Si efectivo: Marca como pago contra entrega        │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 9. Cliente confirma pedido                               │
│    - Sistema crea orden en base de datos                │
│    - Sistema procesa pago según método                   │
│    - Sistema emite factura si hay datos fiscales         │
└─────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─────────────────────────────────────────────────────────┐
│ 10. Cliente recibe confirmación                         │
│     - Email con detalles del pedido                     │
│     - Número de orden                                    │
│     - PDF de factura (si aplica)                        │
└─────────────────────────────────────────────────────────┘
```

### Procesamiento de Pagos

**Tarjeta (Stripe/Mercado Pago)**:
```javascript
async function processCardPayment(cardData) {
    const response = await fetch('/api/checkout.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'process_payment',
            method: 'card',
            cardData: cardData,
            orderId: orderId
        })
    });
    
    const result = await response.json();
    
    if (result.success) {
        window.location.href = `/order_confirmation.php?order_id=${orderId}`;
    } else {
        showError(result.message);
    }
}
```

**SPEI**:
```javascript
async function processSpeiPayment(bankId) {
    const response = await fetch('/api/checkout.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'process_payment',
            method: 'spei',
            bankId: bankId,
            orderId: orderId
        })
    });
    
    const result = await response.json();
    
    // Orden creada en estado "pending_payment"
    // Cliente debe realizar transferencia externa
    window.location.href = `/order_confirmation.php?order_id=${orderId}&pending=true`;
}
```

**Efectivo**:
```javascript
async function processCashPayment() {
    const response = await fetch('/api/checkout.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'process_payment',
            method: 'cash',
            orderId: orderId
        })
    });
    
    const result = await response.json();
    
    // Orden creada en estado "cod" (cash on delivery)
    window.location.href = `/order_confirmation.php?order_id=${orderId}`;
}
```

---

## 4. Mi Cuenta (account.php)

**URL**: `/account.php`

**Propósito**: Panel de control del cliente donde puede ver su historial de pedidos, direcciones, wishlist y configuración.

**Requisitos de Acceso**:
- Login requerido
- Rol: client

### Estructura de la Página

#### Header
```
┌─────────────────────────────────────────────────────────┐
│ [← Regresar]  Mi Cuenta  [Cerrar Sesión]                 │
└─────────────────────────────────────────────────────────┘
```

#### Métricas del Cliente
```
┌─────────────────────────────────────────────────────────┐
│ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐   │
│ │   12     │ │  $5,420  │ │    3     │ │   150    │   │
│ │ Pedidos  │ │ Gastado  │ │ Direcc.  │ │ Puntos   │   │
│ └──────────┘ └──────────┘ └──────────┘ └──────────┘   │
└─────────────────────────────────────────────────────────┘
```

**Métricas**:
- Total de pedidos
- Total gastado
- Direcciones guardadas
- Puntos de lealtad (si está activo)

#### Navegación por Tabs
```
┌─────────────────────────────────────────────────────────┐
│ [Perfil] [Pedidos] [Direcciones] [Wishlist] [Favoritos] │
└─────────────────────────────────────────────────────────┘
```

#### Tab 1: Perfil
```
┌─────────────────────────────────────────────────────────┐
│ 👤 Mi Perfil                                             │
│                                                          │
│ Nombre: [Juan Pérez]                                    │
│ Email: [juan@email.com]                                 │
│ Teléfono: [55-1234-5678]                                │
│ RFC: [PEJU800101H01] (B2B)                             │
│ Segmento: [Contratista]                                 │
│ Puntos de Lealtad: 150                                  │
│                                                          │
│ [Editar Perfil] [Cambiar Contraseña]                    │
└─────────────────────────────────────────────────────────┘
```

**Campos Editables**:
- Nombre
- Email
- Teléfono
- Contraseña
- RFC (solo si B2B)
- Dirección fiscal

#### Tab 2: Pedidos
```
┌─────────────────────────────────────────────────────────┐
│ 📦 Mis Pedidos                                          │
│                                                          │
│ ┌───────────────────────────────────────────────────┐  │
│ │ #ORD-2024-00123                                     │  │
│ │ 15 Ene 2024 • 3 productos • $1,797.30              │  │
│ │ Estado: Entregado ✅                                 │  │
│ │ [Ver Detalles] [Rastrear] [Repetir Pedido]         │  │
│ └───────────────────────────────────────────────────┘  │
│ ┌───────────────────────────────────────────────────┐  │
│ │ #ORD-2024-00156                                     │  │
│ │ 20 Ene 2024 • 2 productos • $899.00                │  │
│ │ Estado: En tránsito 🚚                              │  │
│ │ [Ver Detalles] [Rastrear] [Cancelar]               │  │
│ └───────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

**Estados de Pedido**:
- `pending`: Pendiente de confirmación
- `processing`: En proceso
- `shipped`: Enviado
- `delivered`: Entregado
- `cancelled`: Cancelado
- `refunded`: Reembolsado

**Acciones**:
- Ver detalles
- Rastrear envío
- Repetir pedido
- Cancelar (si está permitido)
- Solicitar devolución

#### Tab 3: Direcciones
```
┌─────────────────────────────────────────────────────────┐
│ 📍 Mis Direcciones                                      │
│                                                          │
│ ┌───────────────────────────────────────────────────┐  │
│ │ Casa (Principal)                                   │  │
│ │ Av. Reforma 123, Col. Centro                       │  │
│ │ CDMX, 06000                                        │  │
│ │ [Editar] [Eliminar] [Establecer Principal]         │  │
│ └───────────────────────────────────────────────────┘  │
│ ┌───────────────────────────────────────────────────┐  │
│ │ Oficina                                            │  │
│ │ Insurgentes Sur 456, Col. Roma Norte             │  │
│ │ CDMX, 06700                                        │  │
│ │ [Editar] [Eliminar] [Establecer Principal]         │  │
│ └───────────────────────────────────────────────────┘  │
│                                                          │
│ [+ Agregar Nueva Dirección]                             │
└─────────────────────────────────────────────────────────┘
```

#### Tab 4: Wishlist
```
┌─────────────────────────────────────────────────────────┐
│ ❤️ Mi Lista de Deseos                                   │
│                                                          │
│ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐         │
│ │ [Imagen]    │ │ [Imagen]    │ │ [Imagen]    │         │
│ │ Taladro     │ │ Sierra      │ │ Nivel       │         │
│ │ $1,299.00   │ │ $899.00     │ │ $199.00     │         │
│ │ [Agregar]   │ │ [Agregar]   │ │ [Agregar]   │         │
│ │ [Eliminar]  │ │ [Eliminar]  │ │ [Eliminar]  │         │
│ └─────────────┘ └─────────────┘ └─────────────┘         │
└─────────────────────────────────────────────────────────┘
```

#### Tab 5: Favoritos
```
┌─────────────────────────────────────────────────────────┐
│ ⭐ Productos Favoritos                                  │
│                                                          │
│ [Lista de productos marcados como favoritos]            │
└─────────────────────────────────────────────────────────┘
```

### Funcionalidades JavaScript

**Cargar Historial de Pedidos**:
```javascript
function loadOrderHistory() {
    fetch('/api/orders.php?action=user_orders')
        .then(response => response.json())
        .then(data => {
            renderOrders(data.orders);
        });
}
```

**Cargar Direcciones**:
```javascript
function loadAddresses() {
    fetch('/api/addresses.php')
        .then(response => response.json())
        .then(data => {
            renderAddresses(data.addresses);
        });
}
```

**Cargar Wishlist**:
```javascript
function loadWishlist() {
    fetch('/api/wishlist.php')
        .then(response => response.json())
        .then(data => {
            renderWishlist(data.products);
        });
}
```

**Actualizar Perfil**:
```javascript
function updateProfile(profileData) {
    fetch('/api/profile.php', {
        method: 'POST',
        body: JSON.stringify(profileData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Perfil actualizado');
        }
    });
}
```

**Cambiar Contraseña**:
```javascript
function changePassword(currentPassword, newPassword) {
    fetch('/api/profile.php?action=change_password', {
        method: 'POST',
        body: JSON.stringify({
            currentPassword,
            newPassword
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Contraseña cambiada');
        } else {
            showError(data.message);
        }
    });
}
```

---

## 5. Dashboard del Cliente (customer_dashboard.php)

**URL**: `/customer_dashboard.php`

**Propósito**: Dashboard avanzado del cliente con métricas, gráficos y funcionalidades adicionales.

**Requisitos de Acceso**:
- Login requerido
- Rol: client

### Estructura de la Página

#### Header
```
┌─────────────────────────────────────────────────────────┐
│ [← Regresar]  Dashboard  [Cerrar Sesión]                │
└─────────────────────────────────────────────────────────┘
```

#### KPIs Principales
```
┌─────────────────────────────────────────────────────────┐
│ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐   │
│ │   12     │ │  $5,420  │ │   95%    │ │   150    │   │
│ │ Pedidos  │ │ Gastado  │ │ Satisfac.│ │ Puntos   │   │
│ └──────────┘ └──────────┘ └──────────┘ └──────────┘   │
└─────────────────────────────────────────────────────────┘
```

#### Gráfico de Gastos Mensuales
```
┌─────────────────────────────────────────────────────────┐
│ 📊 Gastos por Mes                                      │
│                                                          │
│ [Gráfico de barras con gastos de los últimos 12 meses]  │
└─────────────────────────────────────────────────────────┘
```

#### Últimos Pedidos
```
┌─────────────────────────────────────────────────────────┐
│ 📦 Últimos Pedidos                                      │
│                                                          │
│ [Tabla con últimos 5 pedidos]                            │
└─────────────────────────────────────────────────────────┘
```

#### Categorías Más Compradas
```
┌─────────────────────────────────────────────────────────┐
│ 🏷️ Categorías Más Compradas                             │
│                                                          │
│ [Gráfico de pastel con distribución por categoría]        │
└─────────────────────────────────────────────────────────┘
```

#### Recomendaciones Personalizadas
```
┌─────────────────────────────────────────────────────────┐
│ 💡 Recomendaciones para Ti                              │
│                                                          │
│ Basado en tu historial de compras:                      │
│ ┌─────────────┐ ┌─────────────┐ ┌─────────────┐         │
│ │ [Imagen]    │ │ [Imagen]    │ │ [Imagen]    │         │
│ │ Producto 1  │ │ Producto 2  │ │ Producto 3  │         │
│ │ $XXX.XX     │ │ $XXX.XX     │ │ $XXX.XX     │         │
│ │ [Agregar]   │ │ [Agregar]   │ │ [Agregar]   │         │
│ └─────────────┘ └─────────────┘ └─────────────┘         │
└─────────────────────────────────────────────────────────┘
```

#### Puntos de Lealtad
```
┌─────────────────────────────────────────────────────────┐
│ ⭐ Puntos de Lealtad                                     │
│                                                          │
│ Tienes 150 puntos                                        │
│ Próximo nivel: 200 puntos (Bronce)                      │
│ Faltan 50 puntos para el siguiente nivel                 │
│                                                          │
│ [Ver Recompensas Disponibles]                            │
└─────────────────────────────────────────────────────────┘
```

### Funcionalidades Avanzadas

**Sistema de Recomendación**:
```javascript
function loadRecommendations() {
    fetch('/api/recommendations.php')
        .then(response => response.json())
        .then(data => {
            renderRecommendations(data.products);
        });
}
```

**Cálculo de Puntos**:
```javascript
function calculatePoints(orderAmount) {
    const pointsPerPeso = 0.1; // 1 punto por cada $10
    return Math.floor(orderAmount * pointsPerPeso);
}
```

**Niveles de Lealtad**:
- **Básico**: 0-199 puntos
- **Bronce**: 200-499 puntos
- **Plata**: 500-999 puntos
- **Oro**: 1000+ puntos

**Beneficios por Nivel**:
- Básico: Sin beneficios especiales
- Bronce: 5% de descuento en cumpleaños
- Plata: 10% de descuento, envío gratis
- Oro: 15% de descuento, envío gratis, acceso anticipado a ofertas

---

## PARTE 2: INTERFACES DE ADMINISTRACIÓN - TIENDA

---

## 6. Pedidos/Ventas (orders.php)

**URL**: `/orders.php`

**Propósito**: Gestión de pedidos online y locales para administradores y empleados.

**Requisitos de Acceso**:
- Login requerido
- Rol: admin o employee

### Estructura de la Página

#### Header
```
┌─────────────────────────────────────────────────────────┐
│ [← Regresar]  Pedidos/Ventas  [Cerrar Sesión]          │
└─────────────────────────────────────────────────────────┘
```

#### Filtros de Búsqueda
```
┌─────────────────────────────────────────────────────────┐
│ 🔍 Buscar: [________________] [Filtrar ▼]               │
│ Estado: [Todos ▼]  Fecha: [Todas ▼]  Cliente: [Todos ▼]│
└─────────────────────────────────────────────────────────┘
```

**Filtros Disponibles**:
- **Estado**: Todos, Pendiente, En Proceso, Enviado, Entregado, Cancelado
- **Fecha**: Hoy, Esta semana, Este mes, Personalizado
- **Cliente**: Todos, Cliente específico
- **Monto**: Rango de montos
- **Método de Pago**: Todos, Tarjeta, SPEI, Efectivo

#### Lista de Pedidos
```
┌─────────────────────────────────────────────────────────┐
│ ┌───────────────────────────────────────────────────┐  │
│ │ #ORD-2024-00123  |  Juan Pérez  |  $1,797.30      │  │
│ │ 15 Ene 2024     |  3 productos  |  Tarjeta        │  │
│ │ Estado: Entregado ✅  |  [Ver] [Editar] [Facturar]│  │
│ └───────────────────────────────────────────────────┘  │
│ ┌───────────────────────────────────────────────────┐  │
│ │ #ORD-2024-00156  |  María López  |  $899.00       │  │
│ │ 20 Ene 2024     |  2 productos  |  SPEI           │  │
│ │ Estado: En tránsito 🚚  |  [Ver] [Editar] [Facturar]│  │
│ └───────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

#### Modal de Detalle de Pedido
```
┌─────────────────────────────────────────────────────────┐
│ 📦 Detalle del Pedido #ORD-2024-00123        [Cerrar ✕] │
├─────────────────────────────────────────────────────────┤
│ Cliente: Juan Pérez (juan@email.com)                    │
│ Teléfono: 55-1234-5678                                  │
│ Dirección: Av. Reforma 123, Col. Centro, CDMX 06000     │
│ Método de Pago: Tarjeta de Crédito                       │
│ Método de Envío: Estándar (3-5 días)                     │
├─────────────────────────────────────────────────────────┤
│ Productos:                                              │
│ • Taladro Percutor 18V x 1        $1,299.00             │
│ • Martillo Profesional x 2         $498.00               │
│ ───────────────────────────────────────                 │
│ Subtotal: $1,797.00                                      │
│ Envío: $150.00                                           │
│ Total: $1,947.00                                         │
├─────────────────────────────────────────────────────────┤
│ Estado Actual: Entregado                                 │
│ Cambiar a: [En Proceso ▼] [Actualizar]                  │
├─────────────────────────────────────────────────────────┤
│ [Imprimir Ticket] [Enviar Email] [Facturar] [Cancelar]  │
└─────────────────────────────────────────────────────────┘
```

### Funcionalidades

**Crear Nuevo Pedido**:
```javascript
function createNewOrder() {
    // Abre modal para crear pedido manual
    showNewOrderModal();
}
```

**Editar Pedido**:
```javascript
function editOrder(orderId) {
    fetch(`/api/orders.php?action=get_order&order_id=${orderId}`)
        .then(response => response.json())
        .then(data => {
            showEditOrderModal(data.order);
        });
}
```

**Cambiar Estado**:
```javascript
function updateOrderStatus(orderId, newStatus) {
    fetch(`/api/orders.php?action=update_status`, {
        method: 'POST',
        body: JSON.stringify({
            orderId,
            status: newStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadOrders();
            showNotification('Estado actualizado');
        }
    });
}
```

**Cancelar Pedido**:
```javascript
function cancelOrder(orderId, reason) {
    fetch(`/api/orders.php?action=cancel`, {
        method: 'POST',
        body: JSON.stringify({
            orderId,
            reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadOrders();
            showNotification('Pedido cancelado');
        }
    });
}
```

**Imprimir Ticket**:
```javascript
function printTicket(orderId) {
    window.open(`/api/print_ticket.php?order_id=${orderId}`, '_blank');
}
```

**Facturar Pedido**:
```javascript
function invoiceOrder(orderId) {
    window.open(`/admin_online_billing.php?order_id=${orderId}`, '_blank');
}
```

### Estados de Pedido

**Workflow de Estados**:
```
pending → processing → shipped → delivered
   ↓          ↓           ↓
cancelled   cancelled   cancelled
```

**Descripciones**:
- `pending`: Pedido recibido, pendiente de confirmación
- `processing`: Pedido confirmado, en preparación
- `shipped`: Pedido enviado, en tránsito
- `delivered`: Pedido entregado al cliente
- `cancelled`: Pedido cancelado
- `refunded`: Pedido reembolsado

---

## 7. Seguimiento/Logística (order_tracking.php)

**URL**: `/order_tracking.php`

**Propósito**: Seguimiento de envíos y logística de pedidos.

**Requisitos de Acceso**:
- Login requerido
- Rol: admin o employee

### Estructura de la Página

#### Header
```
┌─────────────────────────────────────────────────────────┐
│ [← Regresar]  Seguimiento/Logística  [Cerrar Sesión]    │
└─────────────────────────────────────────────────────────┘
```

#### Buscador de Pedidos
```
┌─────────────────────────────────────────────────────────┐
│ 🔍 Rastrear Pedido                                      │
│                                                          │
│ Número de Pedido: [ORD-2024-00123____] [Rastrear]       │
│                                                          │
│ O rastrear por:                                         │
│ [Número de Guía______] [Rastrear]                        │
└─────────────────────────────────────────────────────────┘
```

#### Timeline de Seguimiento
```
┌─────────────────────────────────────────────────────────┐
│ 📦 Seguimiento del Pedido #ORD-2024-00123               │
│                                                          │
│ Estado Actual: En tránsito 🚚                            │
│                                                          │
│ Timeline:                                                │
│ ┌───────────────────────────────────────────────────┐  │
│ │ ✅ Pedido Confirmado                                │  │
│ │    15 Ene 2024, 10:30 AM                           │  │
│ └───────────────────────────────────────────────────┘  │
│ ┌───────────────────────────────────────────────────┐  │
│ │ ✅ En Proceso                                      │  │
│ │    15 Ene 2024, 2:00 PM                            │  │
│ └───────────────────────────────────────────────────┘  │
│ ┌───────────────────────────────────────────────────┐  │
│ │ ✅ Enviado                                         │  │
│ │    16 Ene 2024, 9:00 AM                            │  │
│ │    Guía: MX-123456789                              │  │
│ └───────────────────────────────────────────────────┘  │
│ ┌───────────────────────────────────────────────────┐  │
│ │ ⏳ En Tránsito                                     │  │
│ │    16 Ene 2024, 11:00 AM                           │  │
│ │    Ubicación: CDMX - Centro de distribución        │  │
│ └───────────────────────────────────────────────────┘  │
│ ┌───────────────────────────────────────────────────┐  │
│ │ ⏳ Entregado (Pendiente)                           │  │
│ │    Estimado: 17 Ene 2024                           │  │
│ └───────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

#### Mapa de Ubicación
```
┌─────────────────────────────────────────────────────────┐
│ 🗺️ Ubicación del Paquete                                │
│                                                          │
│ [Mapa con ubicación actual del paquete]                  │
└─────────────────────────────────────────────────────────┘
```

#### Información del Transportista
```
┌─────────────────────────────────────────────────────────┐
│ 🚚 Información del Transportista                         │
│                                                          │
│ Transportista: FedEx Express                             │
│ Número de Guía: 1234567890123                           │
│ Teléfono: 55-1234-5678                                  │
│                                                          │
│ [Contactar Transportista] [Ver en sitio de transportista]│
└─────────────────────────────────────────────────────────┘
```

### Funcionalidades

**Rastrear por Número de Pedido**:
```javascript
function trackByOrder(orderNumber) {
    fetch(`/api/tracking.php?action=by_order&order_number=${orderNumber}`)
        .then(response => response.json())
        .then(data => {
            renderTracking(data.tracking);
        });
}
```

**Rastrear por Número de Guía**:
```javascript
function trackByGuide(guideNumber) {
    fetch(`/api/tracking.php?action=by_guide&guide_number=${guideNumber}`)
        .then(response => response.json())
        .then(data => {
            renderTracking(data.tracking);
        });
}
```

**Actualizar Estado de Envío**:
```javascript
function updateShippingStatus(orderId, status, location) {
    fetch(`/api/tracking.php?action=update_status`, {
        method: 'POST',
        body: JSON.stringify({
            orderId,
            status,
            location
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadTracking(orderId);
        }
    });
}
```

**Integración con Transportistas**:
- FedEx API
- UPS API
- DHL API
- Estafeta API

---

## 8. Devoluciones RMA (rma_manager.php)

**URL**: `/rma_manager.php`

**Propósito**: Gestión de solicitudes de devolución y reembolso (RMA - Return Merchandise Authorization).

**Requisitos de Acceso**:
- Login requerido
- Rol: admin o employee

### Estructura de la Página

#### Header
```
┌─────────────────────────────────────────────────────────┐
│ [← Regresar]  Devoluciones RMA  [Cerrar Sesión]         │
└─────────────────────────────────────────────────────────┘
```

#### Filtros
```
┌─────────────────────────────────────────────────────────┐
│ Estado: [Todos ▼]  Fecha: [Todas ▼]  Tipo: [Todos ▼]    │
└─────────────────────────────────────────────────────────┘
```

#### Lista de Solicitudes RMA
```
┌─────────────────────────────────────────────────────────┐
│ ┌───────────────────────────────────────────────────┐  │
│ │ #RMA-2024-00123  |  ORD-2024-00156  |  $899.00     │  │
│ │ Juan Pérez       |  20 Ene 2024      |  Producto     │  │
│ │ Motivo: Producto defectuoso                          │  │
│ │ Estado: Pendiente de Aprobación ⏳                   │  │
│ │ [Ver Detalles] [Aprobar] [Rechazar]                 │  │
│ └───────────────────────────────────────────────────┘  │
│ ┌───────────────────────────────────────────────────┐  │
│ │ #RMA-2024-00124  |  ORD-2024-00178  |  $450.00     │  │
│ │ María López      |  22 Ene 2024      |  Producto     │  │
│ │ Motivo: No es lo que esperaba                     │  │
│ │ Estado: Aprobado ✅  [Generar Etiqueta]            │  │
│ │ [Ver Detalles] [Procesar Reembolso]               │  │
│ └───────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

#### Modal de Solicitud RMA
```
┌─────────────────────────────────────────────────────────┐
│ 📝 Nueva Solicitud de Devolución           [Cerrar ✕]   │
├─────────────────────────────────────────────────────────┤
│ Número de Pedido: [ORD-2024-00156____]                  │
│                                                          │
│ Producto a Devolver:                                     │
│ [Taladro Percutor 18V ▼]                                │
│                                                          │
│ Cantidad: [1]                                            │
│                                                          │
│ Motivo de Devolución:                                    │
│ [Producto defectuoso ▼]                                  │
│                                                          │
│ Descripción:                                             │
│ [________________________________________________]     │
│                                                          │
│ Tipo de Reembolso:                                       │
│ ○ Reembolso al método de pago original                  │
│ ○ Tienda de crédito                                     │
│ ○ Reemplazo del producto                                │
│                                                          │
│ Evidencia (opcional):                                     │
│ [Adjuntar fotos/videos]                                  │
│                                                          │
│ [Enviar Solicitud]                                       │
└─────────────────────────────────────────────────────────┘
```

### Estados de RMA

**Workflow**:
```
pending → approved → processing → completed
   ↓          ↓          ↓
rejected   rejected   refunded
```

**Descripciones**:
- `pending`: Pendiente de aprobación
- `approved`: Aprobado, esperando producto
- `processing`: Producto recibido, procesando reembolso
- `completed`: Reembolso completado
- `rejected`: Solicitud rechazada
- `refunded`: Reembolso procesado

### Funcionalidades

**Crear Solicitud RMA**:
```javascript
function createRMA(orderId, productId, reason, description) {
    fetch('/api/rma.php', {
        method: 'POST',
        body: JSON.stringify({
            orderId,
            productId,
            reason,
            description
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Solicitud creada');
        }
    });
}
```

**Aprobar RMA**:
```javascript
function approveRMA(rmaId) {
    fetch(`/api/rma.php?action=approve`, {
        method: 'POST',
        body: JSON.stringify({ rmaId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadRMAs();
            showNotification('RMA aprobada');
        }
    });
}
```

**Rechazar RMA**:
```javascript
function rejectRMA(rmaId, reason) {
    fetch(`/api/rma.php?action=reject`, {
        method: 'POST',
        body: JSON.stringify({
            rmaId,
            reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadRMAs();
            showNotification('RMA rechazada');
        }
    });
}
```

**Procesar Reembolso**:
```javascript
function processRefund(rmaId) {
    fetch(`/api/rma.php?action=refund`, {
        method: 'POST',
        body: JSON.stringify({ rmaId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadRMAs();
            showNotification('Reembolso procesado');
        }
    });
}
```

**Generar Etiqueta de Devolución**:
```javascript
function generateReturnLabel(rmaId) {
    window.open(`/api/rma.php?action=label&rma_id=${rmaId}`, '_blank');
}
```

---

## 9. Facturación & Pagos SAT (admin_online_billing.php)

**URL**: `/admin_online_billing.php`

**Propósito**: Interfaz principal de administración para facturación fiscal CFDI 4.0 y pasarelas de pago.

**Requisitos de Acceso**:
- Login requerido
- Rol: admin o employee

*(Esta interfaz ya está documentada en detalle en DOCUMENTACION_SISTEMA_PAGOS_FACTURACION.md)*

---

## 10. Configuración de Pagos (admin_payment_config.php)

**URL**: `/admin_payment_config.php`

**Propósito**: Interfaz específica para configuración de bancos mexicanos y métodos de pago.

**Requisitos de Acceso**:
- Login requerido
- Rol: admin o employee

*(Esta interfaz ya está documentada en detalle en DOCUMENTACION_SISTEMA_PAGOS_FACTURACION.md)*

---

## PARTE 3: INTERFACES DE ADMINISTRACIÓN - LOCAL

---

## 11. Caja/Punto de Venta (cashier.php)

**URL**: `/cashier.php`

**Propósito**: Sistema de punto de venta para ventas en tienda física.

**Requisitos de Acceso**:
- Login requerido
- Rol: admin o employee

### Estructura de la Página

#### Header
```
┌─────────────────────────────────────────────────────────┐
│ [← Regresar]  Caja/Punto de Venta  [Cerrar Sesión]      │
└─────────────────────────────────────────────────────────┘
```

#### Estado del Cajón
```
┌─────────────────────────────────────────────────────────┐
│ 💰 Estado del Cajón                                      │
│                                                          │
│ Estado: [Abierto ✅]  [Cerrar Cajón]  [Abrir Cajón]      │
│                                                          │
│ Saldo Inicial: $5,000.00                                 │
│ Ventas del Turno: $12,450.00                             │
│ Saldo Actual: $17,450.00                                 │
└─────────────────────────────────────────────────────────┘
```

#### Buscador de Productos
```
┌─────────────────────────────────────────────────────────┐
│ 🔍 Buscar Producto: [__________________] [Buscar]         │
│                                                          │
│ O escanear código de barras: [__________________]         │
└─────────────────────────────────────────────────────────┘
```

#### Carrito de Venta
```
┌─────────────────────────────────────────────────────────┐
│ 🛒 Carrito de Venta                                     │
│                                                          │
│ ┌───────────────────────────────────────────────────┐  │
│ │ Taladro Percutor 18V                               │  │
│ │ SKU: TAL-1800  |  Cantidad: [1]  |  $1,299.00   │  │
│ │ [Eliminar]                                         │  │
│ └───────────────────────────────────────────────────┘  │
│ ┌───────────────────────────────────────────────────┐  │
│ │ Martillo Profesional                               │  │
│ │ SKU: MAR-0500  |  Cantidad: [2]  |  $498.00     │  │
│ │ [Eliminar]                                         │  │
│ └───────────────────────────────────────────────────┘  │
├─────────────────────────────────────────────────────────┤
│ Subtotal: $1,797.00                                      │
│ IVA (16%): $287.52                                       │
│ Total: $2,084.52                                         │
└─────────────────────────────────────────────────────────┘
```

#### Datos del Cliente
```
┌─────────────────────────────────────────────────────────┐
│ 👤 Datos del Cliente (Opcional)                         │
│                                                          │
│ Nombre: [_________________________]                     │
│ Teléfono: [_________________________]                   │
│ RFC: [_________________________] (para facturar)         │
│                                                          │
│ [Buscar Cliente] [Nuevo Cliente]                        │
└─────────────────────────────────────────────────────────┘
```

#### Método de Pago
```
┌─────────────────────────────────────────────────────────┐
│ 💳 Método de Pago                                      │
│                                                          │
│ ○ Efectivo                                              │
│ ○ Tarjeta de Crédito/Débito                            │
│ ○ Transferencia                                         │
│ ○ Vale                                                 │
└─────────────────────────────────────────────────────────┘
```

**Pago en Efectivo**:
```
┌─────────────────────────────────────────────────────────┐
│ Pago en Efectivo                                        │
│                                                          │
│ Total: $2,084.52                                         │
│ Recibido: [$___________]                                 │
│ Cambio: $___________                                      │
│                                                          │
│ [Procesar Venta]                                         │
└─────────────────────────────────────────────────────────┘
```

**Pago con Tarjeta**:
```
┌─────────────────────────────────────────────────────────┐
│ Pago con Tarjeta                                        │
│                                                          │
│ [Conectar terminal POS]                                  │
│                                                          │
│ O ingresar manualmente:                                   │
│ Últimos 4 dígitos: [____]                                │
│ Monto: $2,084.52                                         │
│                                                          │
│ [Procesar Venta]                                         │
└─────────────────────────────────────────────────────────┘
```

### Funcionalidades

**Abrir/Cerrar Cajón**:
```javascript
function openDrawer(initialAmount) {
    fetch('/api/cashier.php?action=open_drawer', {
        method: 'POST',
        body: JSON.stringify({ initialAmount })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateDrawerStatus('open');
        }
    });
}

function closeDrawer() {
    fetch('/api/cashier.php?action=close_drawer', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateDrawerStatus('closed');
        }
    });
}
```

**Buscar Producto**:
```javascript
function searchProduct(query) {
    fetch(`/api/products.php?action=search&q=${query}`)
        .then(response => response.json())
        .then(data => {
            showProductResults(data.products);
        });
}
```

**Escanear Código de Barras**:
```javascript
function scanBarcode(barcode) {
    fetch(`/api/products.php?action=barcode&barcode=${barcode}`)
        .then(response => response.json())
        .then(data => {
            if (data.product) {
                addToCart(data.product);
            }
        });
}
```

**Procesar Venta**:
```javascript
function processSale(paymentMethod, paymentData) {
    fetch('/api/cashier.php?action=process_sale', {
        method: 'POST',
        body: JSON.stringify({
            cart: cart,
            customer: customer,
            paymentMethod,
            paymentData
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            printReceipt(data.saleId);
            clearCart();
            showNotification('Venta procesada');
        }
    });
}
```

**Imprimir Ticket**:
```javascript
function printReceipt(saleId) {
    window.open(`/api/print_receipt.php?sale_id=${saleId}`, '_blank');
}
```

### Reportes de Caja

**Corte de Caja**:
```
┌─────────────────────────────────────────────────────────┐
│ 📊 Corte de Caja                                        │
│                                                          │
│ Fecha: 15 Ene 2024                                      │
│ Cajero: Juan Pérez                                      │
│                                                          │
│ Ventas del Turno:                                       │
│ • Efectivo: $8,500.00                                   │
│ • Tarjeta: $3,950.00                                    │
│ • Total: $12,450.00                                     │
│                                                          │
│ Saldo Inicial: $5,000.00                                │
│ Saldo Final: $17,450.00                                 │
│                                                          │
│ [Imprimir Corte] [Cerrar Cajón]                         │
└─────────────────────────────────────────────────────────┘
```

---

## 12. Aprobación B2B (b2b_approval.php)

**URL**: `/b2b_approval.php`

**Propósito**: Panel para aprobar solicitudes B2B de contratistas, escuelas y establecimientos.

**Requisitos de Acceso**:
- Login requerido
- Rol: admin

### Estructura de la Página

#### Header
```
┌─────────────────────────────────────────────────────────┐
│ [← Regresar]  Aprobación B2B  [Cerrar Sesión]           │
└─────────────────────────────────────────────────────────┘
```

#### Lista de Solicitudes
```
┌─────────────────────────────────────────────────────────┐
│ ┌───────────────────────────────────────────────────┐  │
│ │ #B2B-00123  |  Constructora ABC  |  Pendiente ⏳   │  │
│ │ RFC: CON990101XYZ                                   │  │
│ │ Razón Social: Constructora ABC S.A. de C.V.         │  │
│ │ Segmento Solicitado: Contratista                    │  │
│ │ Fecha: 15 Ene 2024                                   │  │
│ │ [Ver Detalles] [Aprobar] [Rechazar]                 │  │
│ └───────────────────────────────────────────────────┘  │
│ ┌───────────────────────────────────────────────────┐  │
│ │ #B2B-00124  |  Escuela Secundaria #45  |  Pendiente │  │
│ │ RFC: ESC990201ABC                                   │  │
│ │ Razón Social: Escuela Secundaria #45                │  │
│ │ Segmento Solicitado: Escuela                        │  │
│ │ Fecha: 16 Ene 2024                                   │  │
│ │ [Ver Detalles] [Aprobar] [Rechazar]                 │  │
│ └───────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

#### Modal de Aprobación
```
┌─────────────────────────────────────────────────────────┐
│ ✅ Aprobar Solicitud B2B #B2B-00123        [Cerrar ✕]   │
├─────────────────────────────────────────────────────────┤
│ Cliente: Constructora ABC                                │
│ RFC: CON990101XYZ                                       │
│ Razón Social: Constructora ABC S.A. de C.V.             │
│ Email: contacto@constructoraabc.com                      │
│ Teléfono: 55-9876-5432                                  │
│                                                          │
│ Segmento Solicitado: Contratista                         │
│                                                          │
│ Asignar Segmento:                                        │
│ [Contratista ▼]                                         │
│ [Escuela ▼]                                             │
│ [Establecimiento ▼]                                      │
│                                                          │
│ Notas (opcional):                                        │
│ [________________________________________________]     │
│                                                          │
│ [Aprobar Solicitud] [Cancelar]                           │
└─────────────────────────────────────────────────────────┘
```

### Segmentos B2B

**Contratista**:
- Descuento: 10-15%
- Crédito: Hasta 30 días
- Requisitos: RFC, Cédula fiscal

**Escuela**:
- Descuento: 15-20%
- Crédito: Hasta 45 días
- Requisitos: RFC, Cédula fiscal, RFC de escuela

**Establecimiento**:
- Descuento: 5-10%
- Crédito: Hasta 15 días
- Requisitos: RFC, Cédula fiscal

### Funcionalidades

**Aprobar Solicitud**:
```javascript
function approveB2B(applicationId, segment, notes) {
    fetch('/api/b2b.php?action=approve', {
        method: 'POST',
        body: JSON.stringify({
            applicationId,
            segment,
            notes
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadApplications();
            showNotification('Solicitud aprobada');
        }
    });
}
```

**Rechazar Solicitud**:
```javascript
function rejectB2B(applicationId, reason) {
    fetch('/api/b2b.php?action=reject', {
        method: 'POST',
        body: JSON.stringify({
            applicationId,
            reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadApplications();
            showNotification('Solicitud rechazada');
        }
    });
}
```

---

## 13. Tickets y Cotizaciones (tickets.php)

**URL**: `/tickets.php`

**Propósito**: Gestión de tickets de venta y cotizaciones para clientes en tienda.

**Requisitos de Acceso**:
- Login requerido
- Rol: admin o employee

### Estructura de la Página

#### Header
```
┌─────────────────────────────────────────────────────────┐
│ [← Regresar]  Tickets y Cotizaciones  [Cerrar Sesión]   │
└─────────────────────────────────────────────────────────┘
```

#### Acciones Rápidas
```
┌─────────────────────────────────────────────────────────┐
│ [+ Nuevo Ticket]  [+ Nueva Cotización]                   │
└─────────────────────────────────────────────────────────┘
```

#### Lista de Tickets
```
┌─────────────────────────────────────────────────────────┐
│ ┌───────────────────────────────────────────────────┐  │
│ │ #TKT-2024-00123  |  Juan Pérez  |  $1,299.00     │  │
│ │ 15 Ene 2024     |  Ticket       |  Pendiente     │  │
│ │ [Ver] [Convertir a Venta] [Imprimir] [Eliminar]    │  │
│ └───────────────────────────────────────────────────┘  │
│ ┌───────────────────────────────────────────────────┐  │
│ │ #COT-2024-00156  |  María López  |  $899.00      │  │
│ │ 16 Ene 2024     |  Cotización   |  Enviada       │  │
│ │ [Ver] [Convertir a Venta] [Imprimir] [Reenviar]    │  │
│ └───────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

#### Modal de Nuevo Ticket
```
┌─────────────────────────────────────────────────────────┐
│ 📝 Nuevo Ticket                              [Cerrar ✕]   │
├─────────────────────────────────────────────────────────┤
│ Cliente: [Buscar cliente...]                             │
│                                                          │
│ Productos:                                              │
│ [Buscar producto...] [+ Agregar]                         │
│                                                          │
│ ┌───────────────────────────────────────────────────┐  │
│ │ Taladro Percutor 18V                               │  │
│ │ Cantidad: [1]  |  Precio: [$1,299.00]            │  │
│ │ [Eliminar]                                         │  │
│ └───────────────────────────────────────────────────┘  │
│                                                          │
│ Subtotal: $1,299.00                                      │
│ IVA (16%): $207.84                                       │
│ Total: $1,506.84                                         │
│                                                          │
│ Notas: [________________________________________]       │
│                                                          │
│ [Guardar Ticket] [Guardar como Cotización]               │
└─────────────────────────────────────────────────────────┘
```

### Funcionalidades

**Crear Ticket**:
```javascript
function createTicket(clientId, products, notes) {
    fetch('/api/tickets.php', {
        method: 'POST',
        body: JSON.stringify({
            clientId,
            products,
            notes,
            type: 'ticket'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadTickets();
            showNotification('Ticket creado');
        }
    });
}
```

**Crear Cotización**:
```javascript
function createQuote(clientId, products, notes) {
    fetch('/api/tickets.php', {
        method: 'POST',
        body: JSON.stringify({
            clientId,
            products,
            notes,
            type: 'quote'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadTickets();
            showNotification('Cotización creada');
        }
    });
}
```

**Convertir a Venta**:
```javascript
function convertToSale(ticketId) {
    fetch('/api/tickets.php?action=convert', {
        method: 'POST',
        body: JSON.stringify({ ticketId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = `/cashier.php?sale_id=${data.saleId}`;
        }
    });
}
```

**Enviar Cotización por WhatsApp**:
```javascript
function sendQuoteWhatsApp(quoteId) {
    fetch(`/api/tickets.php?action=send_whatsapp`, {
        method: 'POST',
        body: JSON.stringify({ quoteId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.open(data.whatsappUrl, '_blank');
        }
    });
}
```

---

## 14. Validación de Tickets (ticket_validation.php)

**URL**: `/ticket_validation.php`

**Propósito**: Validación de tickets de venta para clientes que desean canjear promociones o devoluciones.

**Requisitos de Acceso**:
- Público (cualquier empleado puede usar)

### Estructura de la Página

#### Header
```
┌─────────────────────────────────────────────────────────┐
│ [← Regresar]  Validación de Tickets  [Cerrar Sesión]    │
└─────────────────────────────────────────────────────────┘
```

#### Buscador de Tickets
```
┌─────────────────────────────────────────────────────────┐
│ 🔍 Validar Ticket                                       │
│                                                          │
│ Número de Ticket: [TKT-2024-00123____] [Validar]        │
│                                                          │
│ O escanear código QR: [📷 Escanear]                       │
└─────────────────────────────────────────────────────────┘
```

#### Resultado de Validación
```
┌─────────────────────────────────────────────────────────┐
│ ✅ Ticket Válido                                         │
├─────────────────────────────────────────────────────────┤
│ Número: TKT-2024-00123                                  │
│ Fecha: 15 Ene 2024                                       │
│ Cliente: Juan Pérez                                      │
│                                                          │
│ Productos:                                              │
│ • Taladro Percutor 18V x 1        $1,299.00             │
│ • Martillo Profesional x 2         $498.00               │
│ ───────────────────────────────────────                 │
│ Total: $1,797.00                                         │
│                                                          │
│ Estado: No canjeado                                     │
│                                                          │
│ [Canjear Promoción] [Ver Detalles]                       │
└─────────────────────────────────────────────────────────┘
```

### Funcionalidades

**Validar Ticket**:
```javascript
function validateTicket(ticketNumber) {
    fetch(`/api/ticket_validation.php?action=validate&ticket_number=${ticketNumber}`)
        .then(response => response.json())
        .then(data => {
            if (data.valid) {
                showTicketDetails(data.ticket);
            } else {
                showError('Ticket inválido o ya canjeado');
            }
        });
}
```

**Escanear Código QR**:
```javascript
function scanQRCode() {
    // Usar cámara del dispositivo para escanear QR
    const scanner = new Html5QrcodeScanner("reader");
    scanner.render(onScanSuccess);
}

function onScanSuccess(decodedText) {
    validateTicket(decodedText);
}
```

**Canjear Promoción**:
```javascript
function redeemPromotion(ticketId, promotionId) {
    fetch('/api/ticket_validation.php?action=redeem', {
        method: 'POST',
        body: JSON.stringify({
            ticketId,
            promotionId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Promoción canjeada');
        }
    });
}
```

---

## 15. Tareas de Empleados (tasks.php)

**URL**: `/tasks.php`

**Propósito**: Gestión de tareas asignadas a empleados del almacén y tienda.

**Requisitos de Acceso**:
- Login requerido
- Rol: admin o employee

### Estructura de la Página

#### Header
```
┌─────────────────────────────────────────────────────────┐
│ [← Regresar]  Tareas de Empleados  [Cerrar Sesión]      │
└─────────────────────────────────────────────────────────┘
```

#### Filtros
```
┌─────────────────────────────────────────────────────────┐
│ Empleado: [Todos ▼]  Estado: [Todas ▼]  Prioridad: [Todas ▼]│
└─────────────────────────────────────────────────────────┘
```

#### Lista de Tareas
```
┌─────────────────────────────────────────────────────────┐
│ ┌───────────────────────────────────────────────────┐  │
│ │ #TSK-00123  |  Reabastecer Taladros  |  Alta 🔴   │  │
│ │ Asignado a: Pedro López                            │  │
│ │ Fecha límite: 20 Ene 2024                           │  │
│ │ Estado: Pendiente ⏳                                 │  │
│ │ [Ver Detalles] [Completar] [Reasignar]             │  │
│ └───────────────────────────────────────────────────┘  │
│ ┌───────────────────────────────────────────────────┐  │
│ │ #TSK-00124  |  Organizar Pasillo 3  |  Media 🟡   │  │
│ │ Asignado a: María García                            │  │
│ │ Fecha límite: 22 Ene 2024                           │  │
│ │ Estado: En Proceso 🔄                                 │  │
│ │ [Ver Detalles] [Completar] [Reasignar]             │  │
│ └───────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

#### Modal de Nueva Tarea
```
┌─────────────────────────────────────────────────────────┐
│ 📝 Nueva Tarea                                [Cerrar ✕]   │
├─────────────────────────────────────────────────────────┤
│ Título: [______________________________________]       │
│                                                          │
│ Descripción:                                            │
│ [________________________________________________]     │
│ [________________________________________________]     │
│                                                          │
│ Asignar a: [Seleccionar empleado ▼]                     │
│                                                          │
│ Prioridad:                                              │
│ ○ Baja 🟢                                                │
│ ○ Media 🟡                                               │
│ ○ Alta 🔴                                                │
│                                                          │
│ Fecha límite: [DD/MM/AAAA]                               │
│                                                          │
│ [Crear Tarea]                                           │
└─────────────────────────────────────────────────────────┘
```

### Funcionalidades

**Crear Tarea**:
```javascript
function createTask(title, description, assigneeId, priority, dueDate) {
    fetch('/api/tasks.php', {
        method: 'POST',
        body: JSON.stringify({
            title,
            description,
            assigneeId,
            priority,
            dueDate
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadTasks();
            showNotification('Tarea creada');
        }
    });
}
```

**Completar Tarea**:
```javascript
function completeTask(taskId) {
    fetch('/api/tasks.php?action=complete', {
        method: 'POST',
        body: JSON.stringify({ taskId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadTasks();
            showNotification('Tarea completada');
        }
    });
}
```

**Reasignar Tarea**:
```javascript
function reassignTask(taskId, newAssigneeId) {
    fetch('/api/tasks.php?action=reassign', {
        method: 'POST',
        body: JSON.stringify({
            taskId,
            newAssigneeId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadTasks();
            showNotification('Tarea reasignada');
        }
    });
}
```

---

## PARTE 4: INTERFACES DE ADMINISTRACIÓN - SOLO ADMIN

---

## 16. Abastecimiento/Precios (admin_supply.php)

**URL**: `/admin_supply.php`

**Propósito**: Gestión de proveedores, precios de compra y abastecimiento de inventario.

**Requisitos de Acceso**:
- Login requerido
- Rol: admin

### Estructura de la Página

#### Header
```
┌─────────────────────────────────────────────────────────┐
│ [← Regresar]  Abastecimiento/Precios  [Cerrar Sesión]   │
└─────────────────────────────────────────────────────────┘
```

#### Tabs de Navegación
```
┌─────────────────────────────────────────────────────────┐
│ [Proveedores] [Órdenes de Compra] [Precios] [Stock]     │
└─────────────────────────────────────────────────────────┘
```

#### Tab 1: Proveedores
```
┌─────────────────────────────────────────────────────────┐
│ 📦 Proveedores                                          │
│                                                          │
│ [+ Agregar Proveedor]                                    │
│                                                          │
│ ┌───────────────────────────────────────────────────┐  │
│ │ Truper S.A. de C.V.                                │  │
│ │ Contacto: ventas@truper.com                        │  │
│ │ Teléfono: 55-1234-5678                              │  │
│ │ Productos: 150                                      │  │
│ │ [Ver Detalles] [Editar] [Eliminar]                  │  │
│ └───────────────────────────────────────────────────┘  │
│ ┌───────────────────────────────────────────────────┐  │
│ │ Bosch Herramientas                                 │  │
│ │ Contacto: mexico@bosch.com                        │  │
│ │ Teléfono: 55-9876-5432                              │  │
│ │ Productos: 85                                       │  │
│ │ [Ver Detalles] [Editar] [Eliminar]                  │  │
│ └───────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

#### Tab 2: Órdenes de Compra
```
┌─────────────────────────────────────────────────────────┐
│ 📋 Órdenes de Compra                                    │
│                                                          │
│ [+ Nueva Orden de Compra]                               │
│                                                          │
│ ┌───────────────────────────────────────────────────┐  │
│ │ #PO-2024-00123  |  Truper S.A.  |  $15,450.00     │  │
│ │ 15 Ene 2024     |  25 productos  |  Enviada ✅    │  │
│ │ [Ver Detalles] [Recibir] [Cancelar]                  │  │
│ └───────────────────────────────────────────────────┘  │
│ ┌───────────────────────────────────────────────────┐  │
│ │ #PO-2024-00124  |  Bosch       |  $8,900.00      │  │
│ │ 16 Ene 2024     |  12 productos  |  Pendiente ⏳   │  │
│ │ [Ver Detalles] [Enviar] [Editar]                    │  │
│ └───────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

#### Tab 3: Precios
```
┌─────────────────────────────────────────────────────────┐
│ 💰 Precios de Compra y Venta                           │
│                                                          │
│ Producto: [Seleccionar producto ▼]                      │
│                                                          │
│ ┌───────────────────────────────────────────────────┐  │
│ │ Taladro Percutor 18V (TAL-1800)                     │  │
│ │                                                   │  │
│ │ Precio de Compra: $950.00                         │  │
│ │ Precio de Venta Local: $1,299.00                   │  │
│ │ Precio de Venta Online: $1,199.00                  │  │
│ │ Margen Local: 36.7%                                │  │
│ │ Margen Online: 26.2%                               │  │
│ │                                                   │  │
│ │ [Actualizar Precios]                               │  │
│ └───────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

#### Tab 4: Stock
```
┌─────────────────────────────────────────────────────────┐
│ 📊 Alertas de Stock                                     │
│                                                          │
│ Productos con stock bajo:                                │
│ ┌───────────────────────────────────────────────────┐  │
│ │ Taladro Percutor 18V                               │  │
│ │ Stock actual: 5  |  Stock mínimo: 10  ⚠️           │  │
│ │ [Reabastecer]                                     │  │
│ └───────────────────────────────────────────────────┘  │
│ ┌───────────────────────────────────────────────────┐  │
│ │ Martillo Profesional                               │  │
│ │ Stock actual: 3  |  Stock mínimo: 15  ⚠️           │  │
│ │ [Reabastecer]                                     │  │
│ └───────────────────────────────────────────────────┘  │
│                                                          │
│ [Generar Orden de Compra Automática]                    │
└─────────────────────────────────────────────────────────┘
```

### Funcionalidades

**Agregar Proveedor**:
```javascript
function addSupplier(name, contact, email, phone) {
    fetch('/api/supply.php?action=add_supplier', {
        method: 'POST',
        body: JSON.stringify({
            name,
            contact,
            email,
            phone
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadSuppliers();
            showNotification('Proveedor agregado');
        }
    });
}
```

**Crear Orden de Compra**:
```javascript
function createPurchaseOrder(supplierId, products) {
    fetch('/api/supply.php?action=create_po', {
        method: 'POST',
        body: JSON.stringify({
            supplierId,
            products
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadPurchaseOrders();
            showNotification('Orden de compra creada');
        }
    });
}
```

**Recibir Orden de Compra**:
```javascript
function receivePurchaseOrder(poId, receivedItems) {
    fetch('/api/supply.php?action=receive', {
        method: 'POST',
        body: JSON.stringify({
            poId,
            receivedItems
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadPurchaseOrders();
            showNotification('Orden recibida');
        }
    });
}
```

**Actualizar Precios**:
```javascript
function updatePrices(productId, purchasePrice, localPrice, onlinePrice) {
    fetch('/api/supply.php?action=update_prices', {
        method: 'POST',
        body: JSON.stringify({
            productId,
            purchasePrice,
            localPrice,
            onlinePrice
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Precios actualizados');
        }
    });
}
```

---

## 17. Reportes Contables (accounting_reports.php)

**URL**: `/accounting_reports.php`

**Propósito**: Generación de reportes contables y financieros para el negocio.

**Requisitos de Acceso**:
- Login requerido
- Rol: admin

### Estructura de la Página

#### Header
```
┌─────────────────────────────────────────────────────────┐
│ [← Regresar]  Reportes Contables  [Cerrar Sesión]      │
└─────────────────────────────────────────────────────────┘
```

#### Filtros de Reporte
```
┌─────────────────────────────────────────────────────────┐
│ Tipo de Reporte: [Ventas ▼]                              │
│ Periodo: [Este mes ▼]                                   │
│ Del: [DD/MM/AAAA]  Al: [DD/MM/AAAA]                     │
│                                                          │
│ [Generar Reporte] [Exportar Excel] [Exportar PDF]        │
└─────────────────────────────────────────────────────────┘
```

#### Tipos de Reporte

**Reporte de Ventas**:
```
┌─────────────────────────────────────────────────────────┐
│ 📊 Reporte de Ventas                                    │
│                                                          │
│ Periodo: 1 Ene 2024 - 31 Ene 2024                        │
│                                                          │
│ Resumen:                                                │
│ • Total de ventas: $125,450.00                          │
│ • Número de transacciones: 156                          │
│ • Ticket promedio: $804.17                              │
│ • Margen promedio: 32.5%                                │
│                                                          │
│ Ventas por Método de Pago:                              │
│ • Efectivo: $45,000.00 (35.9%)                          │
│ • Tarjeta: $62,500.00 (49.8%)                           │
│ • SPEI: $17,950.00 (14.3%)                             │
│                                                          │
│ Ventas por Categoría:                                    │
│ • Herramientas: $65,000.00 (51.9%)                      │
│ • Construcción: $35,000.00 (27.9%)                     │
│ • Electricidad: $25,450.00 (20.3%)                      │
└─────────────────────────────────────────────────────────┘
```

**Reporte de Inventario**:
```
┌─────────────────────────────────────────────────────────┐
│ 📦 Reporte de Inventario                                │
│                                                          │
│ Valor Total de Inventario: $450,000.00                  │
│ Número de SKUs: 1,250                                   │
│                                                          │
│ Productos con Rotación Alta:                             │
│ • Taladro Percutor 18V - 50 unidades/mes                │
│ • Martillo Profesional - 35 unidades/mes                 │
│                                                          │
│ Productos con Rotación Baja:                             │
│ • Sierra Circular - 2 unidades/mes                       │
│ • Nivel Láser - 1 unidad/mes                            │
└─────────────────────────────────────────────────────────┘
```

**Reporte de Ganancias y Pérdidas**:
```
┌─────────────────────────────────────────────────────────┐
│ 💰 Reporte de Ganancias y Pérdidas                      │
│                                                          │
│ Ingresos:                                               │
│ • Ventas: $125,450.00                                   │
│ • Otros ingresos: $2,500.00                              │
│ Total Ingresos: $127,950.00                             │
│                                                          │
│ Gastos:                                                 │
│ • Costo de ventas: $84,750.00                           │
│ • Gastos operativos: $15,000.00                         │
│ • Gastos de personal: $12,000.00                        │
│ Total Gastos: $111,750.00                               │
│                                                          │
│ Utilidad Neta: $16,200.00                               │
│ Margen Neto: 12.7%                                      │
└─────────────────────────────────────────────────────────┘
```

**Reporte de Impuestos**:
```
┌─────────────────────────────────────────────────────────┐
│ 📋 Reporte de Impuestos                                 │
│                                                          │
│ IVA Cobrado: $18,072.00                                 │
│ IVA Pagado: $12,450.00                                  │
│ IVA a Pagar: $5,622.00                                  │
│                                                          │
│ ISR Estimado: $2,430.00                                 │
│                                                          │
│ Total Impuestos a Pagar: $8,052.00                      │
└─────────────────────────────────────────────────────────┘
```

### Funcionalidades

**Generar Reporte**:
```javascript
function generateReport(type, startDate, endDate) {
    fetch('/api/reports.php', {
        method: 'POST',
        body: JSON.stringify({
            type,
            startDate,
            endDate
        })
    })
    .then(response => response.json())
    .then(data => {
        renderReport(data.report);
    });
}
```

**Exportar Excel**:
```javascript
function exportExcel(reportId) {
    window.open(`/api/reports.php?action=export_excel&report_id=${reportId}`, '_blank');
}
```

**Exportar PDF**:
```javascript
function exportPDF(reportId) {
    window.open(`/api/reports.php?action=export_pdf&report_id=${reportId}`, '_blank');
}
```

---

## 18. Egresos/Gastos (gastos.php)

**URL**: `/gastos.php`

**Propósito**: Registro y gestión de egresos y gastos operativos del negocio.

**Requisitos de Acceso**:
- Login requerido
- Rol: admin

### Estructura de la Página

#### Header
```
┌─────────────────────────────────────────────────────────┐
│ [← Regresar]  Egresos/Gastos  [Cerrar Sesión]           │
└─────────────────────────────────────────────────────────┘
```

#### Filtros
```
┌─────────────────────────────────────────────────────────┐
│ Categoría: [Todas ▼]  Fecha: [Este mes ▼]               │
└─────────────────────────────────────────────────────────┘
```

#### Lista de Gastos
```
┌─────────────────────────────────────────────────────────┐
│ [+ Registrar Gasto]                                     │
│                                                          │
│ ┌───────────────────────────────────────────────────┐  │
│ │ Renta de Local                                      │  │
│ │ 15 Ene 2024  |  Operativo  |  $15,000.00          │  │
│ │ [Ver] [Editar] [Eliminar]                           │  │
│ └───────────────────────────────────────────────────┘  │
│ ┌───────────────────────────────────────────────────┐  │
│ │ Servicios de Luz                                    │  │
│ │ 15 Ene 2024  |  Servicios   |  $3,500.00           │  │
│ │ [Ver] [Editar] [Eliminar]                           │  │
│ └───────────────────────────────────────────────────┘  │
│ ┌───────────────────────────────────────────────────┐  │
│ │ Nómina de Empleados                                 │  │
│ │ 15 Ene 2024  |  Personal    |  $25,000.00          │  │
│ │ [Ver] [Editar] [Eliminar]                           │  │
│ └───────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

#### Resumen del Mes
```
┌─────────────────────────────────────────────────────────┐
│ 📊 Resumen de Gastos - Enero 2024                       │
│                                                          │
│ Total de Gastos: $65,500.00                             │
│                                                          │
│ Por Categoría:                                          │
│ • Operativo: $20,000.00 (30.5%)                         │
│ • Servicios: $12,500.00 (19.1%)                         │
│ • Personal: $25,000.00 (38.2%)                         │
│ • Marketing: $5,000.00 (7.6%)                          │
│ • Otros: $3,000.00 (4.6%)                               │
└─────────────────────────────────────────────────────────┘
```

#### Modal de Nuevo Gasto
```
┌─────────────────────────────────────────────────────────┐
│ 💰 Registrar Gasto                           [Cerrar ✕]   │
├─────────────────────────────────────────────────────────┤
│ Descripción: [____________________________________]       │
│                                                          │
│ Categoría: [Seleccionar ▼]                               │
│ • Operativo                                              │
│ • Servicios                                              │
│ • Personal                                               │
│ • Marketing                                             │
│ • Otros                                                  │
│                                                          │
│ Monto: [$___________]                                     │
│                                                          │
│ Fecha: [DD/MM/AAAA]                                      │
│                                                          │
│ Factura (opcional): [Adjuntar archivo]                   │
│                                                          │
│ Notas: [________________________________________]       │
│                                                          │
│ [Registrar Gasto]                                        │
└─────────────────────────────────────────────────────────┘
```

### Funcionalidades

**Registrar Gasto**:
```javascript
function addExpense(description, category, amount, date, invoice, notes) {
    fetch('/api/expenses.php', {
        method: 'POST',
        body: JSON.stringify({
            description,
            category,
            amount,
            date,
            invoice,
            notes
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadExpenses();
            showNotification('Gasto registrado');
        }
    });
}
```

**Editar Gasto**:
```javascript
function editExpense(expenseId, data) {
    fetch('/api/expenses.php?action=update', {
        method: 'POST',
        body: JSON.stringify({
            expenseId,
            ...data
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadExpenses();
            showNotification('Gasto actualizado');
        }
    });
}
```

**Eliminar Gasto**:
```javascript
function deleteExpense(expenseId) {
    if (confirm('¿Estás seguro de eliminar este gasto?')) {
        fetch('/api/expenses.php?action=delete', {
            method: 'POST',
            body: JSON.stringify({ expenseId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadExpenses();
                showNotification('Gasto eliminado');
            }
        });
    }
}
```

---

## 19. Estadísticas (analytics.php)

**URL**: `/analytics.php`

**Propósito**: Dashboard de estadísticas y métricas del negocio.

**Requisitos de Acceso**:
- Login requerido
- Rol: admin

### Estructura de la Página

#### Header
```
┌─────────────────────────────────────────────────────────┐
│ [← Regresar]  Estadísticas  [Cerrar Sesión]             │
└─────────────────────────────────────────────────────────┘
```

#### KPIs Principales
```
┌─────────────────────────────────────────────────────────┐
│ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐   │
│ │  $125K   │ │   156    │ │  $804    │ │  32.5%   │   │
│ │ Ventas   │ │ Pedidos  │ │ Ticket  │ │ Margen   │   │
│ │ este mes │ │ este mes │ │ promedio │ │ promedio │   │
│ └──────────┘ └──────────┘ └──────────┘ └──────────┘   │
└─────────────────────────────────────────────────────────┘
```

#### Gráficos

**Ventas por Mes**:
```
┌─────────────────────────────────────────────────────────┐
│ 📊 Ventas por Mes (Últimos 12 meses)                    │
│                                                          │
│ [Gráfico de líneas con tendencia de ventas]              │
└─────────────────────────────────────────────────────────┘
```

**Ventas por Categoría**:
```
┌─────────────────────────────────────────────────────────┐
│ 🏷️ Ventas por Categoría                                 │
│                                                          │
│ [Gráfico de pastel con distribución por categoría]        │
└─────────────────────────────────────────────────────────┘
```

**Métodos de Pago**:
```
┌─────────────────────────────────────────────────────────┐
│ 💳 Métodos de Pago                                      │
│                                                          │
│ [Gráfico de barras con distribución por método]           │
└─────────────────────────────────────────────────────────┘
```

**Clientes Top**:
```
┌─────────────────────────────────────────────────────────┐
│ 👥 Clientes Top (por gasto)                             │
│                                                          │
│ 1. Constructora ABC - $45,000.00                         │
│ 2. Escuela Secundaria #45 - $32,500.00                  │
│ 3. Juan Pérez - $15,750.00                              │
│ 4. María López - $12,300.00                             │
│ 5. Roberto Sánchez - $10,500.00                         │
└─────────────────────────────────────────────────────────┘
```

**Productos Top**:
```
┌─────────────────────────────────────────────────────────┐
│ 📦 Productos Top (por ventas)                            │
│                                                          │
│ 1. Taladro Percutor 18V - 50 unidades                    │
│ 2. Martillo Profesional - 35 unidades                   │
│ 3. Sierra Circular - 28 unidades                        │
│ 4. Nivel Láser - 25 unidades                           │
│ 5. Juego de Destornilladores - 22 unidades             │
└─────────────────────────────────────────────────────────┘
```

### Funcionalidades

**Cargar Estadísticas**:
```javascript
function loadAnalytics(period) {
    fetch(`/api/analytics.php?period=${period}`)
        .then(response => response.json())
        .then(data => {
            renderKPIs(data.kpis);
            renderCharts(data.charts);
            renderTopClients(data.topClients);
            renderTopProducts(data.topProducts);
        });
}
```

**Filtrar por Periodo**:
```javascript
function filterPeriod(period) {
    loadAnalytics(period);
}
```

**Exportar Reporte**:
```javascript
function exportReport() {
    window.open('/api/analytics.php?action=export', '_blank');
}
```

---

## 20. Analíticas Avanzadas (admin_analytics.php)

**URL**: `/admin_analytics.php`

**Propósito**: Analíticas avanzadas con predicciones y segmentación de clientes.

**Requisitos de Acceso**:
- Login requerido
- Rol: admin

### Estructura de la Página

#### Header
```
┌─────────────────────────────────────────────────────────┐
│ [← Regresar]  Analíticas Avanzadas  [Cerrar Sesión]     │
└─────────────────────────────────────────────────────────┘
```

#### Segmentación de Clientes
```
┌─────────────────────────────────────────────────────────┐
│ 👥 Segmentación de Clientes                             │
│                                                          │
│ ┌───────────────────────────────────────────────────┐  │
│ │ Clientes VIP                                         │  │
│ │ 25 clientes  |  Gasto promedio: $5,000+             │  │
│ │ Representan 40% de las ventas                       │  │
│ │ [Ver Lista] [Enviar Campaña]                         │  │
│ └───────────────────────────────────────────────────┘  │
│ ┌───────────────────────────────────────────────────┐  │
│ │ Clientes Frecuentes                                  │  │
│ │ 150 clientes  |  Gasto promedio: $1,000-$2,000     │  │
│ │ Representan 35% de las ventas                       │  │
│ │ [Ver Lista] [Enviar Campaña]                         │  │
│ └───────────────────────────────────────────────────┘  │
│ ┌───────────────────────────────────────────────────┐  │
│ │ Clientes Ocasionales                                  │  │
│ │ 300 clientes  |  Gasto promedio: $100-$500          │  │
│ │ Representan 20% de las ventas                       │  │
│ │ [Ver Lista] [Enviar Campaña]                         │  │
│ └───────────────────────────────────────────────────┘  │
│ ┌───────────────────────────────────────────────────┐  │
│ │ Clientes Inactivos                                   │  │
│ │ 200 clientes  |  Sin compras en los últimos 90 días│  │
│ │ [Ver Lista] [Enviar Reactivación]                   │  │
│ └───────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────┘
```

#### Predicciones de Ventas
```
┌─────────────────────────────────────────────────────────┐
│ 📈 Predicciones de Ventas                               │
│                                                          │
│ Predicción para el próximo mes:                         │
│ • Ventas estimadas: $135,000.00 (+7.5%)                │
│ • Pedidos estimados: 170                               │
│                                                          │
│ Tendencia: 📈 Crecimiento                               │
│                                                          │
│ [Ver Detalles]                                           │
└─────────────────────────────────────────────────────────┘
```

#### Análisis de Abandono de Carrito
```
┌─────────────────────────────────────────────────────────┐
│ 🛒 Análisis de Abandono de Carrito                      │
│                                                          │
│ Tasa de abandono: 65%                                   │
│                                                          │
│ Razones principales:                                     │
│ • Costo de envío muy alto (35%)                         │
│ • Proceso de checkout largo (25%)                        │
│ • Falta de métodos de pago (20%)                        │
│ • Otros (20%)                                           │
│                                                          │
│ [Ver Detalles] [Implementar Mejoras]                    │
└─────────────────────────────────────────────────────────┘
```

#### Análisis de Cohorte
```
┌─────────────────────────────────────────────────────────┐
│ 📊 Análisis de Cohorte                                   │
│                                                          │
│ Retención de clientes por mes de adquisición:            │
│                                                          │
│ [Tabla de retención con cohortes mensuales]              │
└─────────────────────────────────────────────────────────┘
```

### Funcionalidades

**Segmentar Clientes**:
```javascript
function segmentClients(criteria) {
    fetch('/api/admin_analytics_api.php?action=segment', {
        method: 'POST',
        body: JSON.stringify({ criteria })
    })
    .then(response => response.json())
    .then(data => {
        renderSegments(data.segments);
    });
}
```

**Predecir Ventas**:
```javascript
function predictSales(period) {
    fetch(`/api/admin_analytics_api.php?action=predict&period=${period}`)
        .then(response => response.json())
        .then(data => {
            renderPrediction(data.prediction);
        });
}
```

**Analizar Abandono**:
```javascript
function analyzeCartAbandonment() {
    fetch('/api/admin_analytics_api.php?action=cart_abandonment')
        .then(response => response.json())
        .then(data => {
            renderAbandonmentAnalysis(data.analysis);
        });
}
```

---

## PARTE 5: APIs DEL SISTEMA

---

## 21. API de Autenticación

**Endpoint**: `/api/auth.php`

**Propósito**: Gestión de autenticación de usuarios.

### Acciones

**Login**:
```
POST /api/auth.php?action=login
Body: { email, password }
Response: { success: true, user: {...}, token: "..." }
```

**Logout**:
```
POST /api/auth.php?action=logout
Response: { success: true }
```

**Registro**:
```
POST /api/auth.php?action=register
Body: { name, email, password, phone }
Response: { success: true, user_id: 123 }
```

**Recuperar Contraseña**:
```
POST /api/auth.php?action=forgot_password
Body: { email }
Response: { success: true, message: "Email enviado" }
```

**Resetear Contraseña**:
```
POST /api/auth.php?action=reset_password
Body: { token, new_password }
Response: { success: true }
```

---

## 22. API de Carrito

**Endpoint**: `/api/cart.php`

**Propósito**: Gestión del carrito de compras.

### Acciones

**Obtener Carrito**:
```
GET /api/cart.php
Response: { success: true, cart: [...], total: 1234.56 }
```

**Agregar al Carrito**:
```
POST /api/cart.php
Body: { product_id, quantity }
Response: { success: true, cart: [...] }
```

**Actualizar Cantidad**:
```
PUT /api/cart.php
Body: { product_id, quantity }
Response: { success: true, cart: [...] }
```

**Eliminar del Carrito**:
```
DELETE /api/cart.php?product_id=123
Response: { success: true, cart: [...] }
```

**Vaciar Carrito**:
```
DELETE /api/cart.php?action=clear
Response: { success: true }
```

**Aplicar Cupón**:
```
POST /api/cart.php?action=apply_coupon
Body: { coupon_code }
Response: { success: true, discount: 100.00 }
```

---

## 23. API de Checkout

**Endpoint**: `/api/checkout.php`

**Propósito**: Procesamiento de checkout y pagos.

### Acciones

**Validar Carrito**:
```
GET /api/checkout.php?action=validate
Response: { success: true, valid: true, total: 1234.56 }
```

**Crear Orden**:
```
POST /api/checkout.php?action=create_order
Body: { 
    contact: {...}, 
    shipping: {...}, 
    payment_method: "...",
    fiscal_data: {...}
}
Response: { success: true, order_id: 123 }
```

**Procesar Pago**:
```
POST /api/checkout.php?action=process_payment
Body: { order_id, payment_method, payment_data }
Response: { success: true, payment_id: "..." }
```

**Calcular Envío**:
```
POST /api/checkout.php?action=calculate_shipping
Body: { shipping_method, address }
Response: { success: true, shipping_cost: 150.00 }
```

**Validar Cupón**:
```
POST /api/checkout.php?action=validate_coupon
Body: { coupon_code }
Response: { success: true, valid: true, discount: 100.00 }
```

---

## 24. API de Pedidos

**Endpoint**: `/api/orders.php`

**Propósito**: Gestión de pedidos.

### Acciones

**Obtener Pedidos del Usuario**:
```
GET /api/orders.php?action=user_orders
Response: { success: true, orders: [...] }
```

**Obtener Pedido por ID**:
```
GET /api/orders.php?action=get_order&order_id=123
Response: { success: true, order: {...} }
```

**Crear Pedido Manual**:
```
POST /api/orders.php?action=create
Body: { customer_id, products, payment_method }
Response: { success: true, order_id: 123 }
```

**Actualizar Estado**:
```
PUT /api/orders.php?action=update_status
Body: { order_id, status }
Response: { success: true }
```

**Cancelar Pedido**:
```
POST /api/orders.php?action=cancel
Body: { order_id, reason }
Response: { success: true }
```

**Buscar Pedidos**:
```
GET /api/orders.php?action=search&q=juan
Response: { success: true, orders: [...] }
```

---

## 25. API de Productos

**Endpoint**: `/api/products.php`

**Propósito**: Gestión de productos.

### Acciones

**Obtener Todos los Productos**:
```
GET /api/products.php
Response: { success: true, products: [...] }
```

**Obtener Producto por ID**:
```
GET /api/products.php?action=get&id=123
Response: { success: true, product: {...} }
```

**Buscar Productos**:
```
GET /api/products.php?action=search&q=taladro
Response: { success: true, products: [...] }
```

**Obtener por Categoría**:
```
GET /api/products.php?action=by_category&category=herramientas
Response: { success: true, products: [...] }
```

**Crear Producto**:
```
POST /api/products.php
Body: { name, sku, price, category, ... }
Response: { success: true, product_id: 123 }
```

**Actualizar Producto**:
```
PUT /api/products.php
Body: { id, name, price, ... }
Response: { success: true }
```

**Eliminar Producto**:
```
DELETE /api/products.php?id=123
Response: { success: true }
```

**Actualizar Stock**:
```
POST /api/products.php?action=update_stock
Body: { product_id, quantity }
Response: { success: true }
```

---

## 26. API de Inventario

**Endpoint**: `/api/inventory.php`

**Propósito**: Gestión de inventario y stock.

### Acciones

**Obtener Stock**:
```
GET /api/inventory.php?action=stock&product_id=123
Response: { success: true, stock: 50 }
```

**Obtener Alertas de Stock**:
```
GET /api/inventory.php?action=alerts
Response: { success: true, alerts: [...] }
```

**Reservar Stock**:
```
POST /api/inventory.php?action=reserve
Body: { product_id, quantity, order_id }
Response: { success: true }
```

**Liberar Reserva**:
```
POST /api/inventory.php?action=release
Body: { reservation_id }
Response: { success: true }
```

**Ajustar Stock**:
```
POST /api/inventory.php?action=adjust
Body: { product_id, quantity, reason }
Response: { success: true }
```

**Obtener Movimientos de Kardex**:
```
GET /api/inventory.php?action=kardex&product_id=123
Response: { success: true, movements: [...] }
```

---

## 27. API de Clientes

**Endpoint**: `/api/admin_clients.php`

**Propósito**: Gestión de clientes.

### Acciones

**Obtener Todos los Clientes**:
```
GET /api/admin_clients.php
Response: { success: true, clients: [...] }
```

**Buscar Cliente**:
```
GET /api/admin_clients.php?action=search&q=juan
Response: { success: true, clients: [...] }
```

**Obtener Cliente por ID**:
```
GET /api/admin_clients.php?action=get&id=123
Response: { success: true, client: {...} }
```

**Crear Cliente**:
```
POST /api/admin_clients.php
Body: { name, email, phone, ... }
Response: { success: true, client_id: 123 }
```

**Actualizar Cliente**:
```
PUT /api/admin_clients.php
Body: { id, name, email, ... }
Response: { success: true }
```

**Eliminar Cliente**:
```
DELETE /api/admin_clients.php?id=123
Response: { success: true }
```

**Obtener Historial de Compras**:
```
GET /api/admin_clients.php?action=purchase_history&client_id=123
Response: { success: true, orders: [...] }
```

---

## 28. API de Facturación

**Endpoint**: `/api/admin_online_billing_api.php`

**Propósito**: Gestión de facturación CFDI 4.0.

### Acciones

**Emitir Factura**:
```
POST /api/admin_online_billing_api.php?action=issue_invoice
Body: { order_id, customer_rfc, cfdi_use, ... }
Response: { success: true, invoice_id: "...", pdf_url: "..." }
```

**Cancelar Factura**:
```
POST /api/admin_online_billing_api.php?action=cancel_sat_invoice
Body: { order_id, sat_reason, notes }
Response: { success: true }
```

**Obtener Factura**:
```
GET /api/admin_online_billing_api.php?action=get_invoice&invoice_id=...
Response: { success: true, invoice: {...} }
```

**Obtener Facturas**:
```
GET /api/admin_online_billing_api.php?action=get_invoices
Response: { success: true, invoices: [...] }
```

**Descargar PDF**:
```
GET /api/admin_online_billing_api.php?action=download_pdf&invoice_id=...
Response: PDF file
```

**Descargar XML**:
```
GET /api/admin_online_billing_api.php?action=download_xml&invoice_id=...
Response: XML file
```

---

## 29. API de Pagos

**Endpoint**: `/api/admin_payment_config.php`

**Propósito**: Gestión de configuración de pagos.

### Acciones

**Obtener Bancos**:
```
GET /api/admin_payment_config.php?action=get_banks
Response: { success: true, banks: [...] }
```

**Agregar Banco**:
```
POST /api/admin_payment_config.php?action=add_bank
Body: { bank_name, bank_code, clabe, ... }
Response: { success: true, bank_id: 123 }
```

**Actualizar Banco**:
```
POST /api/admin_payment_config.php?action=update_bank&bank_id=123
Body: { bank_name, clabe, ... }
Response: { success: true }
```

**Eliminar Banco**:
```
POST /api/admin_payment_config.php?action=delete_bank&bank_id=123
Response: { success: true }
```

**Obtener Métodos de Pago**:
```
GET /api/admin_payment_config.php?action=get_payment_methods
Response: { success: true, methods: [...] }
```

**Calcular Comisión**:
```
GET /api/admin_payment_config.php?action=calculate_fee&amount=1000&method_code=card
Response: { success: true, fee: 35.50 }
```

**Obtener Configuración SAT**:
```
GET /api/admin_payment_config.php?action=get_sat_config
Response: { success: true, config: {...} }
```

**Actualizar Configuración SAT**:
```
POST /api/admin_payment_config.php?action=update_sat_config
Body: { company_rfc, company_tax_name, ... }
Response: { success: true }
```

---

## 30. API de Reportes

**Endpoint**: `/api/reports.php`

**Propósito**: Generación de reportes.

### Acciones

**Generar Reporte de Ventas**:
```
POST /api/reports.php
Body: { type: "sales", start_date, end_date }
Response: { success: true, report: {...} }
```

**Generar Reporte de Inventario**:
```
POST /api/reports.php
Body: { type: "inventory" }
Response: { success: true, report: {...} }
```

**Generar Reporte de Ganancias**:
```
POST /api/reports.php
Body: { type: "pnl", start_date, end_date }
Response: { success: true, report: {...} }
```

**Exportar Excel**:
```
GET /api/reports.php?action=export_excel&report_id=123
Response: Excel file
```

**Exportar PDF**:
```
GET /api/reports.php?action=export_pdf&report_id=123
Response: PDF file
```

**Exportar CSV**:
```
GET /api/reports.php?action=export_csv&report_id=123
Response: CSV file
```

---

## Conclusión

Este documento proporciona una descripción detallada de todas las interfaces y componentes del sistema Ferretería FOX / Truper Platform. Cada sección incluye:

- **Propósito** de la interfaz
- **Requisitos de acceso** (roles, autenticación)
- **Estructura visual** de la página
- **Funcionalidades principales** con ejemplos de código
- **Flujos de trabajo** paso a paso
- **Integración con APIs** correspondientes

El sistema está diseñado para ser modular, escalable y fácil de mantener, con separación clara entre:
- Interfaces del cliente (tienda, checkout, cuenta)
- Interfaces de administración - tienda (pedidos, facturación, logística)
- Interfaces de administración - local (caja, tickets, tareas)
- Interfaces de administración - solo admin (abastecimiento, reportes, analíticas)
- APIs del sistema para comunicación entre componentes

**Documento Versión**: 1.0  
**Fecha de Creación**: 2026  
**Última Actualización**: 2026
