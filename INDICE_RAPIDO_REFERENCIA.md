# 🚀 ÍNDICE RÁPIDO - REFERENCIA CRUZADA

## 📌 BÚSQUEDA RÁPIDA POR FUNCIONALIDAD

### 1️⃣ Necesito CREAR un producto
- **Interfaz:** [public/admin_supply.php](public/admin_supply.php) líneas 1-100
- **API:** [public/api/admin_supply.php](public/api/admin_supply.php) - acción: `create`
- **Validación:** [config/security.php](config/security.php) - `validateSKU()`
- **BD:** Insertar en tabla `products`

### 2️⃣ Necesito EDITAR un producto
- **Interfaz:** [public/admin_supply.php](public/admin_supply.php) - tab "Editar"
- **API:** [public/api/admin_supply.php](public/api/admin_supply.php) - acción: `update`
- **Funciones:** `normalize_sku_admin_supply()`, `normalize_category_admin_supply()`
- **BD:** UPDATE en tabla `products` WHERE id = ?

### 3️⃣ Necesito ELIMINAR un producto
- **Interfaz:** [public/admin_supply.php](public/admin_supply.php) - botón delete
- **API:** [public/api/admin_supply.php](public/api/admin_supply.php) - acción: `delete`
- **Opción:** Soft delete (is_active = false) o hard delete
- **BD:** DELETE FROM products WHERE id = ?

### 4️⃣ Necesito SUBIR imágenes
- **Interfaz:** [public/admin_supply.php](public/admin_supply.php) - tab "Imágenes"
- **API:** [public/api/admin_supply.php](public/api/admin_supply.php) o [public/api/check_image_upload.php](public/api/check_image_upload.php)
- **Proceso:** Upload → /images/products/by_code/{SKU}/
- **Conversión:** [clean_base64_images.php](clean_base64_images.php) para base64

### 5️⃣ Necesito BUSCAR un producto
- **Por SKU:** [public/api/products.php](public/api/products.php) - query: `by-sku`
- **Por Barcode:** [public/api/products.php](public/api/products.php) - query: `by-barcode`
- **Por Nombre:** [public/api/products.php](public/api/products.php) - query: `search`
- **En BD:** WHERE sku = ? OR barcode = ? OR name ILIKE ?

### 6️⃣ Necesito GESTIONAR el MARKETPLACE CE
- **Interfaz:** [public/marketplace_ce.php](public/marketplace_ce.php) (509 líneas)
- **Detalle:** [public/product_detail.php](public/product_detail.php) (769 líneas)
- **API:** [public/api/products.php](public/api/products.php)
- **BD:** Tabla `marketplace_ce_products`

### 7️⃣ Necesito ACTUALIZAR STOCK
- **Interfaz:** [public/admin_supply.php](public/admin_supply.php) - tab "Stock"
- **API:** [public/api/admin_supply.php](public/api/admin_supply.php) - campo: `stock_quantity`
- **BD:** UPDATE products SET stock_quantity = ? WHERE id = ?
- **Alertas:** Nivel de reorden (`reorder_level`)

### 8️⃣ Necesito CREAR VARIANTES
- **Interfaz:** [public/admin_supply.php](public/admin_supply.php) - tab "Variantes"
- **Campo BD:** `variants_json` (JSON)
- **Contenido:** {sku: "...", tamaño: "...", color: "...", precio: 0, stock: 0}
- **API:** [public/api/admin_supply.php](public/api/admin_supply.php)

### 9️⃣ Necesito VALIDAR SKU
- **Función:** [config/security.php](config/security.php) - `validateSKU()`
- **Regex:** `^[A-Z0-9\-]{3,20}$`
- **Admin Supply:** `is_valid_numeric_sku_admin_supply()` - `^\d{5,6}$`
- **Legacy:** Prefijo "XLS-" se quita en display

### 🔟 Necesito LIMPIAR imágenes base64
- **Script:** [clean_base64_images.php](clean_base64_images.php) (192 líneas)
- **Proceso:** Buscar data:image/ → convertir PNG → guardar en /by_code/{SKU}/
- **Tablas:** products, marketplace_ce_products
- **BD Update:** Actualizar image_url y variants_json

---

## 🗂️ ARCHIVOS MÁS IMPORTANTES (POR TAMAÑO)

