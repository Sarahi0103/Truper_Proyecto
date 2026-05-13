#!/bin/bash
# Script para inicializar la estructura de directorios de imágenes
# Se ejecuta durante el deployment para asegurar que gallery/ existe

set -e

echo "=== Inicializando estructura de directorios ==="

# Directorios a crear
DIRS=(
    "public/images"
    "public/images/products"
    "public/images/products/gallery"
    "public/images/products/by_code"
)

for dir in "${DIRS[@]}"; do
    if [ ! -d "$dir" ]; then
        echo "Creando directorio: $dir"
        mkdir -p "$dir"
    else
        echo "Directorio existe: $dir"
    fi
    
    # Asegurar permisos de escritura
    if [ -d "$dir" ]; then
        chown -R www-data:www-data "$dir" 2>/dev/null || true
        chmod 775 "$dir" 2>/dev/null || true
        echo "  ✓ Permisos establecidos: $dir (775)"
    fi
done

# Verificar que el directorio principal es escribible
if [ -w "public/images/products/gallery" ]; then
    echo "✓ El directorio gallery es escribible"
else
    echo "⚠ Advertencia: El directorio gallery no es escribible"
    # Intentar cambiar permisos
    chmod 755 "public/images/products/gallery" 2>/dev/null || true
fi

chown -R www-data:www-data public/images 2>/dev/null || true
chmod -R u+rwX,g+rwX,o+rX public/images 2>/dev/null || true

echo "=== Inicialización completada ==="
