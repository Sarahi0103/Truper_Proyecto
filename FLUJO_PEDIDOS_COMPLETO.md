# Flujo Completo del Sistema de Pedidos - Truper Platform

## Resumen del Flujo

Este documento explica el proceso completo desde que un usuario agrega productos al carrito hasta que puede rastrear su pedido.

---

## 1. Flujo del Carrito al Pedido

### 1.1 Agregar Productos al Carrito

**Frontend** (`public/js/catalog.js`):
```javascript
async function addToCart(product) {
    // 1. Agregar a localStorage inmediatamente (UX rápida)
    cart.push({
        id: product.id,
        sku: product.sku,
        name: product.name,
        quantity: 1,
        unit_price: product.unit_price
    });
    
    // 2. Sincronizar con servidor
    await fetch('/api/cart.php?action=add', {
        method: 'POST',
        body: JSON.stringify({
            product_id: product.id,
            quantity: 1,
            price: product.unit_price
        })
    });
}
```

**Backend** (`public/api/cart.php`):
```php
// 1. Validar stock disponible
$availableStock = getAvailableStock($productId);
if ($availableStock < $quantity) {
    return error('Stock insuficiente');
}

// 2. Crear reserva de stock temporal (15 minutos)
$reservation = $stockService->createReservation($productId, $quantity, $userId, $sessionId, 15);

// 3. Guardar en base de datos
INSERT INTO shopping_carts (user_id, session_id, product_id, quantity, price, reservation_id)
VALUES (?, ?, ?, ?, ?, ?);
```

### 1.2 Proceso de Checkout

**Frontend** (`public/checkout.php`):
```javascript
// 1. Leer carrito del localStorage
const cartItems = JSON.parse(localStorage.getItem('fox_cart') || '[]');

// 2. Enviar al servidor
fetch('/api/checkout.php', {
    method: 'POST',
    body: JSON.stringify({
        cartItems: cartItems,
        firstName, lastName, email, phone,
        address, city, postalCode,
        paymentMethod, shippingMethod
    })
});
```

**Backend** (`public/api/checkout.php`):
```php
// 1. Validar CSRF y datos requeridos
require_csrf_token();

// 2. Crear reservas de stock para todos los items
$reservations = $stockService->createCartReservations($cartItems, $userId, $sessionId);

// 3. Validar precios desde servidor (no del cliente)
foreach ($cartItems as $item) {
    $product = getProductFromDB($item['id']);
    $serverPrice = $product['unit_price'];
    // Validar stock disponible
    $availableStock = $stockService->getAvailableStock($productId);
}

// 4. Crear orden en tabla `orders`
INSERT INTO orders (client_id, order_number, total_amount, subtotal_amount, tax_amount, 
                    payment_status, order_date, delivery_date, notes, status)
VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, 'pending');

// 5. Crear items de orden
INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal, line_total)
VALUES (?, ?, ?, ?, ?, ?);

// 6. Confirmar reservas de stock (deducir stock real)
$stockService->confirmCartReservations($reservations, $orderId);

// 7. Procesar pago según método
switch ($paymentMethod) {
    case 'credit_card':
        $result = $paymentGateway->processStripePayment($total, $paymentMethodId, $orderData);
        break;
    case 'mercadopago':
        $result = $paymentGateway->processMercadoPagoPayment($total, $input, $orderData);
        break;
    case 'bank_transfer':
        $result = $paymentGateway->processSPEIPayment($total, $orderData);
        break;
    case 'on_delivery':
        $result = $paymentGateway->processCashOnDelivery($total, $orderData);
        break;
}

// 8. Registrar pago
INSERT INTO payments (order_id, amount, payment_method, payment_status, transaction_id, payment_date)
VALUES (?, ?, ?, ?, ?, NOW());

// 9. Crear ticket de venta (sales_tickets)
INSERT INTO sales_tickets (order_id, folio, customer_name, total_amount, issued_date, order_status)
VALUES (?, ?, ?, ?, NOW(), 'pending');

// 10. Enviar notificaciones
$emailService->sendOrderConfirmation($userId, $orderId);
$whatsappService->sendOrderNotification($userId, $orderId);

// 11. Limpiar carrito
localStorage.removeItem('fox_cart');
```

---

## 2. Gestión de Pedidos - Panel de Administrador

### 2.1 Lista de Pedidos

**Frontend** (`public/admin_online_orders.php`):
- Muestra tabla con todos los pedidos en línea
- Filtros por estado, fecha, búsqueda
- Estadísticas KPI (pedidos hoy, pendientes, ventas del mes)

**Backend** (`public/api/admin_online_orders_api.php`):
```php
// Listar pedidos
SELECT id, folio, customer_name, total_amount, issued_date, order_status, payment_status
FROM sales_tickets
WHERE 1=1
ORDER BY issued_date DESC
LIMIT 100;
```

### 2.2 Actualizar Estado de Pedido

