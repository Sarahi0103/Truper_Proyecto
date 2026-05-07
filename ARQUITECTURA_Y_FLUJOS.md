# 🏗️ ARQUITECTURA Y FLUJOS DEL SISTEMA

## 1. ARQUITECTURA GENERAL

```
┌─────────────────────────────────────────────────────────────────┐
│                        USUARIOS                                  │
│  ┌──────────────────┐  ┌──────────────┐  ┌────────────────┐    │
│  │ Administrador    │  │   Cliente    │  │   Empleado     │    │
│  └────────┬─────────┘  └──────┬───────┘  └────────┬───────┘    │
└─────────────┼───────────────────┼──────────────────┼─────────────┘
              │                   │                  │
              ↓                   ↓                  ↓
    ┌──────────────────┐  ┌─────────────┐  ┌────────────────┐
    │ admin_supply.php │  │ index.php   │  │  dashboard.php │
    │  (4,611 líneas)  │  │ (789 líneas)│  │ (229 líneas)   │
    └────────┬─────────┘  └──────┬──────┘  └────────┬───────┘
             │                   │                   │
             ├───────────────────┼───────────────────┤
             │                   │                   │
             ↓                   ↓                   ↓
    ┌─────────────────────────────────────────────────────────┐
    │              PUBLIC APIS (15 archivos)                  │
    │  ┌─────────────────────────────────────────────────┐   │
    │  │ admin_supply.php    (4,174 líneas)             │   │
    │  │ products.php          (424 líneas)             │   │
    │  │ admin_clients.php     (366 líneas)             │   │
    │  │ analytics.php         (667 líneas)             │   │
    │  │ cashier.php           (475 líneas)             │   │
    │  │ check_image_upload.php (82 líneas)             │   │
    │  │ ... (9 más)                                     │   │
    │  └─────────────────────────────────────────────────┘   │
    └────────────────────┬────────────────────────────────────┘
                         │
             ┌───────────┼───────────┐
             ↓           ↓           ↓
    ┌──────────────┐ ┌─────────┐ ┌──────────────┐
    │ PostgreSQL   │ │ MySQL   │ │ Filesystem   │
    │  / MySQL     │ │ (Alt)   │ │ /images/...  │
    │              │ │         │ │              │
    │ - products   │ │ - users │ │ by_code/     │
    │ - orders     │ │ - orders│ │ {sku}/       │
    │ - users      │ │ - items │ │   ├─ img1.jpg│
    │ - items      │ │         │ │   ├─ img2.png│
    │ - mkpl_ce    │ │         │ │   └─ img3..  │
    └──────────────┘ └─────────┘ └──────────────┘
```

---

## 2. FLUJO DE PRODUCTOS

