<?php
/**
 * Sincronizar imágenes existentes en disco a la BD
 * Busca todas las imágenes en images/products/gallery/{sku}/ 
 * y actualiza los registros en BD con la ruta canónica
 */

require_once __DIR__ . '/config/config.php';

echo "=== SINCRONIZANDO IMÁGENES A BASE DE DATOS ===\n\n";

$gallery_root = __DIR__ . '/images/products/gallery';

if (!is_dir($gallery_root)) {
    echo "❌ Directorio de galerías no existe: $gallery_root\n";
    exit(1);
}

$updated = 0;
$skipped = 0;
$errors = 0;

// Escanear directorios de SKU
$sku_dirs = glob($gallery_root . '/*', GLOB_ONLYDIR);

echo "Encontrados " . count($sku_dirs) . " directorios de SKU\n\n";

foreach ($sku_dirs as $sku_dir) {
    $sku = basename($sku_dir);
    
    // Obtener archivos de imagen
    $images = glob($sku_dir . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
    $images = array_filter($images, 'is_file');
    
    if (empty($images)) {
        continue;
    }
    
    // Ordenar por nombre
    sort($images);
    
    // La primera imagen es el thumbnail
    $first_image_path = 'images/products/gallery/' . $sku . '/' . basename($images[0]);
    
    // Ver si el producto existe en BD
    $stmt = $pdo->prepare("SELECT id, image_url FROM products WHERE sku = ?");
    $stmt->execute([$sku]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        $skipped++;
        continue;
    }
    
    // Verificar si ya tiene la imagen canónica correcta
    if ($product['image_url'] === $first_image_path) {
        $skipped++;
        continue;
    }
    
    // Actualizar
    try {
        $stmt = $pdo->prepare("UPDATE products SET image_url = ? WHERE sku = ?");
        $stmt->execute([$first_image_path, $sku]);
        
        // También actualizar marketplace si existe
        $stmt = $pdo->prepare("UPDATE marketplace_ce_products SET image_url = ? WHERE sku = ?");
        $stmt->execute([$first_image_path, $sku]);
        
        $updated++;
        echo "✅ SKU $sku: actualizado con " . count($images) . " imágenes\n";
        
    } catch (Exception $e) {
        $errors++;
        echo "❌ SKU $sku: " . $e->getMessage() . "\n";
    }
}

echo "\n=== RESULTADO ===\n";
echo "✅ Actualizados: $updated\n";
echo "⏭️  Saltados: $skipped\n";
echo "❌ Errores: $errors\n";

if ($updated > 0) {
    echo "\n✅ SINCRONIZACIÓN COMPLETADA\n";
    echo "Las imágenes ahora están vinculadas en la BD con la ruta canónica.\n";
} else {
    echo "\n⏭️  No había nada que sincronizar.\n";
}
?>