**Backend** (`public/api/admin_online_orders_api.php`):
```php
case 'update_status':
    $orderId = $_POST['order_id'];
    $newStatus = $_POST['status'];
    
    // Actualizar estado en sales_tickets
    UPDATE sales_tickets 
    SET order_status = ?, updated_at = NOW()
    WHERE id = ?;
    
    // Si se envía, crear tracking
    if ($newStatus === 'shipped') {
        $trackingData = [
            'order_id' => $orderId,
            'carrier' => $_POST['carrier'],
            'tracking_number' => $_POST['tracking_number'],
            'shipping_date' => NOW(),
            'estimated_delivery' => $_POST['estimated_delivery']
        ];
        $shippingService->createTracking($trackingData);
    }
    
    // Registrar en log de auditoría
    log_action($_SESSION['user_id'], 'UPDATE_ORDER_STATUS', "Orden {$orderId} cambiada a {$newStatus}");
    break;
```

### 2.3 Agregar Tracking de Envío

**Backend** (`src/Services/ShippingTrackingService.php`):
```php
public function createTracking($trackingData) {
    INSERT INTO shipping_tracking 
    (order_id, carrier, tracking_number, shipping_date, estimated_delivery, shipping_address)
    VALUES (?, ?, ?, ?, ?, ?);
    
    // Agregar evento inicial
    add_tracking_event($trackingId, 'created', 'Pedido enviado desde almacén', 'Almacén Central');
}
```

---

## 3. Seguimiento de Pedidos - Interfaz de Usuario

### 3.1 Búsqueda de Pedido por Folio

**Frontend** (`public/order_tracking.php`):
```javascript
async function loadTrackingOrders() {
    const search = document.getElementById('searchInput').value;
    
    // Buscar por folio
    const res = await fetch(`/api/admin_supply.php?action=order-tracking-list&search=${search}`);
    
    // Mostrar resultados en tabla
    renderOrdersTable(res.orders);
}
```

**Backend** (`public/api/admin_supply.php`):
```php
case 'order-tracking-list':
    $search = $_GET['search'] ?? '';
    
    // Buscar por folio o ticket
    SELECT st.id, st.folio, st.customer_name, st.total_amount, st.issued_date, 
           st.order_status, st.payment_status, st.shipping_address_json
    FROM sales_tickets st
    WHERE st.folio ILIKE ? OR st.id::text ILIKE ?
    ORDER BY st.issued_date DESC;
    
    // Incluir tracking si existe
    LEFT JOIN shipping_tracking tr ON tr.order_id = st.id
```

### 3.2 Visualización de Tracking

**Frontend** (`public/order_tracking.php`):
```javascript
function showShippingTracking(orderId) {
    // Obtener tracking del servidor
    const tracking = await fetch(`/api/shipping_tracking.php?action=get&order_id=${orderId}`);
    
    // Mostrar modal con timeline de eventos
    renderTrackingTimeline(tracking.events);
}

function renderTrackingTimeline(events) {
    // Mostrar línea de tiempo con eventos:
    // - Pedido creado
    // - En preparación
    // - Enviado
    // - En tránsito
    // - Entregado
}
```

---

## 4. Estados del Pedido

| Estado | Descripción | Acciones del Admin |
|--------|-------------|-------------------|
| `pending` | Pedido recibido, pendiente de confirmación | Confirmar, cancelar |
| `confirmed` | Pago confirmado, en preparación | Iniciar preparación |
| `processing` | En preparación en almacén | Marcar como empacado |
| `packed` | Empacado, listo para envío | Generar tracking |
| `shipped` | Enviado con número de tracking | Actualizar tracking |
| `in_transit` | En tránsito hacia destino | Monitorear |
| `delivered` | Entregado al cliente | Cerrar pedido |
| `cancelled` | Pedido cancelado | Reembolsar si aplica |

---

## 5. Integración de Tracking

### 5.1 Creación de Tracking

Cuando el administrador marca un pedido como "enviado":
```php
// 1. Actualizar estado del pedido
UPDATE sales_tickets SET order_status = 'shipped' WHERE id = ?;

// 2. Crear registro de tracking
INSERT INTO shipping_tracking (order_id, carrier, tracking_number, shipping_date, estimated_delivery)
VALUES (?, ?, ?, NOW(), ?);

// 3. Agregar evento inicial
add_tracking_event($trackingId, 'shipped', 'Pedido enviado desde almacén', 'Almacén Central');
```

### 5.2 Actualización de Tracking (Webhook)

Las paqueterías envían webhooks para actualizar el tracking:
```php
// Webhook de paquetería
POST /api/shipping_webhook.php

// Procesar evento
$shippingService->updateTrackingStatus($trackingId, $status, $description, $location);

// Actualizar estado del pedido si es delivered
if ($status === 'delivered') {
    UPDATE sales_tickets SET order_status = 'delivered' WHERE order_id = ?;
}
```

### 5.3 Consulta de Tracking por Usuario