```
┌──────────────────────────────────────────────────────────────────┐
│                    CICLO DE VIDA DEL PRODUCTO                    │
└──────────────────────────────────────────────────────────────────┘

╔═════════════════════════════════════════════════════════════════╗
║ 1. CREAR PRODUCTO                                               ║
╚═════════════════════════════════════════════════════════════════╝

Admin accede admin_supply.php (4,611 líneas)
    ↓
Click tab "Crear Producto"
    ↓
Rellena formulario:
    ├─ SKU (validar: ^\d{5,6}$)
    ├─ Nombre
    ├─ Descripción
    ├─ Precio
    ├─ Categoría
    ├─ Stock inicial
    └─ Sube imágenes
    ↓
POST → /api/admin_supply.php?action=create
    ↓
API valida:
    ├─ normalize_sku_admin_supply()  ← Quita letras, quedaN 5-6 dígitos
    ├─ normalize_category_admin_supply() ← ASCII, minúsculas
    ├─ is_valid_numeric_sku_admin_supply() ← ^\d{5,6}$
    └─ Valida precio, stock
    ↓
Inserta en BD: INSERT INTO products (...)
    ↓
¿Imágenes?
    ├─ SÍ → Guardar en /images/products/by_code/{SKU}/
    └─ NO → image_url = NULL
    ↓
RESULTADO: Producto visible en catálogo (index.php)

╔═════════════════════════════════════════════════════════════════╗
║ 2. ACTUALIZAR PRODUCTO                                          ║
╚═════════════════════════════════════════════════════════════════╝

Admin en admin_supply.php
    ↓
Click tab "Editar"
    ↓
Busca producto:
    ├─ Por SKU
    ├─ Por nombre
    └─ Por categoría
    ↓
Modifica campos:
    ├─ Nombre/Descripción
    ├─ Precio
    ├─ Stock
    ├─ Categoría
    └─ Imágenes
    ↓
POST → /api/admin_supply.php?action=update
    ↓
UPDATE products SET ... WHERE id = ?
    ↓
RESULTADO: Cambios reflejados inmediatamente

╔═════════════════════════════════════════════════════════════════╗
║ 3. GESTIONAR STOCK                                              ║
╚═════════════════════════════════════════════════════════════════╝

Admin en admin_supply.php → tab "Stock"
    ↓
Busca producto
    ↓
Ajusta stock_quantity
    ↓
Verifica:
    ├─ Si stock < reorder_level
    │   └─ ALERTA: Solicitar reorden
    └─ Si stock = 0
        └─ MARCA: Producto agotado
    ↓
POST → /api/admin_supply.php?action=update
    ↓
UPDATE products SET stock_quantity = ? WHERE id = ?
    ↓
RESULTADO: Stock actualizado en tiempo real

╔═════════════════════════════════════════════════════════════════╗
║ 4. ELIMINAR PRODUCTO                                            ║
╚═════════════════════════════════════════════════════════════════╝

Admin en admin_supply.php
    ↓
Busca producto
    ↓
Click botón "Eliminar"
    ↓
Opción 1: Soft Delete (recomendado)
    └─ UPDATE products SET is_active = false WHERE id = ?
       (El producto se oculta pero datos persisten)

Opción 2: Hard Delete
    └─ DELETE FROM products WHERE id = ?
       (Se elimina permanentemente)
    ↓
¿Eliminar imágenes?
    ├─ php scripts/delete_image_cli.php {SKU}
    ├─ Elimina /images/products/by_code/{SKU}/
    └─ UPDATE ... SET image_url = NULL
    ↓
RESULTADO: Producto desaparece del catálogo

╔═════════════════════════════════════════════════════════════════╗
║ 5. CREAR VARIANTES                                              ║
╚═════════════════════════════════════════════════════════════════╝

Admin en admin_supply.php → tab "Variantes"
    ↓
Click "Agregar variante"
    ↓
Define:
    ├─ SKU variante (5-6 dígitos)
    ├─ Tamaño/Color/Especificación
    ├─ Precio diferencial
    ├─ Stock variante
    └─ Imagen variante
    ↓
Genera JSON:
{
  "variants": [
    {"id": "1", "sku": "12345-1", "size": "M", "price": 99.99, "stock": 50},
    {"id": "2", "sku": "12345-2", "size": "L", "price": 109.99, "stock": 30}
  ]
}
    ↓
ACTUALIZA: variants_json en tabla products
    ↓
RESULTADO: Variantes disponibles para compra
```

---

## 3. FLUJO DE IMÁGENES

