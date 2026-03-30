# Truper - Sistema de GestiÃ³n de Inventario y Ventas

## DescripciÃ³n General

Truper es una plataforma web integral diseÃ±ada para la gestiÃ³n de inventario, venta de productos y relaciÃ³n con clientes mayoristas. El sistema incluye:

- **CatÃ¡logo Digital**: VisualizaciÃ³n rÃ¡pida de productos con bÃºsqueda y filtrado
- **Sistema de Ã“rdenes**: CreaciÃ³n y seguimiento de Ã³rdenes de compra
- **Programa de Puntos**: AcumulaciÃ³n de puntos y bonificaciones por cumpleaÃ±os
- **Control de Pagos**: Seguimiento automatizado del estado de pagos
- **Gestor de Tareas**: AsignaciÃ³n de tareas para empleados
- **AnÃ¡lisis de EstadÃ­sticas**: Analytics basado en compras con predicciones
- **Ventas Mayoreo**: Cotizaciones y Ã³rdenes especiales para mayoristas
- **Lector de CÃ³digos de Barras**: IntegraciÃ³n con lectores de cÃ³digos QR/Barras
- **Seguridad**: AutenticaciÃ³n y autorizaciÃ³n por roles

## Requisitos TÃ©cnicos

- **PHP**: 7.4 o superior
- **MySQL**: 5.7 o superior
- **Servidor Web**: Apache o Nginx
- **Navegador**: Moderno (Chrome, Firefox, Safari, Edge)

## InstalaciÃ³n

### 1. Configurar la Base de Datos

```bash
mysql -u root -p < db/trupper_db.sql
```

### 2. Configurar Variables de Entorno

Editar `backend/config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'trupper_user');
define('DB_PASS', 'trupper_password');
define('DB_NAME', 'trupper_db');
```

### 3. Permisos de Directorios

```bash
mkdir -p logs/
chmod 755 logs/
```

### 4. Iniciar Servidor

```bash
php -S localhost:8000
```

Acceder a: `http://localhost:8000`

## Credenciales de Prueba

**Admin:**
- Email: `admin@truper.com`
- Password: `password123`

**Cliente:**
- Email: `cliente@truper.com`
- Password: `password123`

## Estructura del Proyecto

```
trupper_web/
â”œâ”€â”€ assets/
â”‚   â”œâ”€â”€ css/
â”‚   â”‚   â”œâ”€â”€ style.css          # Estilos principales
â”‚   â”‚   â”œâ”€â”€ dashboard.css      # Estilos dashboard
â”‚   â”‚   â”œâ”€â”€ products.css       # Estilos de productos
â”‚   â”‚   â””â”€â”€ responsive.css     # Responsive design
â”‚   â”œâ”€â”€ js/
â”‚   â”‚   â”œâ”€â”€ main.js           # JavaScript principal
â”‚   â”‚   â”œâ”€â”€ dashboard.js      # Scripts dashboard
â”‚   â”‚   â””â”€â”€ products.js       # Scripts productos
â”‚   â””â”€â”€ img/                  # ImÃ¡genes
â”œâ”€â”€ backend/
â”‚   â”œâ”€â”€ config/
â”‚   â”‚   â”œâ”€â”€ database.php      # ConfiguraciÃ³n DB
â”‚   â”‚   â””â”€â”€ security.php      # Seguridad
â”‚   â”œâ”€â”€ controllers/
â”‚   â”‚   â”œâ”€â”€ auth_controller.php
â”‚   â”‚   â”œâ”€â”€ order_controller.php
â”‚   â”‚   â””â”€â”€ wholesale_controller.php
â”‚   â”œâ”€â”€ models/
â”‚   â”‚   â”œâ”€â”€ User.php
â”‚   â”‚   â”œâ”€â”€ Product.php
â”‚   â”‚   â”œâ”€â”€ Order.php
â”‚   â”‚   â”œâ”€â”€ Task.php
â”‚   â”‚   â”œâ”€â”€ Analytics.php
â”‚   â”‚   â”œâ”€â”€ WholesaleSale.php
â”‚   â”‚   â””â”€â”€ BarcodeReader.php
â”‚   â””â”€â”€ utils/
â”‚       â””â”€â”€ Utilities.php
â”œâ”€â”€ views/
â”‚   â”œâ”€â”€ login.php
â”‚   â”œâ”€â”€ register.php
â”‚   â”œâ”€â”€ dashboard.php
â”‚   â”œâ”€â”€ products.php
â”‚   â”œâ”€â”€ my_orders.php
â”‚   â”œâ”€â”€ profile.php
â”‚   â”œâ”€â”€ my_points.php
â”‚   â””â”€â”€ wholesale.php
â”œâ”€â”€ db/
â”‚   â””â”€â”€ trupper_db.sql
â””â”€â”€ index.php
```

