# 🎟️ Sistema de Gestión de Tickets de Truper Platform

## Descripción General

El Sistema de Gestión de Tickets es un módulo administrativo completo para rastrear, auditar y gestionar todas las transacciones de ventas en Truper Platform. Proporciona:

- **Folios únicos** con formato `YYYYMM-XXXXX`
- **Auditoría completa** de cambios
- **Estadísticas mensuales** automáticas
- **Archivamiento** de tickets antiguos
- **Exportación** a CSV/PDF
- **Verificación de autenticidad** para clientes

## Estructura de Base de Datos

### Tabla: `sales_tickets`
```sql
- id: identificador único
- folio: YYYYMM-XXXXX (unique, indexed)
- order_id: referencia a orden (FK)
- user_id: cliente (FK)
- ticket_type: 'sale', 'return', 'adjustment', 'credit'
- subtotal_amount, tax_amount, discount_amount, total_amount
- payment_method: 'cash', 'card', 'transfer', 'credit', 'check'
- payment_status: 'pending', 'completed', 'failed', 'refunded'
- issued_by: usuario admin (FK)
- issued_date: timestamp
- notes: texto libre
- archived_at: NULL o timestamp
- deleted_at: soft delete
```

### Tabla: `ticket_items`
```sql
- id: PK
- ticket_id: FK -> sales_tickets
- product_id: FK -> products
- product_name, quantity, unit_price, total
- discount, created_at
```

### Tabla: `ticket_audit_log`
```sql
- id: PK
- ticket_id: FK
- action: 'create', 'update', 'delete', etc
- description, old_value, new_value
- admin_id, ip_address, created_at
```

### Tabla: `ticket_folio_counter`
```sql
- year_month: 'YYYY-MM' (PK)
- counter: número secuencial
```

## Características

### 1. Generación de Folios
- Secuencial por mes
- Formato: `202604-00001` (mes + 5 dígitos)
- Garantizado único mediante transacciones
- Restablecimiento automático mensual

### 2. Creación de Tickets
```php
POST /api/tickets.php?action=create

{
    "user_id": 123,
    "ticket_type": "sale",
    "subtotal": 1000.00,
    "tax_amount": 190.00,
    "discount_amount": 100.00,
    "total_amount": 1090.00,
    "payment_method": "card",
    "payment_status": "completed",
    "items": [
        {
            "product_id": 456,
            "product_name": "Producto X",
            "quantity": 5,
            "unit_price": 200.00,
            "total": 1000.00
        }
    ],
    "notes": "Venta normal"
}
```

### 3. Listado con Filtros
```php
GET /api/tickets.php?action=list&page=1&per_page=20
    &folio=202604
    &ticket_type=sale
    &payment_status=completed
    &start_date=2026-04-01
    &end_date=2026-04-30
```

### 4. Verificación de Tickets (Público)
Clientes pueden verificar la autenticidad:
```php
GET /api/tickets.php?action=verify&folio=202604-00001
```

Respuesta:
```json
{
    "success": true,
    "verified": true,
    "folio": "202604-00001",
    "total_amount": "1090.00",
    "issued_date": "2026-04-15 10:30:00",
    "customer_name": "Juan Pérez",
    "item_count": 1
}
```

### 5. Estadísticas Mensuales
```php
GET /api/tickets.php?action=get-stats&year_month=2026-04
```

Retorna:
- Total de tickets
- Total de ventas
- Total de devoluciones
- Total de impuestos
- Promedio por ticket

### 6. Archivamiento
```php
POST /api/tickets.php?action=archive-previous-month
```

Automatiza el archivamiento del mes anterior el día 1 de cada mes.

## Interfaz de Admin (tickets.php)

### Secciones

1. **Estadísticas en Tarjetas**
   - Total tickets este mes
   - Total de ventas
   - Devoluciones
   - Ticket promedio

2. **Filtros Avanzados**
   - Búsqueda por folio
   - Tipo de ticket
   - Estado de pago
   - Rango de fechas

3. **Tabla de Tickets**
   - Listado paginado
   - Acciones por ticket
   - Descarga y verificación

4. **Acciones Globales**
   - Crear ticket manual
   - Descargar CSV
   - Archivar mes anterior

## Auditoría

Cada acción se registra con:
- Acción realizada
- Valores anteriores/nuevos
- Usuario que realizó cambio
- IP de origen
- Timestamp exacto

## Integraciones

### Con Órdenes
Si existe `orders.id`, el ticket se vincula automáticamente

### Con Usuarios
Datos del cliente se obtienen de `users` tabla

### Con Productos
Información de productos se vincula en items

## Seguridad

- ✅ Solo acceso admin
- ✅ Validación CSRF en POST/PUT
- ✅ Soft deletes (nunca se borra)
- ✅ Auditoría completa de cambios
- ✅ Folios inmutables (único)
- ✅ Verificación pública limitada (solo lectura)

## Endpoints API

| Método | Endpoint | Descripción | Permisos |
|--------|----------|-------------|----------|
| GET | `/api/tickets.php?action=list` | Listar tickets | Admin |
| POST | `/api/tickets.php?action=create` | Crear ticket | Admin |
| GET | `/api/tickets.php?action=get-by-folio` | Obtener por folio | Admin |
| GET | `/api/tickets.php?action=verify` | Verificar ticket | Público |
| GET | `/api/tickets.php?action=get-stats` | Estadísticas | Admin |
| POST | `/api/tickets.php?action=generate-stats` | Generar estadísticas | Admin |
| POST | `/api/tickets.php?action=archive-previous-month` | Archivar anterior | Admin |

## Mantenimiento

### Inicialización
1. Ejecutar migración: `GET /public/migrate.php` (solo localhost/admin)
2. Verificar tablas creadas
3. Probar generación de folio

### Respaldo
Exportar mensualmente a CSV para auditoría externa.

### Optimización
- Índices en: `folio`, `user_id`, `issued_date`, `payment_status`
- Archivamiento automático del mes anterior
- Estadísticas precalculadas mensualmente

## Ejemplo de Uso

### 1. Crear ticket
```bash
curl -X POST http://localhost/api/tickets.php?action=create \
  -H "Content-Type: application/json" \
  -d '{
    "csrf_token": "TOKEN",
    "user_id": 123,
    "ticket_type": "sale",
    "subtotal": 1000,
    "tax_amount": 190,
    "total_amount": 1090,
    "payment_method": "card"
  }'
```

### 2. Verificar ticket
```bash
curl http://localhost/api/tickets.php?action=verify&folio=202604-00001
```

### 3. Obtener estadísticas
```bash
curl http://localhost/api/tickets.php?action=get-stats
```

## Troubleshooting

### Tabla no existe
→ Ejecutar `/public/migrate.php`

### Folio duplicado
→ Verificar `ticket_folio_counter` y resetear si es necesario

### Estadísticas desactualizadas
→ Ejecutar `GET /api/tickets.php?action=generate-stats&year_month=2026-04`

---

**Versión:** 1.0  
**Última actualización:** Abril 2026  
**Responsable:** DevOps Team