```
┌──────────────────────────────────────────────────────────────────┐
│                    CICLO DE IMÁGENES                             │
└──────────────────────────────────────────────────────────────────┘

╔═════════════════════════════════════════════════════════════════╗
║ A. SUBIR IMAGEN (Upload)                                        ║
╚═════════════════════════════════════════════════════════════════╝

Admin en admin_supply.php → tab "Imágenes"
    ↓
Click "Subir imagen"
    ↓
Selecciona archivo:
    ├─ JPG, PNG, WebP
    ├─ Máx 5MB
    └─ Validar con check_image_upload.php
    ↓
POST → /api/admin_supply.php o /api/check_image_upload.php
    ↓
¿Es base64?
    ├─ SÍ → Convertir a PNG/JPG (clean_base64_images.php)
    └─ NO → Guardar directamente
    ↓
Crear directorio si no existe:
    └─ /images/products/by_code/{SKU}/
    ↓
Guardar archivo:
    ├─ nombre: image1.jpg
    ├─ O priorizar: image+FC1.jpg (principal)
    └─ O: image+E1.jpg, image+D1.jpg, image+O1.jpg
    ↓
Actualizar BD:
    ├─ image_url = 'images/products/by_code/{SKU}/image+FC1.jpg'
    └─ O variants_json si es de variante
    ↓
RESULTADO: Imagen visible en producto

╔═════════════════════════════════════════════════════════════════╗
║ B. RESOLVER IMAGEN (Mostrar)                                    ║
╚═════════════════════════════════════════════════════════════════╝

Cliente abre product_detail.php
    ↓
Lee producto: SELECT * FROM products WHERE id = ?
    ↓
Obtiene image_url:
    ├─ Si está en BD → Usar esa
    └─ Si vacío → Buscar en filesystem
    ↓
Busca archivos en /images/products/by_code/{SKU}/
    ↓
Ordena por prioridad:
    ├─ image+FC1.jpg    (0) ← Principal
    ├─ image+E1.jpg     (1)
    ├─ image+D1.jpg     (2)
    ├─ image+O\d+.jpg   (3)
    └─ others           (50)
    ↓
Retorna imagen con MAYOR PRIORIDAD (menor número)
    ↓
¿No hay imagen?
    └─ Usar fallback: images/products/default-product.svg
    ↓
RESULTADO: Mostrar imagen en página

╔═════════════════════════════════════════════════════════════════╗
║ C. LIMPIAR BASE64                                               ║
╚═════════════════════════════════════════════════════════════════╝

Ejecutar: php clean_base64_images.php
    ↓
Buscar en BD:
    ├─ SELECT * FROM products
    │   WHERE image_url LIKE 'data:image%'
    │      OR variants_json LIKE '%data:image%'
    ├─ SELECT * FROM marketplace_ce_products
    │   WHERE image_url LIKE 'data:image%'
    │      OR variants_json LIKE '%data:image%'
    ↓
Para cada base64:
    ├─ Decodificar data:image/png;base64,...
    ├─ Generar PNG/JPG
    ├─ Crear /images/products/by_code/{SKU}/ si no existe
    ├─ Guardar archivo
    └─ Actualizar BD con ruta relativa
    ↓
RESULTADO: Base64 convertidos a archivos

╔═════════════════════════════════════════════════════════════════╗
║ D. ELIMINAR IMAGEN                                              ║
╚═════════════════════════════════════════════════════════════════╝

Ejecutar: php scripts/delete_image_cli.php {SKU}
    ↓
Validar SKU: is_valid_sku_cli()
    ├─ ^\d{5,6}$ (5-6 dígitos)
    └─ Si no válido → Error
    ↓
Eliminar directorio: /images/products/by_code/{SKU}/
    ├─ Elimina todos los archivos dentro
    └─ Elimina el directorio
    ↓
Limpiar BD:
    ├─ UPDATE products SET image_url = NULL WHERE sku = ?
    └─ UPDATE marketplace_ce_products SET image_url = NULL WHERE sku = ?
    ↓
RESULTADO: Imágenes eliminadas
```

---

## 4. FLUJO DE MARKETPLACE CE

