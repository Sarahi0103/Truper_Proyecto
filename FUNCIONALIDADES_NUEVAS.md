# 📊 Nuevas Funcionalidades Implementadas

## Resumen Ejecutivo

Se han implementado cuatro funcionalidades stra egicas para mejorar la operación:

1. **Control Semanal de Consumo y Deuda** - Seguimiento automático del consumo por cliente con histórico de 12 semanas
2. **Plazos de Pago Configurables** - Liquidación inmediata, 15 días o 30 días por nota
3. **Registro de Pagos contra Balance** - Gestión de crédito con actualización en tiempo real
4. **Cotización por WhatsApp** - Compartir carrito directamente vía WhatsApp para consultas rápidas
5. **Selector Tema Oscuro/Claro** - Interfaz adaptable con persistencia en navegador

---

## 1️⃣ Control Semanal de Consumo y Deuda

### Para Clientes
- **Ubicación**: `Mi Cuenta` → `Control Semanal`
- **Información que Ve**:
  - Consumo de la semana en curso
  - Deuda acumulada de la semana
  - Histórico de últimas 12 semanas
  - Estado del pago (pendiente, parcial, pagado)
  
### Funcionamiento Automático
- Cada orden que crea un cliente se suma automáticamente para la semana
- Sistema calcula la semana desde lunes a domingo (configurable)
- Se actualiza en tiempo real

### Para Administrador
- Poder ver el consumo semanal de cada cliente
- Registrar pagos contra la semana o nota específica
- Generar reportes de crédito por cliente

### Endpoint API
```
GET /api/client_account.php?action=weekly-summary
GET /api/client_account.php?action=weekly-history
```

---

## 2️⃣ Plazos de Pago Configurables

### Opciones Disponibles
1. **Inmediato** - Pago al momento de la compra
2. **15 Días** - Plazo de 15 días naturales
3. **30 Días** - Plazo de 30 días naturales

### Cómo Funciona
- Al crear una orden, se asigna automáticamente el plazo configurado
- `payment_due_date` se calcula automáticamente basado en la fecha de orden
- El cliente ve en su cuenta cuándo vence cada nota

### Campos en Base de Datos
```sql
ALTER TABLE orders ADD COLUMN payment_terms ENUM('immediate', '15_days', '30_days');
ALTER TABLE orders ADD COLUMN payment_due_date DATE;
ALTER TABLE orders ADD COLUMN balance DECIMAL(10,2);
```

### Ejemplo de Flujo
1. Cliente realiza compra el 07/04/2026
2. Plazo asignado: 30 días
3. Fecha vencimiento: 07/05/2026
4. Se visualiza en "Mi Cuenta → Estado de Crédito"

---

## 3️⃣ Registro de Pagos contra Balance

### Para Administrador
- **Ubicación**: Sistema rotativo (será en admin panel)
- **Funcionalidad**:
  - Registrar pago total o parcial
  - Asignar a una orden específica o al crédito general
  - Guardar método de pago (efectivo, transferencia, cheque, etc.)
  - Escribir notas/referencia

### Información Registrada
- Monto pagado
- Fecha del pago  
- Método de pago
- Número de referencia (cheque, transferencia)
- Notas del administrador
- Usuario que registró el pago

### Actualización Automática
- Se reduce automáticamente el balance del cliente
- La orden se marca como "pagada" si balance = 0
- Se registra en el historial de pagos

### Endpoint API
```
POST /api/client_account.php?action=record-payment
{
  "user_id": 123,
  "payment_amount": 5000.00,
  "payment_date": "2026-04-07",
  "payment_method": "transfer",
  "reference_number": "TRF-12345",
  "notes": "Pago parcial",
  "order_id": 456  // opcional
}
```

---

## 4️⃣ Cotización por WhatsApp

### Para Clientes
- **Ubicación**: Catálogo → Botón "Compartir por WhatsApp" (abajo del carrito)
- **Flujo**:
  1. Cliente agrega productos al carrito
  2. Hace clic en "Compartir por WhatsApp"
  3. Se abre conversación de WhatsApp con mensaje pre-redactado
  4. Mensaje incluye: productos, cantidades, total estimado
  5. Cliente envía directamente a la empresa

