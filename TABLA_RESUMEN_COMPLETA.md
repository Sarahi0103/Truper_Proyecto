# 📋 TABLA RESUMEN - TODOS LOS ARCHIVOS ENCONTRADOS

## 1. ARCHIVOS PRINCIPALES POR FUNCIONALIDAD

### 1️⃣ MARKETPLACE CE

| Archivo | Líneas | Tipo | Función | Ubicación |
|---------|--------|------|---------|-----------|
| `marketplace_ce.php` | 509 | PHP | Mostrador de productos CE | public/ |
| `product_detail.php` | 769 | PHP | Detalle de producto individual | public/ |
| `products.php` (API) | 424 | PHP | Búsqueda y CRUD | public/api/ |
| `marketplace_ce_products` | - | SQL | Tabla BD | PostgreSQL |

**Funciones clave:**
- ✅ Crear tabla `marketplace_ce_products` si no existe
- ✅ Cargar productos con `is_active = true`
- ✅ Normalizar categorías dinámicamente
- ✅ Mostrar 300 últimos productos (DESC)
- ✅ Resolver imágenes de galería
- ✅ Priorizar imágenes por patrón (+FC1, +E1, +D1)

---

### 2️⃣ STOCK/INVENTARIO

| Archivo | Líneas | Tipo | Función | Ubicación |
|---------|--------|------|---------|-----------|
| `admin_supply.php` | 4,611 | PHP | Dashboard de stock | public/ |
| `admin_supply.php` | 4,174 | PHP/API | API de stock | public/api/ |
| `config.php` | ~900 | PHP | Inicialización | config/ |
| `products` | - | SQL | Tabla principal | PostgreSQL |

**Campos de stock:**
- `stock_quantity INT DEFAULT 0`
- `reorder_level INT DEFAULT 10`
- Alertas cuando `stock < reorder_level`

**Operaciones:**
- CREATE: Insertar nuevo con stock
- READ: SELECT stock_quantity, reorder_level
- UPDATE: Modificar cantidad
- DELETE: Soft/hard delete

---

### 3️⃣ CARGA/GESTIÓN DE IMÁGENES

| Archivo | Líneas | Tipo | Función | Ubicación |
|---------|--------|------|---------|-----------|
| `clean_base64_images.php` | 192 | PHP | Convertir base64 → PNG/JPG | Raíz |
| `diagnose_images.php` | 88 | PHP | Diagnosticar problemas | Raíz |
| `sync_images_to_db.php` | 142 | PHP | Sincronizar BD ↔ FS | Raíz |
| `test_image_upload.php` | 66 | PHP | Pruebas | Raíz |
| `check_image_upload.php` | 82 | PHP/API | Validar carga | public/api/ |
| `migrate_legacy_images.php` | 209 | PHP | Migrar antiguas | scripts/ |
| `delete_image_cli.php` | 76 | PHP | Eliminar por SKU | scripts/ |
| `delete_and_verify_images.sh` | - | Bash | Verificación | scripts/ |
| `smoke_test_images.sh` | - | Bash | Pruebas integridad | scripts/ |

**Conversión:**
```
data:image/png;base64,... → /images/products/by_code/{SKU}/file.png
```

**Priorización:**
- `+FC1` = Principal (0)
- `+E1` = Secundaria (1)
- `+D1` = Terciaria (2)
- `+O\d+` = Otras (3)

---

### 4️⃣ ADMIN SUPPLY

| Archivo | Líneas | Tipo | Función | Ubicación |
|---------|--------|------|---------|-----------|
| `admin_supply.php` | 4,611 | PHP | Panel admin completo | public/ |
| `admin_supply.php` | 4,174 | PHP/API | API backend | public/api/ |
| `admin_clients.php` | 366 | PHP/API | Gestión de clientes | public/api/ |
| `auth_controller.php` | 120 | PHP | Control de acceso | backend/controllers/ |

**Tabs del panel:**
1. Productos (listado + búsqueda)
2. Crear producto
3. Editar producto
4. Imágenes
5. Variantes
6. Stock
7. Importar (CSV/XLSX)
8. Exportar
9. Reportes

---

### 5️⃣ PRODUCTOS (Gestión Completa)