```
┌──────────────────────────────────────────────────────────────────┐
│                  MARKETPLACE CE (Productos 2da mano)            │
└──────────────────────────────────────────────────────────────────┘

╔════════════════════════════════════════════════════════════════╗
║ 1. LISTAR PRODUCTOS                                            ║
╚════════════════════════════════════════════════════════════════╝

Cliente accede: marketplace_ce.php
    ↓
Cargar tabla marketplace_ce_products:
    ├─ CREATE TABLE IF NOT EXISTS (primera vez)
    ├─ SELECT * FROM marketplace_ce_products WHERE is_active = true
    ├─ ORDER BY created_at DESC LIMIT 300
    ↓
Cargar categorías:
    ├─ Intentar: SELECT * FROM product_categories
    ├─ Fallback: Extraer de marketplace_ce_products.category
    ├─ Normalizar: mb_strtolower(), iconv('UTF-8', 'ASCII//TRANSLIT')
    ↓
Mostrar en carrusel/grid:
    ├─ Nombre
    ├─ Precio
    ├─ Stock disponible
    ├─ Condición (Seminuevo, Refurbished, etc.)
    ├─ Imagen principal
    └─ Botón "Ver detalle"

╔════════════════════════════════════════════════════════════════╗
║ 2. VER DETALLE                                                 ║
╚════════════════════════════════════════════════════════════════╝

Cliente hace click en producto
    ↓
Carga: product_detail.php?id={id}&source=ce
    ↓
Intenta cargar en este orden:
    ├─ Si source='ce':
    │   └─ SELECT * FROM marketplace_ce_products WHERE id = ?
    ├─ Si source='product':
    │   └─ SELECT * FROM products WHERE id = ?
    └─ Si source no especificado:
        ├─ Intenta products primero
        └─ Luego marketplace_ce_products
    ↓
Resuelve imágenes:
    ├─ Lee image_url de BD
    ├─ Si está: usar
    └─ Si no: buscar en /images/products/by_code/{SKU}/
    ↓
Prioriza:
    ├─ +FC1.jpg (principal)
    ├─ +E1.jpg
    ├─ +D1.jpg
    └─ +O\d+.jpg
    ↓
Muestra:
    ├─ Nombre completo
    ├─ Descripción
    ├─ Condición (condition_label)
    ├─ Precio
    ├─ Stock
    ├─ Categoría
    ├─ Variantes (si existen)
    └─ Especificaciones técnicas

╔════════════════════════════════════════════════════════════════╗
║ 3. AGREGAR A CARRITO                                           ║
╚════════════════════════════════════════════════════════════════╝

Cliente selecciona cantidad
    ↓
Click "Agregar al carrito"
    ↓
Validar:
    ├─ Stock disponible >= cantidad
    └─ Precio > 0
    ↓
Guardar en carrito (SESSION o localStorage)
    ├─ product_id
    ├─ source (ce o product)
    ├─ quantity
    ├─ price
    ├─ image_url
    └─ timestamp
    ↓
Actualizar contador de carrito
    ↓
RESULTADO: Item agregado

╔════════════════════════════════════════════════════════════════╗
║ 4. PROCESAR COMPRA                                             ║
╚════════════════════════════════════════════════════════════════╝

Cliente va a checkout
    ↓
Validar cliente logeado
    ├─ SÍ → Continuar
    └─ NO → Redirigir login
    ↓
Crear orden:
    ├─ INSERT INTO orders (client_id, total, status, ...)
    ├─ order_number = AUTO
    └─ created_at = NOW()
    ↓
Para cada item:
    ├─ INSERT INTO order_items (order_id, product_id, quantity, price, ...)
    ├─ Actualizar stock: UPDATE marketplace_ce_products SET stock_quantity = stock_quantity - qty
    └─ Calcular subtotales y descuentos
    ↓
Generar pago:
    ├─ Marcar estado: 'pending'
    ├─ Calcular total
    └─ Esperar confirmación
    ↓
RESULTADO: Orden creada en BD, cliente recibe confirmación
```

---

## 5. VALIDACIONES GLOBALES

