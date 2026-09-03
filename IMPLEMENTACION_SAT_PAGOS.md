# Guía de Implementación - Pagos Reales y Facturación SAT CFDI 4.0

## 📋 Resumen de Mejoras Implementadas

Esta guía documenta las mejoras críticas implementadas para cumplir con los requisitos del SAT y habilitar pagos reales en la plataforma Truper.

### ✅ Mejoras Implementadas

1. **Integración Real de Pasarelas de Pago** (Stripe, Mercado Pago)
2. **Timbrado CFDI 4.0 con Facturapi** (PAC autorizado)
3. **Factura Global Diaria/Mensual** (Requisito SAT)
4. **Cálculo de IVA con Productos Exentos**
5. **Validación de RFC SAT**
6. **Complementos de Pago** (para pagos parciales)
7. **Webhooks de Confirmación de Pagos**

---

## 🚀 Instalación y Configuración

### 1. Instalar Dependencias de Composer

```bash
cd c:\Users\ksgom\proyecto_Truper
composer install
```

Esto instalará:
- `stripe/stripe-php` - SDK de Stripe
- `facturapi/facturapi-php` - SDK de Facturapi (PAC)
- `mercadopago/dx-php` - SDK de Mercado Pago

### 2. Ejecutar Migraciones de Base de Datos

```bash
psql -U truper_admin -d truper_platform -f database_migrations/add_tax_fields_to_products.sql
psql -U truper_admin -d truper_platform -f database_migrations/add_payment_complements_table.sql
```

Las migraciones agregan:
- Campos de impuestos a `products` (`is_tax_exempt`, `tax_rate`, `tax_type`)
- Campos de desglose de impuestos a `orders` y `order_items`
- Tabla `global_invoices` para facturas globales
- Tabla `payment_complements` para complementos de pago

### 3. Configurar Pasarelas de Pago

Accede a: `http://localhost:8000/admin_online_billing.php`

#### Stripe
1. Ve a la pestaña "Pasarelas de Cobro en Línea"
2. Ingresa tu **Stripe Publishable Key** (pk_live_...)
3. Ingresa tu **Stripe Secret Key** (sk_live_...)
4. Ingresa tu **Stripe Webhook Secret** (whsec_...)
5. Configura el webhook en Stripe: `https://tu-dominio.com/api/stripe_webhook.php`

#### Mercado Pago
1. Ingresa tu **Mercado Pago Public Key** (APP_USR-...)
2. Ingresa tu **Mercado Pago Access Token** (APP_USR-...)
3. Configura el webhook en Mercado Pago: `https://tu-dominio.com/api/mercadopago_webhook.php`

### 4. Configurar Facturación SAT (Facturapi)

1. Ve a la pestaña "Configuración Fiscal CFDI 4.0"
2. Ingresa tu **API Key de Facturapi**
3. Configura los datos fiscales de tu empresa:
   - RFC de la empresa
   - Razón Social exacta
   - Régimen Fiscal (catálogo SAT)
   - Código Postal del domicilio fiscal
4. Verifica el estatus de tus CSD (Certificados de Sello Digital)

### 5. Configurar Cron Job de Factura Global

#### En Linux/Mac (crontab)
```bash
crontab -e
```

Agregar esta línea:
```bash
0 1 * * * /usr/bin/php /var/www/proyecto_Truper/cron/daily_global_invoice.php
```

Esto ejecutará la factura global diariamente a las 1:00 AM.

#### En Windows (Task Scheduler)
1. Abrir "Programador de Tareas"
2. Crear tarea básica
3. Acción: Iniciar un programa
4. Programa: `php`
5. Argumentos: `C:\Users\ksgom\proyecto_Truper\cron\daily_global_invoice.php`
6. Desencadenador: Diariamente a las 1:00 AM

---

## 📊 Uso de las Nuevas Funcionalidades

### Procesamiento de Pagos en Checkout

El checkout ahora procesa pagos reales:

```javascript
// Ejemplo de llamada al checkout con pago Stripe
const paymentMethodId = await stripe.createPaymentMethod({
    type: 'card',
    card: cardElement
});

const response = await fetch('/api/checkout.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken
    },
    body: JSON.stringify({
        // ... datos del cliente
        paymentMethod: 'credit_card',
        paymentMethodId: paymentMethodId.id,
        cartItems: cartItems,
        requireInvoice: true,
        rfc: 'XAXX010101000',
        taxName: 'PÚBLICO EN GENERAL',
        taxRegime: '616',
        zipCodeFiscal: '44100',
        cfdiUse: 'G03'
    })
});
```