| Archivo | Líneas | Tipo | Función | Ubicación |
|---------|--------|------|---------|-----------|
| `Product.php` | 105 | PHP | Modelo OOP | backend/models/ |
| `Product.php` | 100 | PHP | Modelo alternativo | src/models/ |
| `products.php` (API) | 424 | PHP | API CRUD | public/api/ |
| `product_detail.php` | 769 | PHP | Detalle | public/ |
| `products.php` (Vista) | - | PHP | Catálogo | views/ |
| `index.php` | 789 | PHP | Página principal | public/ |

**Métodos principales:**
- `getAll()` - Obtener todos
- `getById()` - Por ID
- `getByBarcode()` - Por código
- `getBySku()` - Por SKU
- `search()` - Búsqueda
- `getByCategory()` - Por categoría
- `create()` - Crear
- `update()` - Editar

---

### 6️⃣ ELIMINACIÓN/EDICIÓN

| Archivo | Líneas | Acción | Ubicación |
|---------|--------|--------|-----------|
| `admin_supply.php` | 4,611 | POST/PUT | public/api/ |
| `admin_supply.php` (UI) | 4,611 | UI botones | public/ |
| `delete_image_cli.php` | 76 | CLI delete | scripts/ |

**Eliminación de productos:**
```php
// Hard delete
DELETE FROM products WHERE id = ?

// Soft delete
UPDATE products SET is_active = false WHERE id = ?
```

**Eliminación de imágenes:**
```php
// Por SKU
php scripts/delete_image_cli.php 12345
// Elimina: /images/products/by_code/12345/

// Referencias en BD
UPDATE products SET image_url = NULL WHERE sku = ?
UPDATE marketplace_ce_products SET image_url = NULL WHERE sku = ?
```

---

### 7️⃣ CÓDIGOS DE PRODUCTOS (SKU/BARCODE)

| Tipo | Formato | Validación | Función | Archivo |
|------|---------|-----------|---------|---------|
| **SKU** | 5-6 dígitos | `^\d{5,6}$` | `is_valid_numeric_sku_admin_supply()` | public/api/admin_supply.php |
| **SKU Admin** | 5 exactos | `^\d{5}$` | `normalize_sku_admin_supply()` | public/api/admin_supply.php |
| **Barcode** | Alfanumérico | Único | `validateEmail()` | config/security.php |
| **Código General** | 3-20 chars | `^[A-Z0-9\-]{3,20}$` | `validateSKU()` | config/security.php |

**Normalización:**
```php
// Quitar prefijo XLS-
preg_replace('/^XLS-/i', '', $sku)

// Extraer solo dígitos
preg_replace('/\D+/', '', $sku)

// Normalizar a 5-6 dígitos
substr($digits, 0, 6)
```

---

## 2. TABLA DE BASE DE DATOS

### Tabla: `products`

| Campo | Tipo | Constraints | Descripción |
|-------|------|-------------|-------------|
| `id` | SERIAL | PRIMARY KEY | Identificador único |
| `sku` | VARCHAR(50) | UNIQUE NOT NULL | Código único del producto |
| `name` | VARCHAR(255) | NOT NULL | Nombre |
| `description` | TEXT | - | Descripción larga |
| `technical_specs` | TEXT | - | Especificaciones técnicas (JSON) |
| `image_url` | TEXT | - | URL de imagen principal |
| `variants_json` | TEXT | - | Variantes en JSON |
| `category` | VARCHAR(100) | - | Categoría |
| `unit_price` | DECIMAL(10,2) | NOT NULL | Precio unitario |
| `barcode` | VARCHAR(100) | UNIQUE | Código de barras |
| `stock_quantity` | INTEGER | DEFAULT 0 | Cantidad en stock |
| `reorder_level` | INTEGER | DEFAULT 10 | Nivel de reorden |
| `supplier_id` | INTEGER | FK | Proveedor |
| `is_active` | BOOLEAN | DEFAULT true | Activo/Inactivo |
| `created_at` | TIMESTAMP | DEFAULT NOW | Fecha creación |
| `updated_at` | TIMESTAMP | DEFAULT NOW | Última actualización |

### Tabla: `marketplace_ce_products`