### Ventajas
- ✅ Consulta rápida de disponibilidad
- ✅ Respuesta inmediata del negocio
- ✅ Sin abandonar la plataforma
- ✅ Verificación antes de confirmación de compra

### Ejemplo de Mensaje
```
Solicito cotización de los siguientes productos:

• 2x Taladro Percutor 1/2" 750W
• 1x Juego de Llaves Combinadas 12 pzas
• 5x Martillo Uña 16 oz

Total estimado: $5,486.00

¿Pueden confirmar disponibilidad y tiempo de entrega?
```

### Tabla de Historial
Se guardan todas las cotizaciones compartidas:
```sql
CREATE TABLE whatsapp_quotes (
  id INT,
  user_id INT,
  quote_data JSON,      // Items y cantidades
  total_amount DECIMAL,
  items_count INT,
  status ENUM('pending', 'sent', 'answered', 'converted_to_order'),
  created_at TIMESTAMP
)
```

### Endpoint API
```
POST /api/client_account.php?action=whatsapp-quote
GET /api/client_account.php?action=pending-quotes
```

---

## 5️⃣ Selector de Tema Oscuro/Claro

### Ubicación
- **Botón fijo**: Esquina superior izquierda de la pantalla
- **Etiqueta**: "🌙 Tema"
- **Color**: Naranja Truper

### Características
- ✨ Transiciones suaves de 0.3 segundos
- 💾 Se guarda la preferencia en el navegador (LocalStorage)
- 🎨 Tema aplicado en toda la plataforma
- 📱 Responsivo y accesible

### Tema Claro (Light)
```
Fondo: Blanco (#ffffff)
Texto: Negro (#1a1a1a)
Bordes: Gris claro (#dddddd)
Cards: Gris muy claro (#f5f5f5)
```

### Tema Oscuro (Dark)
```
Fondo: Azul oscuro (#0f172a)
Texto: Gris claro (#f1f5f9)
Bordes: Azul gris (#475569)
Cards: Gris oscuro (#1e293b)
```

### CSS Variables
Se implementó un sistema completo de variables CSS en `/public/css/theme.css`:
```css
:root[data-theme="light"] {
  --bg-primary: #ffffff;
  --bg-secondary: #f5f5f5;
  --text-primary: #1a1a1a;
  --text-secondary: #666666;
  /* ... 15 variables más */
}

:root[data-theme="dark"] {
  --bg-primary: #0f172a;
  --bg-secondary: #1e293b;
  /* ... */
}
```

### Módulos con Soporte de Tema
- ✅ Catálogo (index.php)
- ✅ Mi Cuenta (account.php)
- ✅ Dashboard
- ✅ Pedidos
- ✅ Caja
- ✅ Abastecimiento
- ✅ Analíticas
- ✅ Perfil
- ✅ Login clientes
- ✅ Login admin

---

## 📍 Nuevos Archivos Creados

### Backend
| Archivo | Propósito |
|---------|-----------|
| `/api/client_account.php` | API central para cuenta, crédito, pagos y cotizaciones |
| `/db/ALTER_PAYMENT_TERMS.sql` | Script de alteraciones de BD |

### Frontend
| Archivo | Propósito |
|---------|-----------|
| `/account.php` | Dashboard de cliente para ver cuenta, crédito y cotizaciones |
| `/css/theme.css` | Sistema completo de temas oscuro/claro |

---

## 🗄️ Tablas de Base de Datos Nuevas

```sql
-- Control semanal
CREATE TABLE weekly_consumption_summary (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL,
  week_start DATE,
  week_end DATE,
  total_consumed DECIMAL(10,2),
  total_owed DECIMAL(10,2),
  payment_status ENUM('pending', 'partial', 'paid')
)

-- Balance de crédito por cliente
CREATE TABLE client_credit_balance (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT NOT NULL UNIQUE,
  credit_limit DECIMAL(10,2),
  credit_available DECIMAL(10,2),
  credit_used DECIMAL(10,2),
  total_owed DECIMAL(10,2),
  last_payment_date DATE,
  days_overdue INT
)

-- Historial de pagos de crédito
CREATE TABLE credit_payments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  order_id INT,
  payment_amount DECIMAL(10,2),
  payment_date DATE,
  payment_method VARCHAR(50),
  reference_number VARCHAR(100),
  notes TEXT,
  recorded_by INT
)

-- Historial de cotizaciones por WhatsApp
CREATE TABLE whatsapp_quotes (
  id INT PRIMARY KEY AUTO_INCREMENT,
  user_id INT,
  quote_data JSON,
  total_amount DECIMAL(10,2),
  items_count INT,
  status ENUM('pending', 'sent', 'answered', 'converted_to_order')
)
```

