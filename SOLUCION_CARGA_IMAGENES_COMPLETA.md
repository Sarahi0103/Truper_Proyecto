# Solución Completa: Error al Cargar Imágenes

## Problema
Cuando se intenta subir imágenes a un producto en admin_supply.php, aparece el error "Error al cargar imágenes".

## Causas Raíz
1. **Directorio gallery/ no existe en algunos ambientes** (especialmente en Render)
2. **Permisos insuficientes** en el directorio de imágenes
3. **Imágenes base64 antiguas** en la BD que interfieren con nuevas cargas

## Soluciones Implementadas

### 1. Inicialización Automática de Directorios
Se agregó `config/init_dirs.php` que se ejecuta automáticamente cuando se carga `config.php`. Esto:
- Crea `/public/images/products/gallery/` si no existe
- Establece permisos correctos (775)
- Se ejecuta en cada solicitud, garantizando que el directorio siempre esté disponible

```php
// config/init_dirs.php
require_once __DIR__ . '/init_dirs.php';  // En config/config.php
```

### 2. Inicialización en Docker
El script `docker/start.sh` ahora:
- Crea los directorios necesarios al iniciar el contenedor
- Establece permisos 777 para garantizar escritura
- Ejecuta `init_dirs.sh` como capa adicional de defensa

```bash
# docker/start.sh
mkdir -p /var/www/html/public/images/products/gallery
chmod 777 /var/www/html/public/images/products/gallery 2>/dev/null || true
```

### 3. Script de Diagnóstico
Se agregaron varios scripts para diagnosticar problemas:

**`test_image_upload.php`** - Prueba desde terminal:
```bash
php test_image_upload.php
```

**`/api/check_image_upload.php`** - Endpoint que verifica estado:
```bash
curl https://truper-web.onrender.com/api/check_image_upload.php
```

**`diagnose_images.php`** - Diagnóstico completo (CLI):
```bash
php diagnose_images.php
```

### 4. Almacenamiento Persistente en Render
`render.yaml` ya tiene configurado:
```yaml
disks:
  - name: images-disk
    mountPath: /var/www/html/images
```

Esto garantiza que las imágenes persistan entre redeploys.

### 5. Mejora en Manejo de Errores
`public/api/admin_supply.php`:
- Captura excepciones específicas al subir imágenes
- Valida permisos antes de guardar
- Retorna mensajes de error descriptivos

### 6. Conversión de Base64
Si todavía hay imágenes base64 en la BD:

```bash
php clean_base64_images.php
```

O usar SQL directamente:
```bash
psql -U truper_admin -d truper_platform -f fix_base64_images.sql
```

## Archivos Nuevos o Modificados

### Nuevos:
- `config/init_dirs.php` - Inicializador automático
- `init_dirs.sh` - Script de inicialización
- `public/api/check_image_upload.php` - Endpoint de verificación
- `test_image_upload.php` - Script de prueba
- `fix_base64_images.sql` - Conversión SQL
- `IMAGE_UPLOAD_FIX_SCRIPT.html` - Función JS mejorada

### Modificados:
- `config/config.php` - Agrega init_dirs.php
- `docker/start.sh` - Inicializa directorios
- `public/api/admin_supply.php` - Mejor manejo de errores
- `public/admin_supply.php` - Mensajes de error mejorados

## Cómo Verificar que Funciona

### 1. Localmente (Docker):
```bash
# Dentro del contenedor
docker exec truper-web php test_image_upload.php
```

### 2. En Render (Producción):
```bash
# Verificar desde navegador
curl https://truper-web.onrender.com/api/check_image_upload.php
```

### 3. Prueba en la interfaz:
1. Ve a admin_supply.php
2. En la sección Stock, selecciona o crea un producto
3. Sube una imagen
4. Debería funcionar correctamente

## Flujo de Solución Automática

Cuando se intenta subir una imagen:

```
1. Se valida el SKU
2. Se verifica que hay archivos seleccionados
3. API valida permisos del directorio
4. Si no existe, lo crea automáticamente
5. Guarda la imagen procesada
6. Retorna ruta de la imagen
7. Frontend renderiza galería
```

## Troubleshooting

### Si aún hay error "Error al cargar imágenes":

1. **Verificar directorios:**
   ```bash
   ls -la public/images/products/gallery/
   ```

2. **Verificar permisos:**
   ```bash
   chmod 777 public/images/products/gallery/
   ```

3. **Ver logs de errores:**
   ```bash
   # En Render, ver Application Logs
   # En Docker: docker logs truper-web
   ```

4. **Ejecutar diagnóstico:**
   ```bash
   php diagnose_images.php
   ```

5. **Convertir base64 si es necesario:**
   ```bash
   php clean_base64_images.php
   ```

## Resultado Esperado

✅ Las imágenes se cargan correctamente  
✅ Se renderiza la galería de productos  
✅ Las imágenes persisten entre redeploys  
✅ No hay errores en la consola del navegador  
✅ Los productos muestran sus imágenes correctamente  

## Deployment

Al hacer deploy en Render:
1. Los cambios en `docker/start.sh` se ejecutarán automáticamente
2. `config/init_dirs.php` se cargará en cada solicitud
3. Los directorios se crearán automáticamente
4. Las imágenes se guardarán en el volumen persistente

No requiere configuración adicional. ¡Todo debería funcionar automáticamente! 🚀
