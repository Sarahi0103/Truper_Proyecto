# 🎟️ Integración de Tickets con Carrito y Órdenes

## ¿Cómo Funciona?

El sistema de tickets está completamente integrado con el flujo de compra:

```
Cliente compra en carrito
    ↓
Se crea Order en BD
    ↓
Se dispara hook onOrderCompleted()
    ↓
Se crea Ticket automáticamente
    ↓
Ticket se vincula con Order
    ↓
Cliente recibe comprobante
```

## ✅ Todo se Guarda Automáticamente

**Cuando finaliza una compra:**
- ✅ Ticket creado con folio único (YYYYMM-XXXXX)
- ✅ Items de la orden vinculados al ticket
- ✅ Totales, impuestos y descuentos calculados
- ✅ Registrado en auditoría
- ✅ Visible en historial del cliente

**Cuando se envía por WhatsApp:**
- ✅ Envío registrado con teléfono
- ✅ Fecha/hora del envío guardada
- ✅ Evento registrado en historial
- ✅ Cliente puede ver que recibió el comprobante

**Cuando se descarga PDF:**
- ✅ Descarga registrada
- ✅ Historial actualizado
- ✅ Auditoría completa

---

## 🔌 Cómo Integrar en tu Código

### 1. En tu archivo de finalización de compra (ej: `api/orders.php`)

```php
<?php
require_once '../backend/hooks/ticket_hooks.php';

// Cuando se completa la orden...
$orderId = $order['id']; // ID de la orden creada
$orderData = [
    'subtotal' => $order['subtotal'],
    'tax' => $order['tax_amount'],
    'discount' => $order['discount_amount'],
    'total' => $order['total_amount']
];

// Esto dispara la creación automática de ticket
$ticketResult = onOrderCompleted($orderId, $orderData);

if ($ticketResult['success']) {
    // El ticket fue creado automáticamente
    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'ticket_folio' => $ticketResult['folio'],
        'message' => 'Compra completada. Comprobante: ' . $ticketResult['folio']
    ]);
}
?>
```

### 2. Para enviar por WhatsApp

```php
<?php
require_once '../backend/hooks/ticket_hooks.php';

$ticketId = $_POST['ticket_id'];
$phoneNumber = $_POST['phone'];

$result = sendTicketViaWhatsApp($ticketId, $phoneNumber);

if ($result['success']) {
    echo json_encode([
        'success' => true,
        'message' => 'Comprobante enviado por WhatsApp'
    ]);
}
?>
```

### 3. Para rastrear descargas

```php
<?php
require_once '../backend/hooks/ticket_hooks.php';

$ticketId = $_GET['ticket_id'];

// Registrar la descarga
trackTicketDownload($ticketId);

// Generar/descargar PDF
// ... tu código de PDF ...
?>
```

---

## 📍 Endpoints Disponibles para Clientes

### Ver Mis Tickets
```
GET /api/client_tickets.php?action=list&page=1&per_page=10
```

**Respuesta:**
```json
{
    "success": true,
    "tickets": [
        {
            "id": 1,
            "folio": "202604-00001",
            "ticket_type": "sale",
            "total_amount": "1090.00",
            "payment_status": "completed",
            "issued_date": "2026-04-19",
            "item_count": 2,
            "whatsapp_sends": 1,
            "downloads": 1
        }
    ],
    "pagination": {...}
}
```

### Ver Detalles de un Ticket
```
GET /api/client_tickets.php?action=get&ticket_id=1
```

### Ver Historial de un Ticket
```
GET /api/client_tickets.php?action=history&ticket_id=1
```

**Respuesta:**
```json
{
    "success": true,
    "history": [
        {
            "type": "auto_created_from_order",
            "action": "create",
            "description": "Ticket generado automáticamente...",
            "timestamp": "2026-04-19 10:30:00"
        },
        {
            "type": "whatsapp",
            "action": "sent_whatsapp",
            "description": "Enviado a: +56912345678",
            "timestamp": "2026-04-19 10:35:00"
        },
        {
            "type": "download",
            "action": "downloaded",
            "description": "pdf",
            "timestamp": "2026-04-19 10:40:00"
        }
    ]
}
```

### Enviar por WhatsApp
```
POST /api/client_tickets.php?action=send-whatsapp

{
    "ticket_id": 1,
    "phone_number": "+56912345678"
}
```

