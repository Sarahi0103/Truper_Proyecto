# ✅ PERSISTENCIA DE IMÁGENES - DEPLOYMENT COMPLETADO

## Resumen de Cambios

El problema fue que **admin guardaba imágenes en una ruta, pero público las buscaba en otra**. Ahora ambos usan la **ruta canónica compartida**: `images/products/gallery/{sku}/`

### ¿Qué cambió?

**Antes (Problema)**
```
Admin sube imagen → Guardada en: images/products/timestamp.jpg
Admin guarda → BD recibe: default-product.svg (sin persistir)
Público busca → Mira images/products/gallery/XXX/ → NO ENCUENTRA
Resultado: Placeholder en público
```

**Ahora (Solución)**
```
Admin sube imagen → Guardada en: images/products/gallery/{sku}/image.jpg
Admin guarda → BD recibe: images/products/gallery/{sku}/image.jpg
Admin selecciona → BD recibe: images/products/gallery/{sku}/image.jpg
Público busca → Mira BD → Encuentra: images/products/gallery/{sku}/image.jpg
Resultado: Imagen aparece en público
```

## Cambios en Código

### `/public/api/admin_supply.php`

#### product-create (línea ~2568)
```php
// ANTES: store_product_image($_FILES['image'], ...)
// AHORA: store_product_image_for_sku_admin_supply($_FILES['image'], $sku)
if (!empty($finalGallery)) {
    $persistedGallery = persist_product_gallery_images_admin_supply($pdo, $sku, $finalGallery);
}
```

#### product-save (línea ~2688)
```php
// Ahora preserva image_url seleccionado incluso sin disco gallery
$finalGallery = !empty($galleryImages)
    ? $galleryImages
    : (!empty($imageUrl) && strcasecmp($imageUrl, 'images/products/default-product.svg') !== 0 
        ? [$imageUrl] 
        : []
    );

if (!empty($finalGallery)) {
    persist_product_gallery_images_admin_supply($pdo, $sku, $finalGallery);
}
```

#### marketplace-save
```php
// ANTES: store_product_image_for_sku_admin_supply solo en condiciones específicas
// AHORA: Siempre usa store_product_image_for_sku_admin_supply
// + llama persist_product_gallery_images_admin_supply
```

## Funciones Clave

### `store_product_image_for_sku_admin_supply($file, $sku)`
- **Qué hace**: Sube imagen a `images/products/gallery/{sku}/`
- **Retorna**: `images/products/gallery/{sku}/filename.ext`
- **Ubicación**: `/public/api/admin_supply.php`

### `persist_product_gallery_images_admin_supply($pdo, $sku, $images)`
- **Qué hace**: Guarda array de imágenes en BD
- **Actualiza**: `products.image_url` (primera imagen) y `products.variants_json` (todas)
- **También**: Actualiza `marketplace_ce_products` tabla
- **Ubicación**: `/public/api/admin_supply.php`

## Estado Actual

✅ **Código desplegado en main**
✅ **Funciones activas en contenedor**
✅ **Directorios gallery existen**
✅ **Validación sin errores PHP**

## Próximo Paso: TESTING

Para confirmar que todo funciona:

1. **En Admin Panel** (`/admin_supply.php`)
   - Selecciona o sube imagen para un producto
   - Guarda el producto
   
2. **Verifica en Browser Console**
   - Red → Verifica response de `/api/admin_supply` 
   - Debe contener: `"image_url":"images/products/gallery/..."`

3. **En Base de Datos** (si tienes acceso)
   - `SELECT image_url FROM products WHERE sku = 'XXX'`
   - Debe mostrar: `images/products/gallery/XXX/...`

4. **En Catálogo Público** (`/index.php`)
   - Busca el producto
   - Imagen debe aparecer (NO placeholder)

## Notas de Desarrollo

- Las imágenes anteriores sin ruta canónica se pueden migrar con el script `sync_gallery_to_db.php`
- La ruta canónica es determinada por `images_root_admin_supply()` en el código
- El fallback a placeholder solo ocurre si NO hay imagen_url en BD (comportamiento correcto)

## Si Algo No Funciona

- Verifica que `public/api/admin_supply.php` tenga las funciones de persistencia
- Verifica permisos de escritura en `images/products/gallery/`
- Verifica que el `$pdo` en config esté conectado (test: `php health.php`)
- Limpia cache del browser (Ctrl+Shift+Del)

---

**Deployado**: May 11, 2026 - Persistencia de Imágenes v2
**Estado**: Production Ready (Render.com)