| Campo | Tipo | Constraints | Descripción |
|-------|------|-------------|-------------|
| `id` | SERIAL | PRIMARY KEY | ID único |
| `sku` | VARCHAR(100) | UNIQUE NOT NULL | SKU único |
| `name` | VARCHAR(220) | NOT NULL | Nombre |
| `description` | TEXT | NOT NULL | Descripción |
| `condition_label` | VARCHAR(80) | DEFAULT 'Seminuevo' | Condición |
| `category` | VARCHAR(120) | - | Categoría |
| `unit_price` | DECIMAL(12,2) | DEFAULT 0 | Precio |
| `stock_quantity` | INTEGER | DEFAULT 1 | Stock |
| `image_url` | TEXT | - | Imagen principal |
| `variants_json` | TEXT | - | Variantes JSON |
| `is_active` | BOOLEAN | DEFAULT true | Visible |
| `created_by` | INTEGER | FK users | Creado por |
| `updated_by` | INTEGER | FK users | Editado por |
| `created_at` | TIMESTAMP | DEFAULT NOW | Creación |
| `updated_at` | TIMESTAMP | DEFAULT NOW | Actualización |

### Tabla: `order_items`

| Campo | Tipo | Constraints | Descripción |
|-------|------|-------------|-------------|
| `id` | SERIAL | PRIMARY KEY | ID único |
| `order_id` | INTEGER | FK orders | Orden |
| `product_id` | INTEGER | FK products | Producto |
| `quantity` | INTEGER | NOT NULL | Cantidad |
| `unit_price` | DECIMAL(10,2) | NOT NULL | Precio unitario |
| `subtotal` | DECIMAL(12,2) | NOT NULL | Subtotal |
| `discount_percentage` | DECIMAL(5,2) | DEFAULT 0 | % Descuento |
| `discount_amount` | DECIMAL(12,2) | DEFAULT 0 | $ Descuento |
| `line_total` | DECIMAL(12,2) | NOT NULL | Total línea |
| `created_at` | TIMESTAMP | DEFAULT NOW | Fecha |

---

## 3. APIS DISPONIBLES

### Stock Management: `/api/admin_supply.php`

| Acción | Método | Parámetros | Respuesta | Líneas |
|--------|--------|-----------|-----------|--------|
| `?action=stock` | GET | - | JSON stock | 4,174 |
| `?action=create` | POST | name, sku, price, stock | {id, success} | " |
| `?action=update` | POST/PUT | id, field, value | {success} | " |
| `?action=delete` | DELETE | id | {success} | " |
| `?action=search` | GET | q | {results[]} | " |
| `?action=by-sku` | GET | sku | {product} | " |
| `?action=by-barcode` | GET | barcode | {product} | " |

### Products Management: `/api/products.php`

| Acción | Método | Parámetros | Respuesta | Líneas |
|--------|--------|-----------|-----------|--------|
| `?action=search` | GET | barcode, sku, q | {product[]} | 424 |
| `?action=create` | POST | name, sku, price | {id, success} | " |
| `?action=update` | POST/PUT | id, data | {success} | " |
| `?action=delete` | DELETE | id | {success} | " |

### Admin Clients: `/api/admin_clients.php`

| Acción | Método | Parámetros | Respuesta | Líneas |
|--------|--------|-----------|-----------|--------|
| `?action=list` | GET | - | {clients[]} | 366 |
| `?action=get` | GET | id | {client} | " |
| `?action=create` | POST | email, name, phone | {id, success} | " |
| `?action=update` | POST | id, data | {success} | " |

---

## 4. ESTRUCTURAS JSON

### Variantes de Producto

```json
{
  "variants": [
    {
      "id": "1",
      "sku": "12345-1",
      "size": "M",
      "color": "Rojo",
      "price": 99.99,
      "stock": 50,
      "image_url": "images/products/by_code/12345/variant1.jpg"
    },
    {
      "id": "2",
      "sku": "12345-2",
      "size": "L",
      "color": "Azul",
      "price": 109.99,
      "stock": 30,
      "image_url": "images/products/by_code/12345/variant2.jpg"
    }
  ]
}
```

### Especificaciones Técnicas

```json
{
  "material": "Algodón 100%",
  "peso": "500g",
  "dimensiones": "30x20x10 cm",
  "origen": "China",
  "garantía": "12 meses",
  "certificaciones": ["CE", "ISO9001"]
}
```

---

## 5. SCRIPTS Y UTILIDADES

### Conversión de Imágenes

