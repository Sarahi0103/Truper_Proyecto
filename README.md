# Truper - Sistema de Gestión de Inventario y Ventas

## Descripción General

Truper es una plataforma web integral diseñada para la gestión de inventario, venta de productos y relación con clientes mayoristas. El sistema incluye:

- **Catálogo Digital**: Visualización rápida de productos con búsqueda y filtrado
- **Sistema de Órdenes**: Creación y seguimiento de órdenes de compra
- **Programa de Puntos**: Acumulación de puntos y bonificaciones por cumpleaños
- **Control de Pagos**: Seguimiento automatizado del estado de pagos
- **Gestor de Tareas**: Asignación de tareas para empleados
- **Análisis de Estadísticas**: Analytics basado en compras con predicciones
- **Ventas Mayoreo**: Cotizaciones y órdenes especiales para mayoristas
- **Lector de Códigos de Barras**: Integración con lectores de códigos QR/Barras
- **Seguridad**: Autenticación y autorización por roles

## Requisitos Técnicos

- **PHP**: 7.4 o superior
- **MySQL**: 5.7 o superior
- **Servidor Web**: Apache o Nginx
- **Navegador**: Moderno (Chrome, Firefox, Safari, Edge)

## Instalación

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

## Deploy En Render

El proyecto ya incluye archivos listos para Render:

- `Dockerfile`
- `render.yaml`

Pasos:

1. En Render, seleccionar New + > Blueprint.
2. Conectar repo: `https://github.com/Sarahi0103/Truper_Proyecto`
3. Configurar variables de entorno de MySQL:
  - `DB_HOST`
  - `DB_PORT`
  - `DB_USER`
  - `DB_PASS`
  - `DB_NAME`
4. Desplegar.

Nota: este sistema usa MySQL; debes usar un proveedor MySQL externo si tu plan de Render no incluye MySQL.

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
├── assets/
│   ├── css/
│   │   ├── style.css          # Estilos principales
│   │   ├── dashboard.css      # Estilos dashboard
│   │   ├── products.css       # Estilos de productos
│   │   └── responsive.css     # Responsive design
│   ├── js/
│   │   ├── main.js           # JavaScript principal
│   │   ├── dashboard.js      # Scripts dashboard
│   │   └── products.js       # Scripts productos
│   └── img/                  # Imágenes
├── backend/
│   ├── config/
│   │   ├── database.php      # Configuración DB
│   │   └── security.php      # Seguridad
│   ├── controllers/
│   │   ├── auth_controller.php
│   │   ├── order_controller.php
│   │   └── wholesale_controller.php
│   ├── models/
│   │   ├── User.php
│   │   ├── Product.php
│   │   ├── Order.php
│   │   ├── Task.php
│   │   ├── Analytics.php
│   │   ├── WholesaleSale.php
│   │   └── BarcodeReader.php
│   └── utils/
│       └── Utilities.php
├── views/
│   ├── login.php
│   ├── register.php
│   ├── dashboard.php
│   ├── products.php
│   ├── my_orders.php
│   ├── profile.php
│   ├── my_points.php
│   └── wholesale.php
├── db/
│   └── trupper_db.sql
└── index.php
```

## Funcionalidades Principales

### 1. Autenticación y Autorización

- Registro de usuarios (Clientes, Empleados, Administradores)
- Login seguro con contraseñas hasheadas
- Protección CSRF
- Gestión de sesiones

### 2. Gestión de Productos

- Catálogo completo con búsqueda
- Filtrado por categoría
- Integración con códigos de barras
- Seguimiento de costos y precios

### 3. Órdenes y Pedidos

```php
$order = new Order();
$order->create($user_id, $total);
$order->addItem($order_id, $product_id, $quantity, $price);
$order->recordPayment($order_id, $amount, 'credit_card');
```

### 4. Programa de Puntos y Bonificaciones

- 1 punto por cada $10 de compra
- Acumulación sin límite
- Bonificación especial en cumpleaños
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

### 8. Lector de Códigos de Barras

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

- ✅ Hashing de contraseñas (Bcrypt)
- ✅ Token CSRF
- ✅ Sanitización de inputs
- ✅ Validación de email
- ✅ Headers HTTP de seguridad
- ✅ Roles y permisos
- ✅ Logs de actividad

## APIs y Endpoints

### Autenticación

```
POST /backend/controllers/auth_controller.php
  - action=login
  - action=register
  - action=logout
```

### Órdenes

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

## Personalización

### Cambiar Colores

Editar `assets/css/style.css`:

```css
:root {
    --color-primary: #FF8C00;      /* Naranja */
    --color-secondary: #000000;    /* Negro */
    --color-light: #FFFFFF;        /* Blanco */
}
```

### Agregar Más Productos

```sql
INSERT INTO products (name, sku, description, category, cost_price, sell_price)
VALUES ('Producto', 'SKU001', 'Descripción', 'Categoría', 10.00, 25.00);
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

Para más información o reportar problemas, contacta a:
- Email: info@truper.com
- Teléfono: +1-234-567-8900

## Licencia

© 2024 Truper. Todos los derechos reservados.

## Changelog

### v1.0.0 (2024)
- Lanzamiento inicial del sistema
- Funcionalidades principales implementadas
- Integración de seguridad
- Sistema de analytics

---

**Última actualización**: Marzo 2024



