# 🚀 GUÍA COMPLETA: CREAR PROYECTO NUEVO CON ESPECIFICACIONES DE TRUPER

**Documento de Referencia para Desarrolladores**

---

## 📋 TABLA DE CONTENIDOS

1. [Stack y Herramientas](#stack-y-herramientas)
2. [Estructura del Proyecto](#estructura-del-proyecto)
3. [Configuración Inicial](#configuración-inicial)
4. [Componentes Principales](#componentes-principales)
5. [Base de Datos](#base-de-datos)
6. [Módulos y Funcionalidades](#módulos-y-funcionalidades)
7. [Checklist de Implementación](#checklist-de-implementación)

---

## 🛠️ STACK Y HERRAMIENTAS

### Requisitos del Sistema

```
PHP 7.4 o superior (recomendado 8.0+)
PostgreSQL 12 o superior
Apache/Nginx con mod_rewrite
Composer (gestor de dependencias PHP)
Node.js 16+ + npm (para Vue/Vite)
Git para control de versiones
```

### Stack Propuesto

| Layer | Tecnología | Justificación |
|-------|-----------|--------------|
| **Frontend** | Vue.js 3 + Vite | Framework moderno, SPA, componentes reutilizables |
| **Backend** | PHP 7.4+ | Maduro, amplio hosting, soporte de librerías |
| **BD** | PostgreSQL 15 | Relacional fuerte, escalabilidad, JSONB support |
| **API** | REST API (PHP nativo) | Integración sencilla con Vue via Axios |
| **Autenticación** | Session-based + CSRF | Seguridad, simpler que JWT |
| **Build Tool** | Vite | Build rápido, HMR en desarrollo |
| **Hosting** | Servidor Web Tradicional | Apache/Nginx, bajo costo, amplio soporte |
| **Styling** | Tailwind CSS / Bootstrap Vue | Utilidades, componentes listos |
| **Gestión Estado** | Pinia | State management ligero para Vue 3 |

### Alternativas Modernas (Opcional)

Si quieres un stack más moderno:
- Backend: Laravel/Symfony (framework PHP más robusto)
- Frontend: Vue.js/React (opción moderna)
- Base de Datos: MongoDB (si datos no-relacionales)
- Deploy: Heroku, AWS, DigitalOcean (en lugar de servidor tradicional)

---

## 📁 ESTRUCTURA DEL PROYECTO

### Scaffolding Recomendado

```
proyecto-nuevo/
├── backend/
│   ├── config/                      # Config específica backend
│   ├── controllers/                 # Controladores (lógica)
│   │   ├── auth_controller.php
│   │   ├── products_controller.php
│   │   ├── orders_controller.php
│   │   └── ...
│   ├── config/
│   │   ├── config.php               # Configuración general
│   │   ├── database.php             # Conexión a BD
│   │   └── security.php             # Headers de seguridad, CSRF
│   ├── models/                      # Modelos (BD interaction)
│   │   ├── User.php
│   │   ├── Product.php
│   │   ├── Order.php

│   │   └── ...
│   ├── utils/                       # Utilidades
│   │   ├── Logger.php
│   │   ├── Mailer.php
│   │   └── ...
│   ├── api.php                      # Router de API
│   └── index.php                    # Punto entrada backend
│
├── frontend/                        # Aplicación Vue.js (SPA)
│   ├── src/
│   │   ├── components/              # Componentes Vue reutilizables
│   │   │   ├── Navbar.vue
│   │   │   ├── ProductCard.vue
│   │   │   ├── CartDrawer.vue
│   │   │   └── ...
│   │   ├── views/                   # Páginas (rutas)
│   │   │   ├── Home.vue
│   │   │   ├── Products.vue
│   │   │   ├── Dashboard.vue
│   │   │   ├── Login.vue
│   │   │   ├── Wholesale.vue
│   │   │   └── ...
│   │   ├── stores/                  # Estado global (Pinia)
│   │   │   ├── auth.js
│   │   │   ├── cart.js
│   │   │   ├── user.js
│   │   │   └── ...
│   │   ├── assets/
│   │   │   ├── css/                 # Estilos Tailwind/SCSS
│   │   │   ├── img/
│   │   │   └── fonts/
│   │   ├── router/
│   │   │   └── index.js             # Rutas con Vue Router
│   │   ├── App.vue                  # Componente raíz
│   │   └── main.js                  # Punto de entrada Vue
│   ├── public/                      # Assets estáticos
│   ├── package.json                 # Dependencias Node
│   ├── vite.config.js               # Configuración Vite
│   └── index.html                   # HTML template
│
├── db/
│   ├── migrations/                  # Scripts de migración
│   ├── seeds/                       # Datos de ejemplo
│   └── schema.sql                   # Schema inicial
│
├── scripts/
│   ├── init_db.php                  # Inicializar BD
│   └── backup_db.sh                 # Backup automático
│
├── .env.example                     # Variables de entorno
├── .gitignore
├── composer.json                    # Dependencias PHP
├── README.md                        # Documentación
└── nginx.conf                       # Config Nginx (opcional)
```

---

## 🔧 CONFIGURACIÓN INICIAL

### 1. Instalación Básica

```bash
# Crear carpeta del proyecto
mkdir mi-proyecto
cd mi-proyecto

# Inicializar git
git init
git remote add origin https://github.com/usuario/mi-proyecto.git

# Copiar estructura de archivos
# (Crear los archivos base)

# Backend: Instalar dependencias PHP
composer install

# Frontend: Crear proyecto Vue con Vite
cd frontend
npm install

# Compilar frontend para desarrollo
npm run dev

# En otra terminal: Iniciar servidor PHP
cd ../backend
php -S localhost:8000


```

### 2. Variables de Entorno (.env)

```bash
# APP
APP_NAME="Mi Proyecto"
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8088

# DATABASE
DB_HOST=db
DB_PORT=5432
DB_NAME=mi_proyecto
DB_USER=admin_user
DB_PASSWORD=SuperSecurePassword123!

# DATABASE (Desarrollo local - para producción usar servidor remoto)
DB_HOST=localhost
DB_PORT=5432
DB_NAME=mi_proyecto
DB_USER=admin_user
DB_PASSWORD=SuperSecurePassword123!

# MAIL (opcional)
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=xxx
MAIL_PASSWORD=xxx
MAIL_FROM=noreply@proyecto.com

# SECURITY
SESSION_TIMEOUT=3600
BCRYPT_ROUNDS=10
```

### 3. Configuración de PHP (config.php)

```php
<?php
// config/config.php

require_once __DIR__ . '/init_dirs.php';
require_once __DIR__ . '/security.php';

// Base URL
define('BASE_URL', $_ENV['APP_URL'] ?? 'http://localhost:8088');

// Database
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_PORT', $_ENV['DB_PORT'] ?? '5432');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'mi_proyecto');
define('DB_USER', $_ENV['DB_USER'] ?? 'admin_user');
define('DB_PASSWORD', $_ENV['DB_PASSWORD'] ?? 'password');

// Session
session_set_cookie_params([
    'lifetime' => (int)($_ENV['SESSION_TIMEOUT'] ?? 3600),
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Strict'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error handling
if ($_ENV['APP_DEBUG'] ?? false) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
?>
```

---

## 🏗️ COMPONENTES PRINCIPALES

### Modelo Base (Model Pattern)

```php
<?php
// backend/models/BaseModel.php

class BaseModel {
    protected $db;
    protected $table;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function find($id) {
        $query = "SELECT * FROM {$this->table} WHERE id = :id";
        return $this->db->fetch($query, ['id' => $id]);
    }
    
    public function findAll($limit = null, $offset = 0) {
        $query = "SELECT * FROM {$this->table} LIMIT :limit OFFSET :offset";
        return $this->db->fetchAll($query, [
            'limit' => $limit ?? 999999,
            'offset' => $offset
        ]);
    }
    
    public function create($data) {
        $columns = implode(',', array_keys($data));
        $placeholders = implode(',', array_map(fn($k) => ":$k", array_keys($data)));
        $query = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        return $this->db->execute($query, $data);
    }
    
    public function update($id, $data) {
        $sets = implode(',', array_map(fn($k) => "$k = :$k", array_keys($data)));
        $query = "UPDATE {$this->table} SET $sets WHERE id = :id";
        $data['id'] = $id;
        return $this->db->execute($query, $data);
    }
    
    public function delete($id) {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        return $this->db->execute($query, ['id' => $id]);
    }
}
?>
```

### Clase Database

```php
<?php
// backend/config/database.php

class Database {
    private $pdo;
    
    public function __construct() {
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            DB_HOST, DB_PORT, DB_NAME
        );
        
        try {
            $this->pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            die("Database Error: " . $e->getMessage());
        }
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function execute($sql, $params = []) {
        return $this->query($sql, $params)->rowCount();
    }
}
?>
```

### Modelo de Usuario

```php
<?php
// backend/models/User.php

class User extends BaseModel {
    protected $table = 'users';
    
    public function getByEmail($email) {
        $query = "SELECT * FROM {$this->table} WHERE email = :email";
        return $this->db->fetch($query, ['email' => $email]);
    }
    
    public function authenticate($email, $password) {
        $user = $this->getByEmail($email);
        if (!$user) return false;
        
        return password_verify($password, $user['password_hash']) ? $user : false;
    }
    
    public function create($data) {
        // Hash password
        if (isset($data['password'])) {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
            unset($data['password']);
        }
        
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['points'] = 0;
        
        return parent::create($data);
    }
}
?>
```

### Controlador de Autenticación

```php
<?php
// backend/controllers/auth_controller.php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../../config/config.php';

$action = $_POST['action'] ?? null;
$user_model = new User();

if ($action === 'login') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    // Validar CSRF token
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['error'] = 'Token de seguridad inválido';
        header('Location: /views/login.php?error=token');
        exit;
    }
    
    $user = $user_model->authenticate($email, $password);
    
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        
        if ($remember) {
            setcookie('user_remember', $user['id'], time() + (30 * 24 * 60 * 60));
        }
        
        header('Location: /views/dashboard.php');
        exit;
    } else {
        header('Location: /views/login.php?error=Credenciales inválidas');
        exit;
    }
}

if ($action === 'register') {
    $data = [
        'email' => $_POST['email'] ?? '',
        'name' => $_POST['name'] ?? '',
        'password' => $_POST['password'] ?? '',
        'phone' => $_POST['phone'] ?? ''
    ];
    
    // Validaciones
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        die('Email inválido');
    }
    
    if (strlen($data['password']) < 8) {
        die('Contraseña muy corta (mínimo 8 caracteres)');
    }
    
    // Crear usuario
    $user_model->create($data);
    
    header('Location: /views/login.php?success=Registro completado');
    exit;
}

if ($action === 'logout') {
    session_destroy();
    header('Location: /index.php');
    exit;
}
?>
```

---

## 💾 BASE DE DATOS

### Schema SQL Completo

```sql
-- Users table
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role VARCHAR(50) DEFAULT 'customer', -- admin, employee, customer, wholesale_customer
    points INT DEFAULT 0,
    avatar_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE products (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    sku VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    category VARCHAR(100),
    cost_price DECIMAL(10, 2),
    sell_price DECIMAL(10, 2) NOT NULL,
    wholesale_price DECIMAL(10, 2),
    stock INT DEFAULT 0,
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Orders table
CREATE TABLE orders (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    total DECIMAL(10, 2) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending', -- pending, processing, shipped, delivered, cancelled
    payment_status VARCHAR(50) DEFAULT 'pending', -- pending, completed, failed, refunded
    shipping_address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Order items
CREATE TABLE order_items (
    id SERIAL PRIMARY KEY,
    order_id INT REFERENCES orders(id) ON DELETE CASCADE,
    product_id INT REFERENCES products(id),
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Points transactions
CREATE TABLE points_transactions (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    points INT NOT NULL,
    transaction_type VARCHAR(50), -- purchase, redemption, bonus, gift
    reference_id INT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Wholesale requests
CREATE TABLE wholesale_requests (
    id SERIAL PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    contact_email VARCHAR(255) NOT NULL,
    contact_phone VARCHAR(20),
    business_type VARCHAR(100),
    description TEXT,
    status VARCHAR(50) DEFAULT 'pending', -- pending, approved, rejected
    user_id INT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tasks (para empleados)
CREATE TABLE tasks (
    id SERIAL PRIMARY KEY,
    assigned_to INT REFERENCES users(id),
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status VARCHAR(50) DEFAULT 'pending', -- pending, in_progress, completed
    priority VARCHAR(50) DEFAULT 'normal', -- low, normal, high
    due_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes para performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_products_sku ON products(sku);
CREATE INDEX idx_orders_user_id ON orders(user_id);
CREATE INDEX idx_order_items_order_id ON order_items(order_id);
CREATE INDEX idx_points_user_id ON points_transactions(user_id);
```

---

## 🎯 MÓDULOS Y FUNCIONALIDADES

### Módulo 1: Autenticación

**Funcionalidades**:
- ✅ Registro de usuarios
- ✅ Login/Logout
- ✅ Remember me
- ✅ Recuperación de contraseña
- ✅ Two-factor authentication (futuro)

**Archivos**:
- `views/login.php`
- `views/register.php`
- `backend/controllers/auth_controller.php`
- `backend/models/User.php`

### Módulo 2: Catálogo de Productos

**Funcionalidades**:
- ✅ Visualización de productos
- ✅ Búsqueda y filtrado
- ✅ Categorización
- ✅ Comparación de productos
- ✅ Reseñas (futuro)

**Archivos**:
- `views/products.php`
- `backend/models/Product.php`
- `backend/controllers/products_controller.php`

### Módulo 3: Carrito de Compras

**Funcionalidades**:
- ✅ Agregar/quitar items
- ✅ Persistencia en localStorage
- ✅ Cálculo de totales
- ✅ Generación de PDF/Ticket
- ✅ Compartir por WhatsApp

**Archivos**:
- `assets/js/cart.js`
- `public/js/jspdf.umd.min.js`

### Módulo 4: Pedidos

**Funcionalidades**:
- ✅ Crear pedidos
- ✅ Seguimiento de estado
- ✅ Historial de pedidos
- ✅ Impresión/PDF de factura

**Archivos**:
- `views/my_orders.php`
- `views/order_detail.php`
- `backend/models/Order.php`

### Módulo 5: Sistema de Puntos

**Funcionalidades**:
- ✅ Acumulación de puntos (1 punto = $1)
- ✅ Redención de puntos
- ✅ Historial de transacciones
- ✅ Bonos por cumpleaños

**Archivos**:
- `views/my_points.php`
- `backend/models/PointsTransaction.php`

### Módulo 6: Mayoreo

**Funcionalidades**:
- ✅ Solicitud de mayoreo
- ✅ Aprobación de vendors
- ✅ Precios mayoristas
- ✅ Cotizaciones personalizadas

**Archivos**:
- `views/wholesale.php`
- `backend/models/WholesaleSale.php`

### Módulo 7: Panel Administrativo

**Funcionalidades**:
- ✅ Dashboard con KPIs
- ✅ Gestión de productos (CRUD)
- ✅ Gestión de pedidos
- ✅ Gestión de usuarios
- ✅ Reportes

**Archivos**:
- `admin/dashboard.php`
- `admin/products/` (crud)
- `admin/orders/`
- `admin/users/`

---

## ✅ CHECKLIST DE IMPLEMENTACIÓN

### Fase 1: Setup Inicial (Semana 1)

- [ ] Crear repositorio Git
- [ ] Configurar Docker/Docker Compose
- [ ] Crear estructura de carpetas
- [ ] Configurar base de datos (PostgreSQL)
- [ ] Setup de config.php, security.php
- [ ] Crear clases base (Database, BaseModel)
- [ ] Pruebas de conexión a BD
- [ ] Instalar dependencias (npm, composer)
- [ ] Configurar servidor local (PHP + Nginx/Apache)
- [ ] Crear estructura de carpetas
**Tiempo estimado**: 2-3 días

### Fase 2: Autenticación (Semana 1-2)

- [ ] Crear tabla de usuarios
- [ ] Implementar modelo User
- [ ] Crear vistas login/register
- [ ] Controlador de autenticación
- [ ] Validación de forms
- [ ] Testing de flujo auth
- [ ] Session management

**Tiempo estimado**: 2-3 días

### Fase 3: Frontend Base (Semana 2)

- [ ] Crear navbar responsivo
- [ ] Página de inicio (hero, features)
- [ ] Footer
- [ ] Sistema de colores y tipografía
- [ ] Responsive design (mobile-first)
- [ ] Toggle tema oscuro/claro
- [ ] Testing en diferentes dispositivos

**Tiempo estimado**: 3-4 días

### Fase 4: Catálogo de Productos (Semana 3)

- [ ] Crear tabla de productos
- [ ] Modelo y controlador Product
- [ ] Vista de catálogo
- [ ] Sistema de filtros
- [ ] Búsqueda
- [ ] Grid responsivo
- [ ] Imágenes de productos

**Tiempo estimado**: 3-4 días

### Fase 5: Carrito y Checkout (Semana 3-4)

- [ ] Implementar carrito en localStorage
- [ ] Funciones add/remove/update
- [ ] Drawer lateral del carrito
- [ ] Integración con JSPDF
- [ ] Integración con WhatsApp API
- [ ] Validación de checkout

**Tiempo estimado**: 3-4 días

### Fase 6: Dashboard de Usuario (Semana 4)

- [ ] Crear tabla orders/order_items
- [ ] Vistas: dashboard, my_orders, order_detail
- [ ] Flujo de creación de orden
- [ ] Seguimiento de pedidos
- [ ] Perfil de usuario

**Tiempo estimado**: 3-4 días

### Fase 7: Sistema de Puntos (Semana 5)

- [ ] Crear tablas de puntos
- [ ] Lógica de acumulación
- [ ] Vista de puntos
- [ ] Redención
- [ ] Bonus por cumpleaños

**Tiempo estimado**: 2-3 días

### Fase 8: Panel Administrativo (Semana 5-6)

- [ ] Crear estructura admin/
- [ ] Dashboard con analítica
- [ ] CRUD de productos
- [ ] CRUD de pedidos
- [ ] Gestión de usuarios
- [ ] Reportes

**Tiempo estimado**: 5-7 días

### Fase 9: Módulo Mayorista (Semana 6)

- [ ] Crear tablas wholesale
- [ ] Formulario de solicitud
- [ ] Flujo de aprobación
- [ ] Vistas de mayorista
- [ ] Precios especiales

**Tiempo estimado**: 2-3 días

### Fase 10: Testing y Deploy (Semana 7)

- [ ] Unit tests (básicos)
- [ ] Testing de seguridad
- [ ] Performance testing
- [ ] UX testing
- [ ] Setup de producción
- [ ] Deploy en servidor

**Tiempo estimado**: 3-5 días

---

## 📊 ESTIMACIÓN TOTAL

**Desarrollo base**: 4-5 semanas (1 desarrollador full-time)  
**Con testing exhaustivo**: 6-7 semanas  
**Con refinamientos y mejoras**: 8-10 semanas

---

## 🚀 DEPLOYMENT

### Producción con Docker

```dockerfile
# Dockerfile
FROM php:8.0-apache

RUN docker-php-ext-install pdo_pgsql
RUN a2enmod rewrite

COPY . /var/www/html
WORKDIR /var/www/html

ENV AUTO_DB_INIT=true
EXPOSE 80
```

### Docker Compose Producción

```yaml
version: '3.9'
services:
  web:
    build: .
    ports:
      - "80:80"
    environment:
      APP_ENV: production
      DB_HOST: db
    depends_on:
      - db
  db:
    image: postgres:15
    environment:
      POSTGRES_PASSWORD: SecurePass123!
    volumes:
      - db_data:/var/lib/postgresql/data
volumes:
  db_data:

### Producción en Servidor Tradicional

#### Paso 1: Build del Frontend Vue

```bash
cd frontend
npm run build  # Genera dist/ con archivos estáticos
```

#### Paso 2: Copiar archivos a servidor

```bash
# Copiar backend a /var/www/backend
scp -r backend/* usuario@servidor:/var/www/backend/

# Copiar frontend compilado a /var/www/frontend/public
scp -r frontend/dist/* usuario@servidor:/var/www/frontend/public/
```

#### Paso 3: Configurar Nginx/Apache

**Nginx (recomendado)**:
```nginx
server {
    listen 80;
    server_name tunombre.com;

    # Frontend (SPA)
    location / {
        root /var/www/frontend/public;
        try_files $uri $uri/ /index.html;
    }

    # Backend API
    location /api/ {
        alias /var/www/backend/;
        try_files $uri $uri/ /api.php?$query_string;
    }

    # PHP-FPM para backend
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index api.php;
        include fastcgi_params;
    }
}
```

**Apache**:
```apache
<VirtualHost *:80>
    ServerName tunombre.com
    DocumentRoot /var/www/frontend/public

    <Directory /var/www/frontend/public>
        RewriteEngine On
        RewriteBase /
        RewriteRule ^index\.html$ - [L]
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule . /index.html [L]
    </Directory>

    ProxyPass /api http://localhost:8000/api
    ProxyPassReverse /api http://localhost:8000/api
</VirtualHost>
```

#### Paso 4: Configurar BD

```bash
# En servidor de BD remoto
psql -U postgres -c "CREATE DATABASE mi_proyecto;"
psql -U postgres -d mi_proyecto < db/schema.sql
```

#### Paso 5: Variables de entorno

```bash
# Backend .env
DB_HOST=servidor-bd.com
DB_NAME=mi_proyecto
DB_USER=usuario_db
DB_PASSWORD=contraseña_segura
APP_ENV=production
APP_DEBUG=false
```

---

## 🔐 Checklist de Seguridad

- [ ] HTTPS obligatorio en producción
- [ ] Headers de seguridad configurados
- [ ] CSRF tokens en todos los forms
- [ ] Input sanitization
- [ ] Prepared statements (PDO)
- [ ] Password hashing (bcrypt)
- [ ] Rate limiting en login
- [ ] Logging de eventos críticos
- [ ] Backup automático de BD
- [ ] Environment variables protegidas

---

## 📈 Mejoras Futuras

1. **Pasarela de Pagos**: Stripe, PayPal, MercadoPago
2. **API REST**: GraphQL o REST mejorado
3. **App Móvil**: React Native, Flutter
4. **AI/ML**: Recomendaciones, predicción de demanda
5. **Integraciones**: ERP, CRM, Email marketing
6. **Performance**: Redis cache, CDN
7. **Escalabilidad**: Microservicios, Load balancing

---

**Documento preparado**: 2026-05-09  
**Versión**: 1.0  
**Próxima revisión**: 2026-08-09