```
┌──────────────────────────────────────────────────────────────────┐
│              PIPELINE DE VALIDACIONES                            │
└──────────────────────────────────────────────────────────────────┘

ENTRADA (Usuario/API)
    ↓
┌─ VALIDACIÓN 1: SKU ─────────────────────────────────────────┐
│                                                              │
│ Función: validateSKU() / is_valid_numeric_sku_admin_supply()
│ Formato: 5-6 dígitos numéricos O 3-20 chars alfanuméricos  │
│ Regex:   ^\d{5,6}$ O ^[A-Z0-9\-]{3,20}$                     │
│ Acción:  Normalizar, truncar, validar                       │
│                                                              │
│ Ejemplos:                                                    │
│  ✓ "12345"         → OK (5 dígitos)                         │
│  ✓ "123456"        → OK (6 dígitos)                         │
│  ✓ "XLS-12345"     → OK (quita XLS-)                        │
│  ✗ "1234"          → ERROR (4 dígitos)                      │
│  ✗ "ABC123"        → ERROR (no numérico)                    │
│                                                              │
└──────────────────────────────────────────────────────────────┘
    ↓
┌─ VALIDACIÓN 2: BARCODE ─────────────────────────────────────┐
│                                                              │
│ Función: Validar unicidad                                  │
│ Requisito: UNIQUE en BD                                    │
│ Formato: Alfanumérico, 3-100 caracteres                    │
│                                                              │
│ Ejemplos:                                                    │
│  ✓ "8901234567890" → OK (EAN-13)                           │
│  ✓ "1234567"       → OK (Code 39)                          │
│  ✗ ""              → ERROR (vacío, si NO NULL)             │
│  ✗ "DUPLICADO"     → ERROR (ya existe)                     │
│                                                              │
└──────────────────────────────────────────────────────────────┘
    ↓
┌─ VALIDACIÓN 3: CATEGORÍA ───────────────────────────────────┐
│                                                              │
│ Función: normalize_category_admin_supply()                 │
│ Acción:                                                     │
│  1. mb_strtolower() - Convertir a minúsculas               │
│  2. iconv('UTF-8', 'ASCII//TRANSLIT') - ASCII             │
│  3. strtr() - Reemplazar: á→a, é→e, ñ→n, etc.             │
│                                                              │
│ Ejemplos:                                                    │
│  "Accesorios"   → "accesorios"                             │
│  "HERRAMIENTAS" → "herramientas"                           │
│  "Ropa/Moda"    → "ropamoda"                               │
│                                                              │
└──────────────────────────────────────────────────────────────┘
    ↓
┌─ VALIDACIÓN 4: FECHA ───────────────────────────────────────┐
│                                                              │
│ Función: normalize_date_value()                            │
│ Soporta:                                                    │
│  - ISO 8601:      2026-05-07                               │
│  - DD/MM/YYYY:    07/05/2026                               │
│  - Texto:         "May 7, 2026" → strtotime()              │
│                                                              │
│ Ejemplos:                                                    │
│  "2026-05-07"  → "2026-05-07" (OK)                         │
│  "07/05/2026"  → "2026-05-07" (convierte)                  │
│  "May 7, 2026" → "2026-05-07" (parsea)                     │
│                                                              │
└──────────────────────────────────────────────────────────────┘
    ↓
┌─ VALIDACIÓN 5: BOOLEANO ────────────────────────────────────┐
│                                                              │
│ Función: normalize_bool_admin_supply()                     │
│ Convierte: 1, true, t, yes, y, on → true                  │
│ Convierte: 0, false, f, no, n, off → false                │
│                                                              │
│ Ejemplos:                                                    │
│  1          → true                                          │
│  "true"     → true                                          │
│  "yes"      → true                                          │
│  0          → false                                         │
│  "false"    → false                                         │
│                                                              │
└──────────────────────────────────────────────────────────────┘
    ↓
┌─ VALIDACIÓN 6: PRECIO ──────────────────────────────────────┐
│                                                              │
│ Tipo: DECIMAL(10,2) o DECIMAL(12,2)                       │
│ Rango: 0 <= precio <= 999999.99                            │
│ Formato: XXX.XX (máximo 2 decimales)                       │
│                                                              │
│ Ejemplos:                                                    │
│  99.99      → OK                                           │
│  "100"      → OK (convierte a 100.00)                      │
│  1.5        → OK                                           │
│  "abc"      → ERROR (no numérico)                          │
│  -10        → ERROR (negativo)                             │
│                                                              │
└──────────────────────────────────────────────────────────────┘
    ↓
┌─ VALIDACIÓN 7: STOCK ───────────────────────────────────────┐
│                                                              │
│ Tipo: INTEGER                                              │
│ Rango: 0 <= stock <= 2147483647 (INT max)                  │
│ Default: 0 (si no especificado)                            │
│                                                              │
│ Ejemplos:                                                    │
│  50         → OK                                           │
│  "100"      → OK (convierte)                               │
│  -5         → ERROR (no negativo)                          │
│  "abc"      → ERROR (no numérico)                          │
│                                                              │
└──────────────────────────────────────────────────────────────┘
    ↓
┌─ VALIDACIÓN 8: IMAGEN ──────────────────────────────────────┐
│                                                              │
│ Extensiones: .jpg, .jpeg, .png, .webp, .gif              │
│ Tamaño máx: 5MB (típicamente)                             │
│ MIME types: image/jpeg, image/png, image/webp            │
│ Función: FileUploadSecurity::validateUpload()             │
│                                                              │
│ Validaciones:                                               │
│  1. is_uploaded_file() - Verificar upload seguro          │
│  2. getimagesize() - Confirmar que es imagen             │
│  3. Tamaño <= 5MB                                         │
│  4. Extensión en whitelist                                │
│                                                              │
└──────────────────────────────────────────────────────────────┘
    ↓
PROCESAMIENTO (Validado)
    ↓
BASE DE DATOS
    ├─ Insertar/Actualizar
    ├─ Verificar UNIQUE constraints
    ├─ Ejecutar triggers
    └─ Log de auditoría
    ↓
SALIDA (Respuesta)
    ├─ Si OK: JSON {success: true, data: {...}}
    └─ Si ERROR: JSON {success: false, error: "..."}
```