## Funcionalidades Principales

### 1. AutenticaciÃ³n y AutorizaciÃ³n

- Registro de usuarios (Clientes, Empleados, Administradores)
- Login seguro con contraseÃ±as hasheadas
- ProtecciÃ³n CSRF
- GestiÃ³n de sesiones

### 2. GestiÃ³n de Productos

- CatÃ¡logo completo con bÃºsqueda
- Filtrado por categorÃ­a
- IntegraciÃ³n con cÃ³digos de barras
- Seguimiento de costos y precios

### 3. Ã“rdenes y Pedidos

```php
$order = new Order();
$order->create($user_id, $total);
$order->addItem($order_id, $product_id, $quantity, $price);
$order->recordPayment($order_id, $amount, 'credit_card');
```

### 4. Programa de Puntos y Bonificaciones

- 1 punto por cada $10 de compra
- AcumulaciÃ³n sin lÃ­mite
- BonificaciÃ³n especial en cumpleaÃ±os
- Dashboard de puntos disponibles

### 5. Control de Pagos

```php
$payment = new PaymentTracker();
$status = $payment->getPaymentStatus($order_id);
$payment->recordPayment($order_id, $amount, 'cash');
```

### 6. Analytics y Predicciones

```php
$analytics = new Analytics();
$trends = $analytics->getSeasonalTrends();
$forecast = $analytics->demandForecast($product_id);
```

### 7. Ventas Mayoreo

```php
$wholesale = new WholesaleSale();
$request = $wholesale->createRequest($user_id, $company_name, $email, $phone, $type, $description);
$quote = $wholesale->createQuote($request_id, $user_id, $items, $discount);
```

### 8. Lector de CÃ³digos de Barras

```php
$barcode = new BarcodeReader();
$product = $barcode->scanBarcode($barcode_number);
$stats = $barcode->getScanStats($days = 30);
```

## Colores Corporativos

- **Primario**: `#FF8C00` (Naranja)
- **Secundario**: `#000000` (Negro)
- **Fondo**: `#FFFFFF` (Blanco)

## Seguridad

### Implementadas:

- âœ… Hashing de contraseÃ±as (Bcrypt)
- âœ… Token CSRF
- âœ… SanitizaciÃ³n de inputs
- âœ… ValidaciÃ³n de email
- âœ… Headers HTTP de seguridad
- âœ… Roles y permisos
- âœ… Logs de actividad

## APIs y Endpoints

### AutenticaciÃ³n

```
POST /backend/controllers/auth_controller.php
  - action=login
  - action=register
  - action=logout
```

### Ã“rdenes

```
POST /backend/controllers/order_controller.php
  - action=create
  - action=track_payment
```

### Mayoreo

```
POST /backend/controllers/wholesale_controller.php
  - action=create_request
```

## PersonalizaciÃ³n

### Cambiar Colores

Editar `assets/css/style.css`:

```css
:root {
    --color-primary: #FF8C00;      /* Naranja */
    --color-secondary: #000000;    /* Negro */
    --color-light: #FFFFFF;        /* Blanco */
}
```

### Agregar MÃ¡s Productos

```sql
INSERT INTO products (name, sku, description, category, cost_price, sell_price)
VALUES ('Producto', 'SKU001', 'DescripciÃ³n', 'CategorÃ­a', 10.00, 25.00);
```

## Mantenimiento

### Limpiar Logs

```bash
rm logs/*.log
```

### Respaldar Base de Datos

```bash
mysqldump -u root -p trupper_db > backup.sql
```

## Soporte

Para mÃ¡s informaciÃ³n o reportar problemas, contacta a:
- Email: info@truper.com
- TelÃ©fono: +1-234-567-8900

## Licencia

Â© 2024 Truper. Todos los derechos reservados.

## Changelog

### v1.0.0 (2024)
- Lanzamiento inicial del sistema
- Funcionalidades principales implementadas
- IntegraciÃ³n de seguridad
- Sistema de analytics

---

**Ãšltima actualizaciÃ³n**: Marzo 2024