El usuario puede consultar su tracking:
```javascript
// Por folio
GET /order_tracking.php?search=FOLIO-123

// Por número de orden
GET /order_tracking.php?order_id=123

// Respuesta JSON con timeline de eventos
{
    "carrier": "FedEx",
    "tracking_number": "1234567890",
    "estimated_delivery": "2026-09-02",
    "events": [
        {"status": "created", "date": "2026-08-30 10:00", "description": "Pedido recibido", "location": "Almacén"},
        {"status": "shipped", "date": "2026-08-30 14:00", "description": "Enviado", "location": "Almacén"},
        {"status": "in_transit", "date": "2026-08-31 09:00", "description": "En tránsito", "location": "Centro de distribución"}
    ]
}
```

---

## 6. Mejoras de UI/UX Necesarias

### 6.1 Panel de Administrador
- ✅ Tabla de pedidos con filtros
- ✅ KPIs de estadísticas
- ⚠️ **Falta**: Botón para agregar tracking directamente desde la tabla
- ⚠️ **Falta**: Modal para actualizar estado con tracking

### 6.2 Interfaz de Seguimiento
- ✅ Búsqueda por folio
- ✅ Tabla de resultados
- ⚠️ **Falta**: Timeline visual de tracking
- ⚠️ **Falta**: Mapa de ubicación (opcional)
- ⚠️ **Falta**: Notificaciones push de actualizaciones

### 6.3 Experiencia del Usuario
- ✅ Confirmación de pedido
- ✅ Email de confirmación
- ⚠️ **Falta**: Página de confirmación con tracking inicial
- ⚠️ **Falta**: Enlace directo de tracking en email
- ⚠️ **Falta**: SMS con número de tracking

---

## 7. Archivos del Sistema

### Frontend
- `public/cart.php` - Página del carrito
- `public/checkout.php` - Página de checkout
- `public/order_confirmation.php` - Confirmación de pedido
- `public/order_tracking.php` - Seguimiento de pedidos
- `public/order_history.php` - Historial de compras
- `public/admin_online_orders.php` - Panel admin de pedidos online

### Backend API
- `public/api/cart.php` - API del carrito
- `public/api/checkout.php` - API de checkout
- `public/api/orders.php` - API de pedidos
- `public/api/admin_online_orders_api.php` - API admin de pedidos
- `public/api/admin_supply.php` - API de logística
- `public/api/shipping_tracking.php` - API de tracking (por crear)

### Servicios
- `src/Services/StockReservationService.php` - Reservas de stock
- `src/Services/PaymentGatewayService.php` - Procesamiento de pagos
- `src/Services/ShippingTrackingService.php` - Tracking de envíos
- `src/Services/EmailService.php` - Notificaciones email
- `src/Services/WhatsAppService.php` - Notificaciones WhatsApp

### Base de Datos
- `shopping_carts` - Carritos persistentes
- `stock_reservations` - Reservas temporales de stock
- `orders` - Órdenes de compra
- `order_items` - Items de órdenes
- `payments` - Registros de pagos
- `sales_tickets` - Tickets de venta (folio)
- `shipping_tracking` - Tracking de envíos
- `tracking_events` - Eventos de tracking

---

## 8. Checklist de Funcionalidades

### Carrito
- [x] Agregar productos con validación de stock
- [x] Actualizar cantidades
- [x] Eliminar items
- [x] Reserva de stock temporal (15 min)
- [x] Persistencia en base de datos
- [x] Sincronización localStorage ↔ servidor

### Checkout
- [x] Lectura automática del carrito
- [x] Validación de stock
- [x] Procesamiento de pagos (Stripe, Mercado Pago, SPEI, Efectivo)
- [x] Creación de orden
- [x] Confirmación de reservas
- [x] Generación de folio
- [x] Notificaciones

### Panel de Administrador
- [x] Lista de pedidos
- [x] Filtros y búsqueda
- [x] Estadísticas KPI
- [x] Actualización de estado
- [ ] Agregar tracking desde panel
- [ ] Modal de actualización de estado

### Seguimiento de Pedidos
- [x] Búsqueda por folio
- [x] Tabla de resultados
- [x] Log de auditoría
- [ ] Timeline visual de tracking
- [ ] Mapa de ubicación
- [ ] Notificaciones push

### Notificaciones
- [x] Email de confirmación
- [x] WhatsApp (si está configurado)
- [ ] SMS con tracking
- [ ] Push notifications

---

## 9. Próximas Mejoras

1. **API de Tracking** - Crear `public/api/shipping_tracking.php`
2. **Modal de Tracking** - Agregar modal en admin para agregar tracking
3. **Timeline Visual** - Implementar timeline en `order_tracking.php`
4. **Página de Confirmación** - Mejorar `order_confirmation.php` con tracking inicial
5. **Notificaciones Push** - Implementar sistema de notificaciones en tiempo real
6. **Integración con Paqueterías** - Webhooks automáticos de FedEx, DHL, etc.