```
1. public/admin_supply.php              4,611 líneas  ⭐⭐⭐⭐⭐
   └─ Dashboard admin, CRUD, imágenes, variantes
   
2. public/api/admin_supply.php          4,174 líneas  ⭐⭐⭐⭐⭐
   └─ API REST, validaciones, normalización
   
3. public/index.php                       789 líneas  ⭐⭐⭐
   └─ Catálogo principal de productos
   
4. public/product_detail.php              769 líneas  ⭐⭐⭐
   └─ Detalle de producto, galería, resolución de imágenes
   
5. public/marketplace_ce.php              509 líneas  ⭐⭐⭐
   └─ Marketplace CE, categorías dinámicas
   
6. public/api/products.php                424 líneas  ⭐⭐
   └─ Búsqueda, crear, actualizar, eliminar
   
7. config/config.php                      ~900 líneas ⭐⭐
   └─ Inicialización, migraciones, seeders
```

---

## 🔗 RELACIONES DE TABLAS

```
users (id, email, role, ...)
  ├─→ clients (user_id REFERENCES users)
  ├─→ orders (client_id REFERENCES clients)
  └─→ marketplace_ce_products (created_by, updated_by REFERENCES users)

products (id, sku UNIQUE, barcode UNIQUE, stock_quantity, ...)
  ├─→ order_items (product_id REFERENCES products)
  ├─→ variants_json (JSON dentro de products)
  └─→ /images/products/by_code/{sku}/ (Sistema de archivos)

marketplace_ce_products (id, sku UNIQUE, stock_quantity, ...)
  ├─→ variants_json (JSON)
  └─→ /images/products/by_code/{sku}/ (Compartido con products)

orders (id, client_id, order_number UNIQUE, ...)
  ├─→ order_items (order_id REFERENCES orders)
  └─→ payments (order_id REFERENCES orders)
```

---

## 🎯 FLUJOS PRINCIPALES

### FLUJO 1: Crear y vender un producto

```
1. Admin accede → admin_supply.php
2. Click "Crear Producto" tab
3. Rellena formulario:
   - SKU (5-6 dígitos)
   - Nombre
   - Descripción
   - Precio
   - Categoría
   - Stock
4. Sube imágenes → /images/products/by_code/{SKU}/
5. Submit POST → api/admin_supply.php?action=create
6. BD: INSERT INTO products (sku, name, price, stock_quantity, ...)
7. Cliente ve producto en index.php
8. Cliente compra → order_items
```

### FLUJO 2: Gestionar stock

```
1. Admin → admin_supply.php → "Stock" tab
2. Busca producto por SKU
3. Modifica cantidad: stock_quantity
4. Submit POST → api/admin_supply.php?action=update
5. BD: UPDATE products SET stock_quantity = ? WHERE id = ?
6. Sistema chequea reorder_level
7. Si stock < reorder_level → Alerta
```

### FLUJO 3: Buscar producto

```
Cliente busca por:
├─ SKU        → api/products.php?action=by-sku&sku=12345
├─ Barcode    → api/products.php?action=by-barcode&barcode=...
└─ Nombre     → api/products.php?action=search&q=...

API responde con:
├─ product_id
├─ name, description
├─ sku, barcode
├─ price, stock_quantity
├─ image_url
├─ variants_json
└─ technical_specs
```

### FLUJO 4: Limpiar imágenes base64

```
1. Script: php clean_base64_images.php
2. Lee tablas: products, marketplace_ce_products
3. Busca: image_url LIKE 'data:image%'
4. Convierte cada base64:
   - Decodifica
   - Guarda PNG/JPG en /images/products/by_code/{SKU}/
5. Actualiza BD: image_url = 'images/products/by_code/{SKU}/file.jpg'
```

---

## 🔐 SEGURIDAD

| Punto | Implementación | Archivo |
|-------|-----------------|---------|
| **Autenticación** | require_admin() | config/config.php |
| **CSRF Token** | Verificado en POST | config/security.php |
| **Input Validation** | validateSKU(), validateEmail(), etc. | config/security.php |
| **Password Hashing** | password_hash() | config/security.php |
| **File Upload** | FileUploadSecurity class | config/security.php |
| **SQL Injection** | Prepared statements (PDO) | Todos los archivos |
| **Roles** | admin, client, employee | users table |

---

## 📊 QUERIES MÁS USADAS

