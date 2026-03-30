# GUÍA RÁPIDA - TRUPPER

## Inicio Rápido

### 1. Instalación Base de Datos
```bash
mysql -u root -p < db/trupper_db.sql
```

### 2. Iniciar Servidor
```bash
cd trupper_web
php -S localhost:8000
```

### 3. Acceso
- **URL**: http://localhost:8000
- **Admin**: admin@trupper.com / password123
- **Cliente**: cliente@trupper.com / password123

## Funcionalidades Principales

### Para Clientes
- ✓ Registro y cuenta
- ✓ Browear catálogo de productos
- ✓ Crear órdenes
- ✓ Acumular puntos (1 punto = $10 de compra)
- ✓ Bonificación de cumpleaños
- ✓ Solicitar mayoreo
- ✓ Rastrear pagos

### Para Administradores
- ✓ Dashboa con resumen general
- ✓ Gestionar usuarios y empleados
- ✓ Gestionar catálogo de productos
- ✓ Gestionar órdenes
- ✓ Ver analytics y predicciones
- ✓ Aprobar solicitudes de mayoreo
- ✓ Gestionar códigos de barras

### Para Empleados
- ✓ Ver tareas asignadas
- ✓ Registrar escaneos de códigos
- ✓ Procesar pagos

## Estructura de Archivos Clave

```
backend/
  ├── config/        # Configuración y seguridad
  ├── controllers/   # Lógica de negocio
  ├── models/        # Modelos de datos
  └── utils/         # Utilidades
  
views/               # Vistas para clientes
admin/               # Panel administrativo
assets/
  ├── css/           # Estilos (Naranja #FF8C00)
  ├── js/            # JavaScript
  └── img/           # Imágenes
  
db/                  # Base de datos SQL
```

## Endpoints Principales

### Autenticación
- `POST /backend/controllers/auth_controller.php` (login, register)

### Órdenes
- `POST /backend/controllers/order_controller.php` (create, track_payment)

### Mayoreo
- `POST /backend/controllers/wholesale_controller.php` (create_request)

### Perfil
- `POST /backend/controllers/profile_controller.php` (update_profile, change_password)

## Colores TRUPPER
- 🟠 Primario: `#FF8C00` (Naranja)
- ⚫ Secundario: `#000000` (Negro)
- ⚪ Fondo: `#FFFFFF` (Blanco)

## Scripts SQL Útiles

### Agregar producto
```sql
INSERT INTO products (name, sku, description, category, cost_price, sell_price)
VALUES ('Producto', 'SKU001', 'Descripción', 'Categoría', 10.00, 25.00);
```

### Ver órdenes de usuario
```sql
SELECT * FROM orders WHERE user_id = 2 ORDER BY created_at DESC;
```

### Obtener estadísticas mensuales
```sql
SELECT DATE_FORMAT(created_at, '%Y-%m') as mes, COUNT(*) as ordenes, SUM(total) as venta
FROM orders GROUP BY DATE_FORMAT(created_at, '%Y-%m');
```

## Funciones PHP Útiles

```php
// Agregar orden
$order = new Order();
$order->create($user_id, $total);

// Registrar pago
$payment = new PaymentTracker();
$payment->recordPayment($order_id, $amount, 'credit_card');

// Get análytics
$analytics = new Analytics();
$forecast = $analytics->demandForecast($product_id);
$trends = $analytics->getSeasonalTrends();

// Escanear código de barras
$barcode = new BarcodeReader();
$product = $barcode->scanBarcode($barcode_number);
```

## Seguridad

- ✓ Contraseñas hasheadas con Bcrypt
- ✓ Protección CSRF activa
- ✓ Validación de inputs
- ✓ Autenticación por roles
- ✓ HTTPS ready
- ✓ Logs de actividad

## Troubleshooting

### Error de conexión DB
1. Verificar credenciales en `backend/config/database.php`
2. Asegurar MySQL está corriendo
3. Crear usuario: `CREATE USER 'trupper_user'@'localhost' IDENTIFIED BY 'trupper_password';`

### Permisos de carpeta
```bash
chmod 755 logs/
chmod 755 assets/
```

### PHP no encuentra archivos
Verificar que `RewriteEngine On` en `.htaccess` está activo

## Próximas Mejoras

- [ ] API REST completa
- [ ] Autenticación OAuth
- [ ] Alertas por email de cumpleaños
- [ ] Sincronización de inventario en tiempo real
- [ ] Aplicación móvil
- [ ] Sistema de chat en vivo
- [ ] Reportes PDF
- [ ] Exportar datos a Excel

---
**Última actualización**: Marzo 2024
**Versión**: 1.0.0
