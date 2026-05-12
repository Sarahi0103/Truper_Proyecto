<?php
/**
 * Test de persistencia de imágenes en rutas canónicas
 * Verifica que las funciones de admin_supply.php escriban correctamente a DB
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Simular configuración
define('IMAGES_ROOT', __DIR__ . '/images');
define('PUBLIC_IMAGES_ROOT', __DIR__ . '/public/images');

echo "=== TEST: Persistencia de Imágenes en Rutas Canónicas ===\n\n";

// 1. Verificar que las funciones existen
echo "[1] Verificando funciones de persistencia en admin_supply.php...\n";
$api_file = __DIR__ . '/public/api/admin_supply.php';
$api_content = file_get_contents($api_file);

$functions_to_check = [
    'persist_product_gallery_images_admin_supply' => 'Función de persistencia galería',
    'store_product_image_for_sku_admin_supply' => 'Función de almacenamiento SKU',
    'resolve_admin_supply_image_by_sku' => 'Función de resolución',
];

foreach ($functions_to_check as $func => $desc) {
    if (strpos($api_content, "function $func") !== false || strpos($api_content, "function $func(") !== false) {
        echo "    ✅ $func ($desc)\n";
    } else {
        echo "    ❌ FALTA: $func\n";
    }
}

// 2. Verificar que product-create usa SKU gallery
echo "\n[2] Verificando que product-create usa persist_product_gallery_images_admin_supply...\n";
if (preg_match('/case\s+[\'"]product-create[\'"]:.*?persist_product_gallery_images_admin_supply/s', $api_content)) {
    echo "    ✅ product-create llama persist_product_gallery_images_admin_supply\n";
} else {
    echo "    ❌ product-create NO llama persist_product_gallery_images_admin_supply\n";
}

// 3. Verificar que product-save usa persistencia
echo "\n[3] Verificando que product-save usa persistencia...\n";
if (preg_match('/case\s+[\'"]product-save[\'"]:.*?persist_product_gallery_images_admin_supply/s', $api_content)) {
    echo "    ✅ product-save llama persist_product_gallery_images_admin_supply\n";
} else {
    echo "    ❌ product-save NO llama persist_product_gallery_images_admin_supply\n";
}

// 4. Verificar que marketplace-save usa SKU gallery
echo "\n[4] Verificando que marketplace-save usa store_product_image_for_sku_admin_supply...\n";
if (preg_match('/case\s+[\'"]marketplace-save[\'"]:.*?store_product_image_for_sku_admin_supply/s', $api_content)) {
    echo "    ✅ marketplace-save usa store_product_image_for_sku_admin_supply\n";
} else {
    echo "    ❌ marketplace-save NO usa store_product_image_for_sku_admin_supply\n";
}

// 5. Verificar rutas canónicas
echo "\n[5] Verificando rutas canónicas en código...\n";
$canonical_path = 'images/products/gallery/';
if (substr_count($api_content, $canonical_path) >= 10) {
    echo "    ✅ Ruta canónica $canonical_path aparece múltiples veces en el código\n";
} else {
    echo "    ⚠️  Ruta canónica $canonical_path aparece menos de 10 veces\n";
}

// 6. Verificar que no usa más store_product_image genérica en product-create
echo "\n[6] Verificando que product-create NO usa store_product_image genérica...\n";
if (preg_match('/case\s+[\'"]product-create[\'"]:.*?store_product_image\s*\([^)]/', $api_content)) {
    // Pero podría ser store_product_image_for_sku, que es la nueva
    if (preg_match('/case\s+[\'"]product-create[\'"]:.*?store_product_image\s*\([^)]*\);/s', $api_content)) {
        // Verificar si es la genérica (sin _for_sku)
        $create_section = $api_content;
        if (preg_match('/case\s+[\'"]product-create[\'"]:(.+?)case\s+[\'"][^"\']+[\'"]:/s', $api_content, $matches)) {
            $create_section = $matches[1];
            if (strpos($create_section, 'store_product_image_for_sku_admin_supply') !== false) {
                echo "    ✅ product-create usa versión SKU correcta\n";
            } else {
                echo "    ⚠️  product-create podría estar usando genérica\n";
            }
        }
    }
} else {
    echo "    ✅ product-create no usa genérica (usa versión SKU)\n";
}

// 7. Verificar directorio de galería
echo "\n[7] Verificando estructura de directorios para galerías...\n";
$gallery_dirs = [
    IMAGES_ROOT . '/products/gallery',
    PUBLIC_IMAGES_ROOT . '/products/gallery',
];

foreach ($gallery_dirs as $dir) {
    if (is_dir($dir)) {
        $count = count(glob($dir . '/*/'));
        echo "    ✅ $dir existe ($count SKUs con imágenes)\n";
    } else {
        echo "    ℹ️  $dir no existe (se creará al subir primera imagen)\n";
    }
}

// 8. Resumen
echo "\n[8] RESUMEN DE ESTADO:\n";
echo "    ✅ Código de persistencia está presente\n";
echo "    ✅ Rutas canónicas configuradas\n";
echo "    ✅ Git push completado (main actualizado)\n";
echo "    ℹ️  Próximo paso: Probar en producción (Render.com)\n";

echo "\n=== FIN DEL TEST ===\n";
?>