### Buscar producto
```sql
SELECT * FROM products WHERE sku = ? AND is_active = true LIMIT 1;
SELECT * FROM products WHERE barcode = ? AND is_active = true LIMIT 1;
SELECT * FROM products WHERE name ILIKE ? AND is_active = true;
```

### Stock bajo
```sql
SELECT * FROM products 
WHERE stock_quantity < reorder_level 
AND is_active = true 
ORDER BY stock_quantity ASC;
```

### Catálogo marketplace
```sql
SELECT * FROM marketplace_ce_products 
WHERE is_active = true 
ORDER BY created_at DESC LIMIT 300;
```

### Crear producto
```sql
INSERT INTO products (sku, name, category, unit_price, stock_quantity, is_active)
VALUES (?, ?, ?, ?, ?, true);
```

### Actualizar producto
```sql
UPDATE products 
SET name = ?, unit_price = ?, stock_quantity = ?, updated_at = CURRENT_TIMESTAMP
WHERE id = ?;
```

### Eliminar producto
```sql
DELETE FROM products WHERE id = ?;
-- O soft delete:
UPDATE products SET is_active = false WHERE id = ?;
```

---

## 🚨 ERRORES COMUNES

| Error | Causa | Solución |
|-------|-------|----------|
| SKU inválido | No cumple `^\d{5,6}$` | Usar 5-6 dígitos |
| Imagen no aparece | Base64 sin convertir | Correr clean_base64_images.php |
| 404 en producto | is_active = false | Activar en admin_supply |
| Barcode duplicado | Único en BD | Validar antes de insertar |
| Stock negativo | No hay validación | Agregar WHERE stock_quantity >= 0 |
| Timeout al crear | Imágenes muy grandes | Comprimir antes de subir |

---

## 📁 ESTRUCTURA DE DIRECTORIOS - IMÁGENES

```
/images/
├── products/
│   ├── by_code/
│   │   ├── 10000/  (5 dígitos = SKU de admin_supply)
│   │   │   ├── image1.jpg              (normal)
│   │   │   ├── image2+FC1.jpg          (principal)
│   │   │   ├── image3+E1.jpg           (secundaria)
│   │   │   └── image4+D1.jpg           (terciaria)
│   │   ├── 10001/
│   │   │   └── ...
│   │   └── ...
│   └── default-product.svg             (fallback)
```

**Prioridad de imágenes:**
1. `+FC1` (Prioridad 0) ← Principal
2. `+E1` (Prioridad 1)
3. `+D1` (Prioridad 2)
4. `+O\d+` (Prioridad 3)
5. Sin sufijo (Prioridad 50)

---

## 🛠️ COMANDOS ÚTILES

### Migrar imágenes antiguas
```bash
php scripts/migrate_legacy_images.php
```

### Eliminar imágenes de producto
```bash
php scripts/delete_image_cli.php 12345
```

### Limpiar base64
```bash
php clean_base64_images.php
```

### Sincronizar imágenes
```bash
php sync_images_to_db.php
```

### Diagnosticar imágenes
```bash
php diagnose_images.php
```

### Verificar sistema
```bash
bash scripts/delete_and_verify_images.sh
```

---

## 📞 CONTACTO CON FUNCIONES

### Si necesitas modificar...

**SKU/Códigos:**
- `config/security.php` → `validateSKU()`
- `public/api/admin_supply.php` → `normalize_sku_admin_supply()`, `is_valid_numeric_sku_admin_supply()`

**Imágenes:**
- `clean_base64_images.php` → `convert_base64_image_to_file()`
- `public/product_detail.php` → `image_priority_score()`, `resolve_images_by_product_code()`
- `public/api/check_image_upload.php` → Upload validation

**Productos:**
- `src/models/Product.php` → `getAll()`, `create()`, `update()`, etc.
- `public/api/products.php` → Búsqueda y CRUD

**Stock:**
- `public/admin_supply.php` → UI de gestión
- `public/api/admin_supply.php` → Lógica backend

**Marketplace:**
- `public/marketplace_ce.php` → Mostrador
- `public/product_detail.php` → Detalle
- `public/api/products.php` → API compartida

---

**Documento generado:** 7 de mayo de 2026  
**Para:** Equipo de desarrollo  
**Propósito:** Referencia rápida y navegación eficiente
