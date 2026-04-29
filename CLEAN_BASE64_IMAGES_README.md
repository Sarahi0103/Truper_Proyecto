# Instrucciones para Limpiar Imágenes Base64

## Problema
Las imágenes se estaban guardando como strings base64 gigantes en la BD en lugar de como archivos reales. Esto causa:
- Las imágenes no cargan correctamente
- Ralentiza la BD
- Interfiere con la visualización del "Modelo estándar"

## Solución
Se ha actualizado el código para guardar imágenes como archivos reales. Ahora hay un script para convertir las antiguas.

## Pasos

### Opción 1: En tu máquina local (desarrollo)
```bash
cd /workspaces/proyecto_Truper
php clean_base64_images.php
```

### Opción 2: En Render (producción)
```bash
# Conéctate por SSH a tu servicio Render
# Luego ejecuta:
php clean_base64_images.php
```

### Opción 3: Automático al desplegar
El script se puede ejecutar automáticamente desde un hook de pre-deploy si lo configuras en tu pipeline.

## ¿Qué hace el script?
1. Busca en la BD todos los campos que contengan strings base64 (`data:image/...`)
2. Los convierte a archivos reales en:
   - `images/products/by_code/{sku}/` (para galerías de productos)
   - `images/products/` (para imágenes principales)
3. Actualiza la BD con las nuevas rutas de archivo

## Después de ejecutar
- Las imágenes nuevas que subas se guardarán automáticamente como archivos
- Puedes eliminar el script: `rm clean_base64_images.php`

## Resultado esperado
✓ Imágenes cargando correctamente  
✓ "Modelo estándar" mostrándose bien en stock  
✓ Galerías en marketplace mostrando imágenes reales