### Alteraciones a Tabla Existente `orders`
```sql
ALTER TABLE orders ADD COLUMN payment_terms ENUM('immediate', '15_days', '30_days');
ALTER TABLE orders ADD COLUMN payment_due_date DATE;
ALTER TABLE orders ADD COLUMN balance DECIMAL(10,2);
```

---

## 🔧 Cómo Usar

### Para Clientes - Ver Consumo Semanal
1. Iniciar sesión con código + fecha de nacimiento
2. Hacer clic en "Mi Cuenta"
3. Ver "Control Semanal" con historial de 12 semanas
4. Consultar "Estado de Crédito" para adeudado total

### Para Clientes - Compartir Cotización por WhatsApp
1. Navegar al Catálogo
2. Agregar productos al carrito
3. Hacer clic en botón "📱 Compartir por WhatsApp"
4. Se abre WhatsApp con el mensaje pre-redactado
5. Enviar a número de la empresa
6. Esperar respuesta de disponibilidad

### Para Administrador - Registrar Pago
1. Acceder a módulo de pagos (en desarrollo, será rotativo en admin panel)
2. Seleccionar cliente
3. Ingresar:
   - Monto pagado
   - Fecha
   - Método (efectivo, transfer, cheque)
   - Referencia
   - Notas
4. Sistema actualiza automáticamente balance

### Para Todos - Cambiar Tema
1. Buscar botón "🌙 Tema" en esquina superior izquierda
2. Hacer clic
3. Interfaz cambia entre claro y oscuro
4. Preferencia se guarda automáticamente

---

## 📊 API Endpoints

### Client Account Service
```
GET  /api/client_account.php?action=credit-summary
GET  /api/client_account.php?action=weekly-summary
GET  /api/client_account.php?action=weekly-history
POST /api/client_account.php?action=record-payment
POST /api/client_account.php?action=whatsapp-quote
GET  /api/client_account.php?action=pending-quotes
GET  /api/client_account.php?action=payment-history
```

---

## 🔐 Seguridad

- ✅ Todas las rutas requieren autenticación (`require_login()`)
- ✅ Operaciones sensibles requieren rol admin (`require_admin()`)
- ✅ Prepared statements en todas las queries
- ✅ Sanitización de inputs
- ✅ CSRF protection

---

## 📈 Proximas Mejoras Sugeridas

1. **Reporte de Crédito Administrativo**
   - Ver clientes con mayor deuda
   - Alertas de vencimiento
   - Proyecciones de cobranza

2. **Estadísticas de WhatsApp**
   - Tasa de conversión cotización → orden
   - Productos más consultados
   - Tiempos promedio de respuesta

3. **Automatización de Reminders**
   - Email cuando falta 2 días para vencer
   - SMS de recordatorio
   - Suspensión automática de crédito si vence

4. **Integración Directa de WhatsApp Business**
   - Vincular número de empresa
   - Recibir respuestas en sistema
   - Auto-crear órdenes desde WhatsApp

5. **Personalización de Plazos**
   - Por cliente (mayoristas 30 días)
   - Por producto
   - Por cantidad mínima

---

## ✅ Commit Git

**Hash**: `d5193e8`  
**Mensaje**: "Add weekly consumption tracking, payment terms, WhatsApp sharing, and dark/light theme"  
**Rama**: `main`  
**Cambios**: 57 archivos (+1173 insertions, -5 deletions)

---

## 📞 Soporte

Para preguntas o problemas con estas nuevas funcionalidades, consultar:
- Código fuente: `/public/api/client_account.php`
- UI Cliente: `/public/account.php`
- Estilos de Tema: `/public/css/theme.css`
- Base de datos: `/db/ALTER_PAYMENT_TERMS.sql`