### Descargar PDF
```
GET /api/client_tickets.php?action=download-pdf&ticket_id=1
```

### Ver Estadísticas
```
GET /api/client_tickets.php?action=stats
```

---

## 🔄 Flujo Completo Ejemplo

### Escenario: Cliente realiza compra

1. **Cliente agrega items al carrito**
   - Carrito local: 2 items por $1000

2. **Cliente finaliza compra (checkout)**
   - Sistema crea Order en BD
   - Se ejecuta `onOrderCompleted($orderId)`

3. **Sistema crea Ticket automáticamente**
   - Folio: `202604-00001` ✨
   - Se vinculan todos los items
   - Se calcula: subtotal, impuesto, descuento, total

4. **Cliente recibe confirmación**
   ```
   "¡Compra exitosa!"
   "Tu comprobante: 202604-00001"
   "Ver en: https://truper.com/public/my_tickets.php"
   ```

5. **Cliente envía por WhatsApp**
   - Click "💬 Enviar por WhatsApp"
   - Ingresa número de teléfono
   - Se ejecuta `sendTicketViaWhatsApp()`
   - **Evento registrado automáticamente**
   - Abre WhatsApp con enlace

6. **Cliente descarga PDF**
   - Click "📥 Descargar PDF"
   - Se ejecuta `trackTicketDownload()`
   - **Descarga registrada en historial**

7. **Admin ve todo en historial**
   ```
   Panel Admin → Tickets
   Folio 202604-00001:
   - ✅ Creado desde orden #5
   - ✅ Enviado por WhatsApp a +56912345678 (10:35)
   - ✅ Descargado en PDF (10:40)
   ```

---

## 📊 Base de Datos - Tablas Creadas

### Principales
- `sales_tickets` - Tickets principales
- `ticket_items` - Items por ticket
- `ticket_audit_log` - Auditoría

### Tracking
- `ticket_whatsapp_sends` - Envíos por WhatsApp
- `ticket_downloads` - Descargas de PDF
- `ticket_generations` - Generaciones de comprobantes

### Índices para Performance
- `idx_whatsapp_ticket`
- `idx_downloads_ticket`
- `idx_generations_ticket`

---

## 🎯 Checklist de Implementación

- [ ] Crear tablas de tracking (ejecutar `/public/migrate.php`)
- [ ] Incluir `ticket_hooks.php` en tu código de órdenes
- [ ] Llamar `onOrderCompleted()` después de crear Order
- [ ] Probar flujo completo de compra
- [ ] Acceder a `/public/my_tickets.php` como cliente
- [ ] Verificar historial del admin en `/public/tickets.php`
- [ ] Probar envío por WhatsApp
- [ ] Verificar que todo se registra en auditoría

---

## 🔍 Verificar Registros

### Ver tickets de un cliente
```sql
SELECT * FROM sales_tickets WHERE user_id = 123;
```

### Ver envíos por WhatsApp
```sql
SELECT * FROM ticket_whatsapp_sends;
```

### Ver descargas
```sql
SELECT * FROM ticket_downloads;
```

### Ver auditoría completa
```sql
SELECT * FROM ticket_audit_log ORDER BY created_at DESC;
```

---

## ⚙️ Variables de Configuración

Están definidas en `backend/models/TicketIntegration.php`:

```php
// Auto-crear ticket después de orden: true
// Registrar envíos: true
// Registrar descargas: true
// Soft delete tickets: true
```

---

## 🆘 Troubleshooting

**P: "Tabla no existe"**
→ Ejecutar `/public/migrate.php`

**P: "Ticket no se crea automáticamente"**
→ Verificar que `onOrderCompleted()` se llama después de crear Order
→ Ver logs en `/logs/`

**P: "WhatsApp no se registra"**
→ Verificar que `sendTicketViaWhatsApp()` se llama
→ Revisar permisos de BD

**P: "Historial vacío"**
→ Los eventos se registran automáticamente
→ Verificar tabla `ticket_audit_log`

---

## 📞 Soporte

Para dudas sobre la integración:
1. Revisar esta documentación
2. Ver ejemplos en `/backend/hooks/ticket_hooks.php`
3. Revisar tablas creadas en BD
4. Contactar al equipo de desarrollo
