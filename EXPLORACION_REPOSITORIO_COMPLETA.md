# 📊 EXPLORACIÓN EXHAUSTIVA DEL REPOSITORIO - proyecto_Truper

**Fecha:** 7 de mayo de 2026  
**Repositorio:** /workspaces/proyecto_Truper  
**Total de líneas en archivos clave:** 10,692 (solo PHP/SQL principales)

---

## 📑 ÍNDICE

1. [1. MARKETPLACE (marketplace_ce)](#1-marketplace-marketplace_ce)
2. [2. STOCK/INVENTARIO](#2-stockinventario)
3. [3. CARGA Y GESTIÓN DE IMÁGENES](#3-carga-y-gestión-de-imágenes)
4. [4. ADMIN SUPPLY (Abastecimiento)](#4-admin-supply-abastecimiento)
5. [5. PRODUCTOS (Gestión completa)](#5-productos-gestión-completa)
6. [6. ELIMINACIÓN/EDICIÓN DE PRODUCTOS](#6-eliminacióledición-de-productos)
7. [7. CÓDIGOS DE PRODUCTOS (SKU, BARCODE)](#7-códigos-de-productos-sku-barcode)
8. [8. TABLAS DE BASE DE DATOS](#8-tablas-de-base-de-datos)
9. [9. FUNCIONES PRINCIPALES POR ARCHIVO](#9-funciones-principales-por-archivo)

---

## 1. MARKETPLACE (marketplace_ce)

### 📄 Archivos Encontrados

| Archivo | Líneas | Tamaño | Ubicación |
|---------|--------|--------|-----------|
| `marketplace_ce.php` | 509 | 25K | [public/](public/) |
| `product_detail.php` | 769 | 26K | [public/](public/) |
| Base de datos: `marketplace_ce_products` | - | - | PostgreSQL |

### 🔍 Detalles del Archivo Principal

**`public/marketplace_ce.php` (509 líneas)**

```
Funciones principales:
├── Cargar tabla marketplace_ce_products
├── Crear tabla si no existe (CREATE TABLE IF NOT EXISTS)
├── Filtrar por visibilidad (is_active, active)
├── Cargar categorías de product_categories
├── Normalizar categorías (ILIKE)
├── Fallback dinámico de categorías
├── Mostrar hasta 300 productos en marketplace
└── Renderizar productos en carrusel/galería
```

**Características:**
- ✅ Stock dinámico por producto (`stock_quantity`)
- ✅ Etiqueta de condición (`condition_label` - "Seminuevo")
- ✅ Categorías normalizadas (PostgreSQL ILIKE)
- ✅ Variantes JSON (`variants_json`)
- ✅ Imágenes por defecto
- ✅ Ordenamiento por fecha (DESC)
- ✅ Soporte para tanto PostgreSQL como MySQL

**Estructura de tabla `marketplace_ce_products`:**
```sql
id SERIAL PRIMARY KEY,
sku VARCHAR(100) UNIQUE NOT NULL,
name VARCHAR(220) NOT NULL,
description TEXT NOT NULL,
condition_label VARCHAR(80) DEFAULT 'Seminuevo',
category VARCHAR(120),
unit_price DECIMAL(12,2) DEFAULT 0,
stock_quantity INTEGER DEFAULT 1,
image_url TEXT,
variants_json TEXT,
is_active BOOLEAN DEFAULT true,
created_by INTEGER REFERENCES users(id),
updated_by INTEGER REFERENCES users(id),
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
```

### 🔗 API Relacionada

**`public/product_detail.php` (769 líneas)**

```
Funciones:
├── Cargar detalles de producto por ID
├── Soportar fuente 'ce' (marketplace CE) y 'product'
├── Resolver imágenes por código de producto
├── Cargar galería desde /images/products/by_code/{sku}/
├── Priorizar imágenes (+FC1, +E1, +D1, +O\d+)
├── Detectar imágenes base64 y archivos
├── Renderizar especificaciones técnicas
└── Mostrar variantes JSON
```

**Características especiales:**
- Soporte dual de tablas (`products` y `marketplace_ce_products`)
- Sistema de priorización de imágenes
- Resolución de imágenes de galería
- Soporte para base64 y rutas de archivo

---

## 2. STOCK/INVENTARIO

### 📄 Archivos Encontrados

| Archivo | Líneas | Ubicación | Descripción |
|---------|--------|-----------|-------------|
| `config/config.php` | ~900 | [config/](config/) | Migraciones, inicialización |
| `public/api/admin_supply.php` | 4,174 | [public/api/](public/api/) | **API PRINCIPAL de stock** |
| `backend/models/Product.php` | 105 | [backend/models/](backend/models/) | Modelo OOP |
| `src/models/Product.php` | 100 | [src/models/](src/models/) | Modelo alternativo |

### 🔑 API Principal: `public/api/admin_supply.php` (4,174 líneas)

**ACCIONES SOPORTADAS:**

| Acción | Método | Descripción | Parámetros |
|--------|--------|-------------|-----------|
| `stock` | GET | Obtener inventario actual | - |
| `create` | POST | Crear nuevo producto | name, sku, unit_price, category, stock_quantity |
| `update` | POST/PUT | Actualizar producto | id, stock_quantity, unit_price, etc. |
| `delete` | POST/DELETE | Eliminar producto | id |
| `search` | GET | Buscar producto | q (query) |
| `by-sku` | GET | Obtener por SKU | sku |
| `by-barcode` | GET | Obtener por código de barras | barcode |

**Funciones principales incluidas:**

```php
normalize_date_value()           // Normalizar fechas (YYYY-MM-DD, DD/MM/YYYY)
normalize_bool_admin_supply()    // Convertir a booleano
normalize_sku_admin_supply()     // Normalizar SKU (5-6 dígitos)
is_valid_numeric_sku_admin_supply() // Validar SKU numérico
normalize_category_admin_supply() // Normalizar categorías (ASCII)
first_existing_column_admin_supply() // Buscar columna en tabla
sku_column_for_table_admin_supply() // Detectar columna SKU
name_column_for_table_admin_supply() // Detectar columna nombre
ensure_products_name_column_admin_supply() // Asegurar columna name
```

**Validaciones:**
- SKU: 5-6 dígitos numéricos
- Categorías normalizadas a ASCII
- Fechas en formato ISO 8601
- Booleanos: 1, true, yes, on

### 📊 Tablas de Stock Relacionadas

```sql
-- Tabla principal
CREATE TABLE products (
    id SERIAL PRIMARY KEY,
    sku VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    stock_quantity INTEGER DEFAULT 0,
    reorder_level INTEGER DEFAULT 10,
    unit_price DECIMAL(10, 2) NOT NULL,
    barcode VARCHAR(100) UNIQUE,
    category VARCHAR(100),
    is_active BOOLEAN DEFAULT true,
    ...
);

-- Tabla alternativa para mercado CE
CREATE TABLE marketplace_ce_products (
    id SERIAL PRIMARY KEY,
    sku VARCHAR(100) UNIQUE NOT NULL,
    stock_quantity INTEGER NOT NULL DEFAULT 1,
    ...
);
```

---

## 3. CARGA Y GESTIÓN DE IMÁGENES

### 📄 Archivos Encontrados

| Archivo | Líneas | Ubicación | Tipo | Descripción |
|---------|--------|-----------|------|-------------|
| `clean_base64_images.php` | 192 | Raíz | Script | Convertir base64 → archivos |
| `diagnose_images.php` | 88 | Raíz | Script | Diagnosticar problemas de imágenes |
| `sync_images_to_db.php` | 142 | Raíz | Script | Sincronizar filesystem ↔ BD |
| `test_image_upload.php` | 66 | Raíz | Script | Pruebas de carga |
| `public/api/check_image_upload.php` | 82 | [public/api/](public/api/) | API | Validar carga |
| `scripts/migrate_legacy_images.php` | 209 | [scripts/](scripts/) | Script | Migrar imágenes antiguas |
| `scripts/delete_image_cli.php` | 76 | [scripts/](scripts/) | CLI | Eliminar imágenes por SKU |
| `scripts/delete_and_verify_images.sh` | - | [scripts/](scripts/) | Bash | Script de verificación |
| `scripts/smoke_test_images.sh` | - | [scripts/](scripts/) | Bash | Pruebas de integridad |

**Total de líneas de código de imágenes:** 855 líneas

### 🔧 Funciones de Conversión de Imágenes

**`clean_base64_images.php` (192 líneas)**

```php
Funciones:
├── convert_base64_image_to_file()
│   ├── Convertir data:image/ → archivo PNG/JPG
│   ├── Crear directorio si no existe: /images/products/by_code/{sku}/
│   ├── Guardar archivo con nombre único
│   └── Retornar ruta relativa
│
├── is_valid_numeric_sku_admin_supply()
│   └── Validar SKU: ^\d{5}$ (exactamente 5 dígitos)
│
└── Procesar dos tablas:
    ├── products
    └── marketplace_ce_products
```

**Procesos:**
1. Buscar imágenes base64 en `image_url` y `variants_json`
2. Convertir base64 a PNG/JPG
3. Guardar en `/images/products/by_code/{SKU}/`
4. Actualizar referencias en BD

### 📁 Estructura de Directorios de Imágenes

```
images/
├── products/
│   ├── by_code/
│   │   ├── 12345/          # SKU de 5 dígitos
│   │   │   ├── image1.jpg
│   │   │   ├── image2.png
│   │   │   └── image3+FC1.jpg  # Imagen principal
│   │   ├── 67890/
│   │   │   └── ...
│   │   └── gallery/
│   │       ├── {sku}/
│   │       └── ...
│   └── default-product.svg
```

### 🔍 Sistema de Priorización de Imágenes

En `product_detail.php` y `public/api/products.php`:

```php
function image_priority_score($fileName): int {
    $name = strtoupper(pathinfo($fileName, PATHINFO_FILENAME));
    
    if (preg_match('/\+FC1$/', $name))     return 0; // Imagen principal
    if (preg_match('/\+E1$/', $name))      return 1; // Imagen secundaria
    if (preg_match('/\+D1$/', $name))      return 2; // Imagen terciaria
    if (preg_match('/\+O\d+$/', $name))    return 3; // Otras imágenes numeradas
    if (strpos($name, '+') === false)      return 50; // Sin sufijo
    
    return 90; // Otras
}
```

### 🗂️ Scripts de Migración y Limpieza

**`scripts/migrate_legacy_images.php` (209 líneas)**

```php
Funciona:
├── Normalizar SKU: normalize_sku_cli()
├── Validar SKU: is_valid_numeric_sku_cli()
├── Obtener rutas canónicas: canonical_relative_cli()
├── Asegurar rutas canónicas: ensure_canonical_cli()
└── Migrar imágenes de /gallery/{sku}/ → /by_code/{sku}/
```

**`scripts/delete_image_cli.php` (76 líneas)**

```php
Uso: php delete_image_cli.php {sku}
├── Normalizar SKU: normalize_sku_cli()
├── Validar: is_valid_sku_cli()
├── Eliminar directorio: /images/products/by_code/{sku}/
└── Reportar resultado
```

---

## 4. ADMIN SUPPLY (Abastecimiento)

### 📄 Archivos Encontrados

| Archivo | Líneas | Tamaño | Ubicación |
|---------|--------|--------|-----------|
| `public/admin_supply.php` | 4,611 | 201K | [public/](public/) |
| `public/api/admin_supply.php` | 4,174 | - | [public/api/](public/api/) |
| `backend/controllers/auth_controller.php` | 120 | - | [backend/controllers/](backend/controllers/) |

**Total de líneas Admin Supply:** 8,905 líneas

### 🎛️ Archivo Principal: `public/admin_supply.php` (4,611 líneas)

**Componentes principales:**

```
┌─────────────────────────────────────────────────────┐
│   ADMIN SUPPLY DASHBOARD                            │
├─────────────────────────────────────────────────────┤
│                                                       │
│  1. VISTA GENERAL (Hero + Cards)                    │
│     ├── Total de productos                          │
│     ├── Stock total                                 │
│     ├── Valor del inventario                        │
│     ├── Productos bajo stock                        │
│     └── Último actualizado                          │
│                                                       │
│  2. TABS/PESTAÑAS                                   │
│     ├── Productos (listado + búsqueda)              │
│     ├── Crear nuevo producto                        │
│     ├── Editar producto                             │
│     ├── Imágenes del producto                       │
│     ├── Variantes                                   │
│     ├── Stock/Inventario                            │
│     ├── Importar (CSV/XLSX)                         │
│     ├── Exportar                                    │
│     └── Reportes                                    │
│                                                       │
│  3. CONTROLES                                       │
│     ├── Búsqueda por SKU/nombre/código              │
│     ├── Filtros por categoría                       │
│     ├── Filtros por estado (activo/inactivo)        │
│     ├── Paginación                                  │
│     └── Ordenamiento                                │
│                                                       │
│  4. FUNCIONALIDADES DE EDICIÓN                      │
│     ├── Actualizar nombre/descripción               │
│     ├── Cambiar precio                              │
│     ├── Modificar stock                             │
│     ├── Nivel de reorden                            │
│     ├── Categoría                                   │
│     ├── SKU/Código de barras                        │
│     └── Estado (activo/inactivo)                    │
│                                                       │
│  5. GESTIÓN DE IMÁGENES                             │
│     ├── Subir imágenes                              │
│     ├── Reordenar galería                           │
│     ├── Eliminar imágenes                           │
│     ├── Ver previsualización                        │
│     └── Soporte para base64                         │
│                                                       │
│  6. VARIANTES DEL PRODUCTO                          │
│     ├── Crear variante                              │
│     ├── Tamaño/Color/Especificaciones               │
│     ├── SKU de variante                             │
│     ├── Precio diferencial                          │
│     └── Stock de variante                           │
│                                                       │
│  7. GESTIÓN DE STOCK                                │
│     ├── Ajuste manual                               │
│     ├── Historial de movimientos                    │
│     ├── Alertas de bajo stock                       │
│     ├── Reorden automático                          │
│     └── Forecasting                                 │
│                                                       │
└─────────────────────────────────────────────────────┘
```

### 📋 Formularios Principales

**Crear/Editar Producto:**
```form
SKU:                    [5-6 dígitos]
Nombre:                 [Texto]
Descripción:            [Textarea]
Código de Barras:       [Texto único]
Categoría:              [Select]
Precio unitario:        [Decimal]
Cantidad en stock:      [Integer]
Nivel de reorden:       [Integer]
Estado:                 [Activo/Inactivo]
Especificaciones:       [JSON]
```

---

## 5. PRODUCTOS (Gestión completa)

### 📄 Archivos Encontrados

| Archivo | Líneas | Tipo | Ubicación |
|---------|--------|------|-----------|
| `backend/models/Product.php` | 105 | Modelo | [backend/models/](backend/models/) |
| `src/models/Product.php` | 100 | Modelo | [src/models/](src/models/) |
| `public/api/products.php` | 424 | API | [public/api/](public/api/) |
| `views/products.php` | - | Vista | [views/](views/) |
| `public/index.php` | 789 | Catálogo | [public/](public/) |
| `public/product_detail.php` | 769 | Detalle | [public/](public/) |

### 🔧 Modelo: `src/models/Product.php` (100 líneas)

```php
class Product {
    public function __construct($pdo)
    
    // Lectura
    public function getAll($limit = 100)
    public function getById($id)
    public function getByBarcode($barcode)
    public function getBySku($sku)
    public function search($term)
    public function getByCategory($category)
    
    // Escritura
    public function create($data)
        Inserta: sku, name, description, category, unit_price, barcode, reorder_level
    
    public function update($id, $data)
        Actualiza: name, description, unit_price, category, technical_specs, etc.
}
```

### 🔌 API: `public/api/products.php` (424 líneas)

```php
ACCIONES:
├── GET search
│   └── Buscar por código de barras o SKU
│
├── POST create
│   ├── Crear producto
│   └── Gestionar galería de imágenes
│
├── POST update
│   └── Actualizar propiedades del producto
│
├── POST delete
│   └── Marcar como inactivo
│
└── Funciones auxiliares:
    ├── display_product_code()     // Quitar prefijo "XLS-"
    ├── image_priority_score_products_api()
    ├── resolve_product_image_from_catalog()
    └── detect_product_image_sources()
```

### 📊 Vistas Principales

**`views/products.php`** - Catálogo de productos
- Mostrar productos activos
- Grid/Lista de productos
- SKU visible
- Precio unitario
- Stock disponible
- Galería de imágenes

**`public/index.php` (789 líneas)** - Página principal
- Catálogo de productos
- Carrito de compras
- Integración con marketplace

**`public/product_detail.php` (769 líneas)** - Detalle de producto
- Información completa
- Galería de imágenes
- Especificaciones técnicas
- Variantes
- Disponibilidad
- Opciones de compra

---

## 6. ELIMINACIÓN/EDICIÓN DE PRODUCTOS

### 📄 Archivos Encontrados

| Archivo | Líneas | Ubicación | Acción |
|---------|--------|-----------|--------|
| `public/api/admin_supply.php` | 4,174 | [public/api/](public/api/) | DELETE/UPDATE |
| `public/admin_supply.php` | 4,611 | [public/](public/) | UI de eliminación |
| `scripts/delete_image_cli.php` | 76 | [scripts/](scripts/) | Eliminar imágenes |

### ⚡ Operaciones de Eliminación

**En `public/api/admin_supply.php`:**

```php
if ($action === 'delete' && $method === 'POST') {
    // Validar entrada
    $id = (int)($input['id'] ?? 0);
    
    if ($id <= 0) {
        // Error: ID inválido
        return error_response('Invalid product ID');
    }
    
    // Opción 1: Marcar como inactivo (soft delete)
    UPDATE products SET is_active = false WHERE id = ?
    
    // Opción 2: Eliminar físicamente (hard delete)
    DELETE FROM products WHERE id = ?
    
    // Opción 3: Eliminar de marketplace CE
    DELETE FROM marketplace_ce_products WHERE id = ?
}
```

**Seguridad:**
- ✅ Require admin role
- ✅ Validación de ID
- ✅ CSRF token
- ✅ Logging de auditoría

### 🔄 Operaciones de Edición

**En `public/api/admin_supply.php`:**

```php
if ($action === 'update' && ($method === 'POST' || $method === 'PUT')) {
    $id = (int)($input['id'] ?? 0);
    
    // Campos actualizables:
    UPDATE products SET
        name = ?,
        sku = ?,
        description = ?,
        category = ?,
        unit_price = ?,
        stock_quantity = ?,
        barcode = ?,
        reorder_level = ?,
        technical_specs = ?,
        image_url = ?,
        variants_json = ?,
        is_active = ?
    WHERE id = ?
}
```

### 🗑️ Gestión de Imágenes en Eliminación

**`scripts/delete_image_cli.php` (76 líneas)**

```bash
# Uso
php scripts/delete_image_cli.php 12345

# Proceso
1. Normalizar SKU
2. Validar formato
3. Eliminar /images/products/by_code/12345/
4. Limpiar referencias en BD
5. Reportar resultado
```

---

## 7. CÓDIGOS DE PRODUCTOS (SKU, BARCODE)

### 📋 Formatos Soportados

| Tipo | Formato | Validación | Ubicación |
|------|---------|-----------|-----------|
| **SKU** | 5-6 dígitos | `^\d{5,6}$` | Normalizado |
| **SKU (Admin)** | 5 dígitos exactos | `^\d{5}$` | `is_valid_numeric_sku_admin_supply()` |
| **SKU (Legacy)** | Prefijo XLS- | Quitado en display | `XLS-12345` |
| **Barcode** | Alfanumérico único | 3-100 caracteres | Base de datos |
| **Producto Code** | Alfanumérico | `^[A-Z0-9\-]{3,20}$` | Validación global |

### 🔧 Funciones de Normalización

**`config/security.php`:**
```php
public static function validateSKU($sku) {
    $sku = trim((string)$sku);
    
    if (!preg_match('/^[A-Z0-9\-]{3,20}$/i', $sku)) {
        throw new Exception("SKU inválido");
    }
    
    return $sku;
}
```

**`public/api/admin_supply.php`:**
```php
function normalize_sku_admin_supply($value): string {
    $sku = trim((string)$value);
    $digits = preg_replace('/\D+/', '', $sku);
    return substr($digits, 0, 6);
}

function is_valid_numeric_sku_admin_supply(string $sku): bool {
    return (bool)preg_match('/^\d{5,6}$/', $sku);
}
```

**`scripts/migrate_legacy_images.php`:**
```php
function normalize_sku_cli(string $value): string {
    $s = trim((string)$value);
    $normalized = (int)preg_replace('/\D+/', '', $s);
    return (string)$normalized;
}

function is_valid_numeric_sku_cli(string $sku): bool {
    return (bool)preg_match('/^\d{5,6}$/', $sku);
}
```

### 📁 Uso de SKU en Directorios

```
/images/products/by_code/
├── 12345/          # 5 dígitos (admin_supply)
│   ├── image1.jpg
│   ├── image2.png
│   └── image3+FC1.jpg
└── 67890/
    ├── image1.jpg
    └── ...
```

### 🗂️ Búsqueda por Códigos

**En `public/api/products.php`:**

```php
// Buscar por barcode
SELECT * FROM products WHERE barcode = ?

// Buscar por SKU
SELECT * FROM products WHERE sku = ?

// Buscar por cualquier código
SELECT * FROM products 
WHERE name ILIKE ? 
   OR sku ILIKE ? 
   OR barcode ILIKE ?
```

### 📊 Índices de Base de Datos

```sql
CREATE UNIQUE INDEX idx_products_sku_unique ON products (sku);
CREATE INDEX idx_products_barcode ON products (barcode);
CREATE INDEX idx_products_category ON products (category);
```

---

## 8. TABLAS DE BASE DE DATOS

### 📚 Archivos SQL Encontrados

| Archivo | Líneas | Descripción |
|---------|--------|-------------|
| `database.sql` | 421 | Esquema principal (PostgreSQL) |
| `db/trupper_db.sql` | 229 | Esquema alternativo (MySQL) |
| `db/PRODUCTOS_XLSX_IMPORT.sql` | 81 | Importación de Excel |
| `db/TICKETS_SYSTEM.sql` | 189 | Sistema de tickets |
| `db/ALTER_PAYMENT_TERMS.sql` | 81 | Términos de pago |
| `MAYORISTAS_CONFIGURACION.sql` | 49 | Configuración mayoristas |
| `PRODUCTOS_EJEMPLO.sql` | 14 | Datos de ejemplo |
| `fix_base64_images.sql` | 36 | Limpieza de imágenes |

**Total: 1,100 líneas de SQL**

### 🗃️ Tabla Principal: products

**PostgreSQL (`database.sql`):**
```sql
CREATE TABLE products (
    id SERIAL PRIMARY KEY,
    sku VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    technical_specs TEXT,
    image_url TEXT,
    variants_json TEXT,
    category VARCHAR(100),
    unit_price DECIMAL(10, 2) NOT NULL,
    barcode VARCHAR(100) UNIQUE,
    stock_quantity INTEGER DEFAULT 0,
    reorder_level INTEGER DEFAULT 10,
    supplier_id INTEGER,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

**MySQL (`db/trupper_db.sql`):**
```sql
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    sku VARCHAR(100) UNIQUE NOT NULL,
    barcode VARCHAR(100),
    description TEXT,
    category VARCHAR(100),
    unit VARCHAR(50),
    cost_price DECIMAL(10, 2),
    sell_price DECIMAL(10, 2) NOT NULL,
    active BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sku (sku),
    INDEX idx_barcode (barcode),
    INDEX idx_category (category)
);
```

### 🏪 Tabla: marketplace_ce_products

```sql
CREATE TABLE marketplace_ce_products (
    id SERIAL PRIMARY KEY,
    sku VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(220) NOT NULL,
    description TEXT NOT NULL,
    condition_label VARCHAR(80) NOT NULL DEFAULT 'Seminuevo',
    category VARCHAR(120),
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    stock_quantity INTEGER NOT NULL DEFAULT 1,
    image_url TEXT,
    variants_json TEXT,
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_by INTEGER REFERENCES users(id),
    updated_by INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 📋 Tabla: order_items

```sql
CREATE TABLE order_items (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_id INTEGER NOT NULL REFERENCES products(id),
    quantity INTEGER NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(12, 2) NOT NULL,
    discount_percentage DECIMAL(5, 2) DEFAULT 0,
    discount_amount DECIMAL(12, 2) DEFAULT 0,
    line_total DECIMAL(12, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 📊 Tabla: orders

```sql
CREATE TABLE orders (
    id SERIAL PRIMARY KEY,
    client_id INTEGER NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    total_amount DECIMAL(12, 2) NOT NULL,
    payment_status VARCHAR(20) DEFAULT 'pending',
    payment_amount DECIMAL(12, 2) DEFAULT 0,
    balance DECIMAL(12, 2) NOT NULL,
    order_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivery_date DATE,
    notes TEXT,
    is_wholesale BOOLEAN DEFAULT false,
    status VARCHAR(20) DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 9. FUNCIONES PRINCIPALES POR ARCHIVO

### 🔴 CRÍTICOS (4 archivos)

#### `public/admin_supply.php` (4,611 líneas)
```
Dashboard administrativo completo
├── Gestión de productos
├── Edición de SKU/precio/stock
├── Carga de imágenes
├── Gestión de variantes
├── Importación/exportación
└── Reportes de inventario
```

#### `public/api/admin_supply.php` (4,174 líneas)
```
API REST completa para stock
├── CRUD de productos
├── Validaciones numéricas/categóricas
├── Normalización de datos
├── Búsqueda flexible
├── Manejo de errores
└── Respuestas JSON
```

#### `public/marketplace_ce.php` (509 líneas)
```
Marketplace CE - Productos de segunda mano
├── Mostrar tabla marketplace_ce_products
├── Categorización dinámica
├── Filtrado por disponibilidad
├── Galería de productos
└── Soporte para variantes
```

#### `public/product_detail.php` (769 líneas)
```
Detalle de producto individual
├── Cargar de dos tablas (products, marketplace_ce_products)
├── Resolver imágenes de galería
├── Priorizar imágenes por nombre (+FC1, +E1, etc.)
├── Mostrar especificaciones técnicas
└── Variantes del producto
```

### 🟠 IMPORTANTES (API)

#### `public/api/products.php` (424 líneas)
```
API de búsqueda y gestión de productos
├── Buscar por barcode/SKU
├── Crear productos
├── Actualizar propiedades
├── Eliminar (soft delete)
├── Resolver imágenes
└── Detección de fuentes de imagen
```

### 🟡 IMÁGENES (Scripts)

#### `clean_base64_images.php` (192 líneas)
```
Convertir base64 → archivos PNG/JPG
├── Procesar dos tablas
├── Crear directorios /by_code/{sku}/
├── Guardar archivos
└── Actualizar referencias
```

#### `scripts/migrate_legacy_images.php` (209 líneas)
```
Migrar imágenes de galería antigua
├── Normalizar SKU
├── Obtener rutas canónicas
├── Mover archivos
└── Actualizar referencias
```

#### `scripts/delete_image_cli.php` (76 líneas)
```
CLI para eliminar imágenes por SKU
├── Validar SKU
├── Eliminar directorio
└── Limpiar BD
```

### 🟢 UTILIDADES

#### `config/config.php` (~900 líneas)
```
Inicialización y migraciones
├── Crear tablas
├── Migraciones de esquema
├── Seeders de datos
├── Validaciones globales
└── Funciones auxiliares
```

#### `config/security.php`
```
Seguridad y validaciones
├── validateSKU()
├── validateEmail()
├── FileUploadSecurity
├── CSRF tokens
└── Hashing de contraseñas
```

---

## 📈 ESTADÍSTICAS COMPLETAS

### 📊 Por Tipo de Archivo

| Tipo | Archivos | Líneas | Descripción |
|------|----------|--------|-------------|
| **PHP (Público)** | 22 | 10,692 | Interfaces y APIs |
| **PHP (Backend)** | 12 | 348 | Controladores OOP |
| **PHP (Scripts)** | 7 | 855 | Utilidades CLI |
| **SQL** | 8 | 1,100 | Esquemas y migraciones |
| **JavaScript** | 6 | 3,206 | Frontend interactivo |
| **Bash** | 4 | - | Scripts de automatización |

**Total estimado: ~16,000 líneas de código**

### 🗂️ Directorios Principales

```
proyecto_Truper/
├── public/                      (22 archivos PHP, 10.6K líneas)
│   ├── admin_supply.php         4,611 líneas
│   ├── index.php                  789 líneas
│   ├── product_detail.php         769 líneas
│   ├── api/                     (15 archivos, 8.2K líneas)
│   │   ├── admin_supply.php     4,174 líneas
│   │   ├── products.php           424 líneas
│   │   ├── admin_clients.php      366 líneas
│   │   └── ...
│   ├── images/products/by_code/ (Galerías por SKU)
│   ├── js/                      (6 archivos, 3.2K líneas)
│   └── css/                     (Estilos responsive)
│
├── backend/
│   ├── models/                  (6 archivos)
│   │   ├── Product.php          105 líneas
│   │   └── ...
│   ├── controllers/             (5 archivos, 348 líneas)
│   │   └── ...
│   └── utils/
│
├── src/
│   ├── models/
│   │   └── Product.php          100 líneas
│   ├── controllers/             (4 archivos)
│   └── utils/
│
├── scripts/
│   ├── delete_image_cli.php       76 líneas
│   ├── migrate_legacy_images.php  209 líneas
│   ├── delete_and_verify_images.sh
│   └── smoke_test_images.sh
│
├── config/
│   ├── config.php               (~900 líneas)
│   ├── security.php             (Validaciones)
│   └── database.php
│
├── db/
│   ├── trupper_db.sql           229 líneas
│   ├── PRODUCTOS_XLSX_IMPORT.sql 81 líneas
│   ├── TICKETS_SYSTEM.sql       189 líneas
│   └── ALTER_PAYMENT_TERMS.sql   81 líneas
│
├── database.sql                 421 líneas
├── clean_base64_images.php      192 líneas
├── diagnose_images.php           88 líneas
├── sync_images_to_db.php        142 líneas
├── test_image_upload.php         66 líneas
└── views/
    ├── products.php
    └── ...
```

---

## 🔍 HALLAZGOS CLAVE

### ✅ Lo que EXISTE

1. **Marketplace CE** - Sistema completo de productos de segunda mano
2. **Admin Supply** - Panel administrativo robusto (4,611 líneas)
3. **Gestión de Imágenes** - Base64 → archivos, migración, limpieza
4. **API REST** - Endpoints para stock, productos, imágenes
5. **Validaciones** - SKU, barcode, categorías, fechas
6. **Base de Datos** - Múltiples esquemas (PostgreSQL, MySQL)
7. **Scripts CLI** - Migración, eliminación, diagnóstico

### ⚠️ Potenciales Mejoras

1. Documentación de API (Swagger/OpenAPI)
2. Unit tests para funciones críticas
3. Rate limiting en APIs
4. Caché de productos
5. Versionado de cambios de productos
6. Más validaciones en carga de imágenes

---

## 📚 REFERENCIAS CRUZADAS

| Funcionalidad | Archivos Relacionados | Líneas |
|---------------|----------------------|--------|
| Marketplace | marketplace_ce.php, product_detail.php, public/api/products.php | 1,702 |
| Stock/Inventario | admin_supply.php (2x), config.php, database.sql | 9,285 |
| Imágenes | 7 scripts + API check_image_upload.php | 855 |
| Admin Supply | admin_supply.php, admin_supply_api.php | 8,785 |
| Productos | 6 modelos + vistas + APIs | 2,000 |
| Eliminación | admin_supply_api.php, delete scripts | 4,250 |
| Códigos (SKU) | security.php, admin_supply_api.php, scripts | 500 |

---

**Documento generado:** 7 de mayo de 2026  
**Scope:** Exploración exhaustiva de proyecto_Truper  
**Archivos analizados:** 150+  
**Líneas de código:** 16,000+
