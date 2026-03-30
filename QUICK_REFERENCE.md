# GUÃA RÃPIDA - Truper

## Inicio RÃ¡pido

### 1. InstalaciÃ³n Base de Datos
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
- **Admin**: admin@truper.com / password123
- **Cliente**: cliente@truper.com / password123

## Funcionalidades Principales

### Para Clientes
- âœ“ Registro y cuenta
- âœ“ Browear catÃ¡logo de productos
- âœ“ Crear Ã³rdenes
- âœ“ Acumular puntos (1 punto = $10 de compra)
- âœ“ BonificaciÃ³n de cumpleaÃ±os
- âœ“ Solicitar mayoreo
- âœ“ Rastrear pagos

### Para Administradores
- âœ“ Dashboa con resumen general
- âœ“ Gestionar usuarios y empleados
- âœ“ Gestionar catÃ¡logo de productos
- âœ“ Gestionar Ã³rdenes
- âœ“ Ver analytics y predicciones
- âœ“ Aprobar solicitudes de mayoreo
- âœ“ Gestionar cÃ³digos de barras

### Para Empleados
- âœ“ Ver tareas asignadas
- âœ“ Registrar escaneos de cÃ³digos
- âœ“ Procesar pagos

## Estructura de Archivos Clave

```
backend/
  â”œâ”€â”€ config/        # ConfiguraciÃ³n y seguridad
  â”œâ”€â”€ controllers/   # LÃ³gica de negocio
  â”œâ”€â”€ models/        # Modelos de datos
  â””â”€â”€ utils/         # Utilidades
  
views/               # Vistas para clientes
admin/               # Panel administrativo
assets/
  â”œâ”€â”€ css/           # Estilos (Naranja #FF8C00)
  â”œâ”€â”€ js/            # JavaScript
  â””â”€â”€ img/           # ImÃ¡genes
  
db/                  # Base de datos SQL
```

## Endpoints Principales

### AutenticaciÃ³n
- `POST /backend/controllers/auth_controller.php` (login, register)

### Ã“rdenes
- `POST /backend/controllers/order_controller.php` (create, track_payment)

### Mayoreo
- `POST /backend/controllers/wholesale_controller.php` (create_request)

### Perfil
- `POST /backend/controllers/profile_controller.php` (update_profile, change_password)

## Colores Truper
- ðŸŸ  Primario: `#FF8C00` (Naranja)
- âš« Secundario: `#000000` (Negro)
- âšª Fondo: `#FFFFFF` (Blanco)

## Scripts SQL Ãštiles

### Agregar producto
```sql
INSERT INTO products (name, sku, description, category, cost_price, sell_price)
VALUES ('Producto', 'SKU001', 'DescripciÃ³n', 'CategorÃ­a', 10.00, 25.00);
```

### Ver Ã³rdenes de usuario
```sql
SELECT * FROM orders WHERE user_id = 2 ORDER BY created_at DESC;
```

### Obtener estadÃ­sticas mensuales
```sql
SELECT DATE_FORMAT(created_at, '%Y-%m') as mes, COUNT(*) as ordenes, SUM(total) as venta
FROM orders GROUP BY DATE_FORMAT(created_at, '%Y-%m');
```

## Funciones PHP Ãštiles

```php
// Agregar orden
$order = new Order();
$order->create($user_id, $total);

// Registrar pago
$payment = new PaymentTracker();
$payment->recordPayment($order_id, $amount, 'credit_card');

// Get anÃ¡lytics
$analytics = new Analytics();
$forecast = $analytics->demandForecast($product_id);
$trends = $analytics->getSeasonalTrends();

// Escanear cÃ³digo de barras
$barcode = new BarcodeReader();
$product = $barcode->scanBarcode($barcode_number);
```

## Seguridad

- âœ“ ContraseÃ±as hasheadas con Bcrypt
- âœ“ ProtecciÃ³n CSRF activa
- âœ“ ValidaciÃ³n de inputs
- âœ“ AutenticaciÃ³n por roles
- âœ“ HTTPS ready
- âœ“ Logs de actividad

## Troubleshooting

### Error de conexiÃ³n DB
1. Verificar credenciales en `backend/config/database.php`
2. Asegurar MySQL estÃ¡ corriendo
3. Crear usuario: `CREATE USER 'trupper_user'@'localhost' IDENTIFIED BY 'trupper_password';`

### Permisos de carpeta
```bash
chmod 755 logs/
chmod 755 assets/
```

### PHP no encuentra archivos
Verificar que `RewriteEngine On` en `.htaccess` estÃ¡ activo

## PrÃ³ximas Mejoras

- [ ] API REST completa
- [ ] AutenticaciÃ³n OAuth
- [ ] Alertas por email de cumpleaÃ±os
- [ ] SincronizaciÃ³n de inventario en tiempo real
- [ ] AplicaciÃ³n mÃ³vil
- [ ] Sistema de chat en vivo
- [ ] Reportes PDF
- [ ] Exportar datos a Excel

---
**Ãšltima actualizaciÃ³n**: Marzo 2024
**VersiÃ³n**: 1.0.0