### Emisión de Facturas SAT

Las facturas se emiten automáticamente cuando:
1. El cliente marca "Requiero Factura Fiscal" en el checkout
2. El pago se confirma exitosamente
3. Los datos fiscales son válidos

### Factura Global Manual

Para generar facturas globales manualmente:
1. Ve a `admin_online_billing.php`
2. Pestaña "Factura Global"
3. Click en "Generar Factura Global Diaria" o "Mensual"
4. Ingresa la fecha (opcional)

### Cancelación de Facturas SAT

1. Ve a "Monitor de Facturas y Cancelaciones"
2. Busca la factura
3. Click en "Cancelar"
4. Selecciona el motivo oficial (01, 02, 03, 04)
5. El sistema reincorpora el inventario automáticamente

---

## 🔍 Validación de RFC

El sistema valida RFCs en dos niveles:

### Validación Básica (checkout)
```php
$validation = SatCatalogs::validateRfc($rfc);
// Retorna: ['valid' => true/false, 'message' => '...', 'is_generic' => true/false]
```

### Validación Estricta (administración)
```php
$validation = SatCatalogs::validateRfcWithChecksum($rfc);
// Valida formato, longitud y fecha
```

---

## 💾 Estructura de Nuevas Tablas

### `global_invoices`
Almacena facturas globales emitidas:
- `invoice_uuid` - UUID de Facturapi
- `invoice_date` - Fecha de la factura
- `total_amount` - Monto total
- ` sales_count` - Número de ventas incluidas
- `xml_url`, `pdf_url` - URLs de descarga

### `payment_complements`
Almacena complementos de pago:
- `original_invoice_uuid` - UUID de factura original
- `complement_uuid` - UUID del complemento
- `amount` - Monto del pago parcial
- `payment_method` - Método de pago

### Campos agregados a `products`
- `is_tax_exempt` - Booleano para productos exentos de IVA
- `tax_rate` - Tasa de impuesto (default 16.00)
- `tax_type` - Tipo de impuesto (IVA, IEPS, etc.)

### Campos agregados a `orders`
- `subtotal_amount` - Subtotal sin impuestos
- `tax_amount` - Monto total de impuestos
- `tax_rate_applied` - Tasa de impuesto aplicada
- `global_invoice_id` - FK a global_invoices

### Campos agregados a `order_items`
- `tax_rate` - Tasa de impuesto por item
- `tax_amount` - Monto de impuesto por item
- `line_total_with_tax` - Total con impuestos

---

## 🧪 Pruebas

### Probar Pagos Stripe (Sandbox)
1. Configura Stripe en modo sandbox
2. Usa tarjetas de prueba: `4242 4242 4242 4242`
3. Verifica que el webhook reciba la confirmación

### Probar Facturación (Sandbox Facturapi)
1. Usa API key de sandbox de Facturapi
2. Genera una factura de prueba
3. Verifica que se genere XML y PDF

### Probar Factura Global
```bash
php cron/daily_global_invoice.php
```

---

## ⚠️ Consideraciones Importantes

### Seguridad
- **Nunca** commits las API keys en el repositorio
- Usa variables de entorno en producción
- Configura HTTPS obligatoriamente
- Valida todos los webhooks con firmas secretas

### Cumplimiento SAT
- La factura global es **obligatoria** para ventas sin factura individual
- Los complementos de pago son obligatorios para pagos parciales
- Las cancelaciones deben usar los 4 motivos oficiales
- Guarda los CSD y XMLs por 5 años (requisito SAT)

### Rendimiento
- Los webhooks deben responder en < 5 segundos
- Usa colas para procesamiento asíncrono si hay alto volumen
- Cachea catálogos SAT para evitar llamadas repetidas

---

## 📞 Soporte

Para problemas con:
- **Stripe**: https://stripe.com/docs
- **Mercado Pago**: https://www.mercadopago.com.mx/developers
- **Facturapi**: https://docs.facturapi.mx/
- **SAT**: https://www.sat.gob.mx/

---

## 🔄 Actualizaciones Futuras

Siguientes mejoras recomendadas:
1. Integración con servicio de validación RFC en tiempo real del SAT
2. Notificaciones por WhatsApp para facturas emitidas
3. Dashboard de métricas fiscales
4. Exportación a contabilidad (XML contable)
5. Integración con sistema POS para facturación en mostrador

---

**Última actualización:** 2026-08-18
**Versión:** 2.0.0