```bash
# Convertir base64 a archivos
php clean_base64_images.php

# Salida:
# ✓ image_url convertida para producto 12345
# ✓ variants_json convertida para producto 12345
# Total procesado: X productos
```

### Migración de Imágenes

```bash
# Migrar de /gallery/{sku}/ a /by_code/{sku}/
php scripts/migrate_legacy_images.php

# Salida:
# Migrando imágenes antiguas...
# Producto 12345: 3 imágenes movidas
```

### Eliminación de Imágenes

```bash
# Eliminar todas las imágenes del producto
php scripts/delete_image_cli.php 12345

# Salida:
# Eliminando imágenes del SKU 12345...
# Directorio eliminado: /images/products/by_code/12345/
# Comprobando referencias en BD... Actualizado
```

### Diagnóstico

```bash
# Verificar estado de imágenes
php diagnose_images.php

# Sincronizar FS con BD
php sync_images_to_db.php

# Pruebas de integridad
bash scripts/smoke_test_images.sh
```

---

## 6. VALIDACIONES

### SKU

```php
// Validación global (security.php)
preg_match('/^[A-Z0-9\-]{3,20}$/i', $sku)

// Validación numérica (admin_supply.php)
preg_match('/^\d{5,6}$/', $sku)

// Validación exacta admin (admin_supply.php)
preg_match('/^\d{5}$/', $sku)
```

### Categoría

```php
// Normalizar a ASCII
iconv('UTF-8', 'ASCII//TRANSLIT', $category)

// Convertir a minúsculas
mb_strtolower($category, 'UTF-8')

// Replacements: á→a, é→e, í→i, etc.
strtr($normalized, [
    'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u'
])
```

### Fechas

```php
// ISO 8601: YYYY-MM-DD
preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)

// DD/MM/YYYY a YYYY-MM-DD
preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $m)
return $m[3] . '-' . $m[2] . '-' . $m[1]

// String datetime
strtotime($date) → date('Y-m-d', $ts)
```

### Booleanos

```php
// Convertir a bool
in_array(strtolower($value), ['1', 'true', 't', 'yes', 'y', 'on'], true)
```

---

## 7. ESTADÍSTICAS DE CÓDIGO

### Por Archivo

```
Admin Supply UI:           4,611 líneas (Interfaz)
Admin Supply API:          4,174 líneas (Backend)
Index (Catálogo):            789 líneas
Product Detail:              769 líneas
Config:                    ~900 líneas (Inicialización)
Marketplace CE:              509 líneas
API Products:                424 líneas
Analytics API:               667 líneas
Auth API:                    198 líneas
```

### Por Tipo

```
PHP:       ~16,000 líneas
SQL:        ~1,100 líneas
JavaScript:  ~3,200 líneas
Bash:           ~500 líneas
```

### Por Función

```
Gestión de Stock:     8,785 líneas
Marketplace:          1,702 líneas
Imágenes:               855 líneas
Validación:             500 líneas
```

---

## 8. DEPENDENCIAS Y RELACIONES

```
marketplace_ce.php
  ├─ Tabla: marketplace_ce_products
  ├─ Carga categorías dinámicamente
  ├─ Resuelve imágenes: /images/products/by_code/{sku}/
  └─ Ordena por: created_at DESC LIMIT 300

product_detail.php
  ├─ Carga de: products O marketplace_ce_products
  ├─ Resuelve galería de imágenes
  ├─ Prioriza por patrón: +FC1, +E1, +D1, +O\d+
  ├─ Detecta: imagen_url, variants_json, base64
  └─ Muestra: especificaciones técnicas

admin_supply.php (UI)
  ├─ Llama a: public/api/admin_supply.php
  ├─ Maneja: CRUD de productos, imágenes, variantes, stock
  ├─ Subidas a: /images/products/by_code/{sku}/
  └─ Valida con: Security::validateSKU(), normalize_sku_admin_supply()

products.php (API)
  ├─ Busca en: products, marketplace_ce_products
  ├─ Búsqueda por: barcode, sku, nombre
  ├─ Resuelve imágenes desde catálogo
  └─ Retorna: JSON con todos los campos
```

---

**Generado:** 7 mayo 2026  
**Archivos analizados:** 150+  
**Total de código:** 16,000+ líneas
