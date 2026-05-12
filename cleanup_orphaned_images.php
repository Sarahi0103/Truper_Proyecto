<?php
/**
 * Limpiar directorios de imágenes huérfanas (SKUs eliminados de BD)
 */

require_once __DIR__ . '/config/config.php';

echo "=== LIMPIEZA DE IMÁGENES HUÉRFANAS ===\n\n";

$gallery_root = __DIR__ . '/images/products/gallery';

// Obtener SKUs en disco
$sku_dirs = array_map('basename', glob($gallery_root . '/*', GLOB_ONLYDIR));
sort($sku_dirs);

// Obtener SKUs en BD
$stmt = $pdo->query("SELECT DISTINCT sku FROM products ORDER BY sku");
$db_skus = array_map(fn($r) => $r['sku'], $stmt->fetchAll());

// Diferencia - SKUs en disco pero NO en BD = HUÉRFANOS
$orphaned = array_diff($sku_dirs, $db_skus);

echo "SKUs huérfanos encontrados: " . count($orphaned) . "\n";
echo "Estos directorios serán eliminados:\n\n";

$deleted = 0;
$failed = 0;

foreach ($orphaned as $sku) {
    $dir = $gallery_root . '/' . $sku;
    
    // Contar archivos
    $files = glob($dir . '/*');
    $file_count = count(array_filter($files, 'is_file'));
    
    printf("  Eliminando SKU %s... ", $sku);
    
    try {
        // Eliminar directorio recursivamente
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }
        rmdir($dir);
        
        echo "✅ ($file_count archivos eliminados)\n";
        $deleted++;
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n=== RESULTADO ===\n";
echo "✅ Eliminados: $deleted directorios\n";
if ($failed > 0) {
    echo "❌ Errores: $failed\n";
}
echo "\nLa BD ahora coincide con los directorios de imágenes.\n";
?>