---

## 6. ESTRUCTURA DE DIRECTORIOS FÍSICA

```
proyecto_Truper/
│
├── 📄 DOCUMENTACIÓN GENERADA (NUEVA)
│   ├── EXPLORACION_REPOSITORIO_COMPLETA.md  ✨ (Este documento)
│   ├── INDICE_RAPIDO_REFERENCIA.md
│   └── TABLA_RESUMEN_COMPLETA.md
│
├── 📁 public/  (Interfaz pública)
│   ├── admin_supply.php           4,611 líneas ⭐ PRINCIPAL
│   ├── marketplace_ce.php           509 líneas (Marketplace CE)
│   ├── product_detail.php           769 líneas (Detalle)
│   ├── index.php                    789 líneas (Catálogo)
│   ├── cart.php                     464 líneas
│   ├── orders.php                   549 líneas
│   ├── api/
│   │   ├── admin_supply.php       4,174 líneas ⭐ API PRINCIPAL
│   │   ├── products.php             424 líneas
│   │   ├── admin_clients.php        366 líneas
│   │   ├── analytics.php            667 líneas
│   │   ├── cashier.php              475 líneas
│   │   ├── check_image_upload.php     82 líneas
│   │   ├── client_account.php       570 líneas
│   │   ├── orders.php               207 líneas
│   │   └── ... (7 más = 15 total)
│   ├── images/
│   │   └── products/
│   │       ├── by_code/
│   │       │   ├── 10000/
│   │       │   │   ├── image+FC1.jpg
│   │       │   │   ├── image+E1.jpg
│   │       │   │   └── ...
│   │       │   └── 10001/
│   │       │       └── ...
│   │       └── default-product.svg
│   ├── js/
│   │   ├── main.js                 442 líneas
│   │   ├── catalog.js              400 líneas
│   │   ├── tasks.js                538 líneas
│   │   ├── orders.js               589 líneas
│   │   ├── analytics.js            740 líneas
│   │   └── barcode-scanner.js       99 líneas
│   └── css/
│       ├── styles.css
│       └── theme.css
│
├── 📁 backend/  (Lógica OOP)
│   ├── models/
│   │   ├── Product.php             105 líneas
│   │   ├── User.php
│   │   ├── Order.php
│   │   ├── SalesTicket.php
│   │   ├── Analytics.php
│   │   ├── BarcodeReader.php
│   │   ├── TicketIntegration.php
│   │   ├── Task.php
│   │   └── WholesaleSale.php
│   ├── controllers/
│   │   ├── auth_controller.php      120 líneas
│   │   ├── order_controller.php      89 líneas
│   │   ├── profile_controller.php    79 líneas
│   │   ├── wholesale_controller.php  51 líneas
│   │   └── logout.php                9 líneas
│   ├── utils/
│   │   └── Utilities.php
│   ├── hooks/
│   │   └── ticket_hooks.php
│   ├── config/
│   │   ├── database.php
│   │   └── security.php
│   └── ... (348 líneas totales)
│
├── 📁 src/  (Código alternativo)
│   ├── models/
│   │   ├── Product.php             100 líneas
│   │   └── User.php
│   ├── controllers/
│   │   ├── AnalyticsController.php
│   │   ├── AuthController.php
│   │   ├── OrderController.php
│   │   └── TaskController.php
│   └── utils/
│       └── BirthdayReminder.php
│
├── 📁 config/  (Configuración)
│   ├── config.php                  ~900 líneas (MIGRACIONES)
│   ├── database.php
│   ├── security.php                (Validaciones)
│   └── init_dirs.php
│
├── 📁 db/  (Esquemas SQL)
│   ├── trupper_db.sql              229 líneas (MySQL)
│   ├── PRODUCTOS_XLSX_IMPORT.sql    81 líneas
│   ├── TICKETS_SYSTEM.sql          189 líneas
│   └── ALTER_PAYMENT_TERMS.sql      81 líneas
│
├── 📁 scripts/  (Utilidades CLI)
│   ├── delete_image_cli.php         76 líneas
│   ├── migrate_legacy_images.php   209 líneas
│   ├── delete_and_verify_images.sh
│   └── smoke_test_images.sh
│
├── 📁 views/  (Templates)
│   ├── products.php
│   ├── login.php
│   ├── register.php
│   └── ...
│
├── 📁 cron/  (Tareas programadas)
│   └── tasks.php
│
├── 📁 admin/  (Admin alternativo)
│   └── dashboard.php
│
├── 📄 SQL - MIGRACIONES
│   ├── database.sql                421 líneas (PostgreSQL)
│   ├── fix_base64_images.sql        36 líneas
│   ├── PRODUCTOS_EJEMPLO.sql        14 líneas
│   ├── MAYORISTAS_CONFIGURACION.sql 49 líneas
│   └── ... (1,100 líneas totales)
│
├── 📄 SCRIPTS - IMAGEN
│   ├── clean_base64_images.php      192 líneas ⭐
│   ├── diagnose_images.php           88 líneas
│   ├── sync_images_to_db.php        142 líneas
│   ├── test_image_upload.php         66 líneas
│   └── ... (855 líneas totales)
│
├── docker-compose.yml
├── Dockerfile
├── render.yaml
├── index.php                       (Punto de entrada principal)
└── ... (20+ archivos de documentación)
```

