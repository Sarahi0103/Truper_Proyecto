# Error al cargar imágenes - Solución

## Problema Identificado
El error "Error al cargar imágenes" ocurre en la página de Abastecimiento (admin_supply.php) cuando intentas subir imágenes de productos.

## Causas Principales

1. **Directorio de galería no existe**: El código intenta guardar imágenes en `public/images/products/gallery/{sku}/` pero el directorio no existía o no tenía permisos.

2. **Imágenes guardadas como base64**: La versión anterior guardaba imágenes como strings base64 en la BD, lo que causa problemas de rendimiento y carga.

3. **Falta de validación de errores**: El código no mostraba mensajes de error específicos.

## Soluciones Aplicadas

### 1. Crear Directorio de Galería
```bash
mkdir -p /workspaces/proyecto_Truper/public/images/products/gallery
chmod 777 /workspaces/proyecto_Truper/public/images/products/gallery
```

✅ **Completado**

### 2. Mejorar Manejo de Errores
Se actualizó el código en `/public/api/admin_supply.php`:
- Ahora captura excepciones específicas al subir imágenes
- Valida que el directorio exista y sea escribible
- Verifica que el archivo se haya guardado correctamente
- Retorna mensajes de error más específicos

✅ **Completado**

### 3. Convertir Imágenes Base64 (IMPORTANTE)
Si todavía tienes imágenes guardadas como base64 en la BD:

**En tu máquina local:**
```bash
cd /workspaces/proyecto_Truper
php clean_base64_images.php
```

**En Render (producción):**
```bash
# Conectarse por SSH
# Luego ejecutar:
php clean_base64_images.php
```

### 4. Crear Endpoint de Diagnóstico
Se agregó `/public/api/diagnosis.php` que permite verificar:
- Estado de directorios
- Permisos de archivos
- Productos con base64 pendientes
- Recomendaciones automáticas

## Prueba la Solución

1. **Accede a admin_supply.php**
   - Ve a la sección "Stock"
   - Intenta subir una imagen

2. **Si aún hay problemas:**
   - Ejecuta: `curl http://localhost/api/diagnosis.php`
   - Verifica el diagnóstico
   - Sigue las recomendaciones

3. **Limpia imágenes base64:**
   ```bash
   cd /workspaces/proyecto_Truper
   php clean_base64_images.php
   ```

## Archivos Modificados
- `/public/api/admin_supply.php` - Mejor manejo de errores en carga de imágenes
- `/public/api/diagnosis.php` - Nuevo endpoint de diagnóstico
- `/public/images/products/gallery/` - Directorio creado con permisos correctos

## Resultado Esperado
✅ Las imágenes se cargan correctamente
✅ Las galerías se muestran en admin_supply.php
✅ El "Modelo estándar" aparece bien en stock
✅ Las imágenes en marketplace funcionan correctamente