---

## 7. RESUMEN DE MAPA MENTAL

```
TRUPER PLATFORM
│
├─ 🛒 TIENDA ONLINE (Public)
│  ├─ Catálogo (index.php, products.php)
│  ├─ Detalle de producto (product_detail.php)
│  ├─ Carrito (cart.php)
│  ├─ Checkout (orders.php)
│  └─ Mi cuenta (account.php, profile.php)
│
├─ 🏪 MARKETPLACE CE (marketplace_ce.php)
│  ├─ Productos de segunda mano
│  ├─ Tabla: marketplace_ce_products
│  ├─ Gestión de stock separada
│  └─ Categorías dinámicas
│
├─ ⚙️ ADMIN PANEL (admin_supply.php) [4,611 líneas]
│  ├─ Gestión de productos
│  │  ├─ CRUD: Crear, Leer, Editar, Eliminar
│  │  ├─ Validación: SKU ^\d{5,6}$
│  │  └─ Búsqueda flexible
│  ├─ Gestión de imágenes
│  │  ├─ Upload
│  │  ├─ Priorización: +FC1, +E1, +D1
│  │  └─ Base64 → PNG/JPG
│  ├─ Gestión de variantes
│  │  ├─ Size, color, etc.
│  │  ├─ Stock por variante
│  │  └─ Precio diferencial
│  ├─ Gestión de stock
│  │  ├─ Ajustes manuales
│  │  ├─ Alertas de bajo stock
│  │  └─ Nivel de reorden
│  ├─ Importación (CSV/XLSX)
│  ├─ Exportación
│  └─ Reportes
│
├─ 🔌 APIs REST (15 archivos)
│  ├─ admin_supply.php [4,174 líneas] ⭐
│  │  └─ CRUD stock, validaciones, normalización
│  ├─ products.php [424 líneas]
│  │  └─ Búsqueda, crear, editar, eliminar
│  ├─ admin_clients.php [366 líneas]
│  │  └─ Gestión de clientes
│  ├─ check_image_upload.php [82 líneas]
│  │  └─ Validar upload
│  ├─ analytics.php [667 líneas]
│  └─ ... (10 más)
│
├─ 🗂️ BASE DE DATOS
│  ├─ Tabla: products
│  │  ├─ sku (UNIQUE)
│  │  ├─ name
│  │  ├─ price
│  │  ├─ stock_quantity
│  │  ├─ image_url
│  │  ├─ variants_json
│  │  └─ is_active
│  ├─ Tabla: marketplace_ce_products
│  │  ├─ Similar a products
│  │  └─ condition_label (Seminuevo)
│  ├─ Tabla: orders
│  ├─ Tabla: order_items
│  └─ Tabla: users
│
├─ 🖼️ IMÁGENES
│  ├─ Sistema de archivos: /images/products/by_code/{SKU}/
│  ├─ Conversión: clean_base64_images.php
│  ├─ Migración: migrate_legacy_images.php
│  ├─ Eliminación: delete_image_cli.php
│  ├─ Sincronización: sync_images_to_db.php
│  ├─ Validación: check_image_upload.php
│  ├─ Prioridad: +FC1, +E1, +D1, +O\d+
│  └─ Fallback: default-product.svg
│
├─ 🔐 SEGURIDAD
│  ├─ Autenticación: require_admin()
│  ├─ Validaciones: Security::validate*()
│  ├─ Hashing: password_hash()
│  ├─ CSRF: Token verification
│  ├─ SQL: Prepared statements (PDO)
│  └─ Upload: FileUploadSecurity
│
├─ 📊 UTILIDADES
│  ├─ Modelos OOP: src/models/, backend/models/
│  ├─ Controladores: src/controllers/, backend/controllers/
│  ├─ Hooks: ticket_hooks.php
│  ├─ Logger: Utilities::Logger
│  ├─ EmailService: Utilities::EmailService
│  └─ Invoice: Utilities::Invoice
│
└─ 🛠️ CONFIGURACIÓN
   ├─ config.php: Inicialización y migraciones
   ├── database.php: Conexión BD
   ├── security.php: Seguridad global
   ├── Docker: Dockerfile, docker-compose.yml
   └── Environment: .env (si existe)
```

---

**Documento actualizado:** 7 mayo 2026  
**Total de líneas de código:** 16,000+  
**Archivos analizados:** 150+
