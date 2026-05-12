<?php
/**
 * Verificador de directorios huérfanos (SKUs con imágenes pero sin producto en BD)
 * Útil para ejecutar periódicamente
 */

require_once __DIR__ . '/config/config.php';

$gallery_root = __DIR__ . '/images/products/gallery';

// Obtener SKUs en disco
$sku_dirs = array_map('basename', glob($gallery_root . '/*', GLOB_ONLYDIR));
sort($sku_dirs);

// Obtener SKUs en BD
$stmt = $pdo->query("SELECT DISTINCT sku FROM products ORDER BY sku");
$db_skus = array_map(fn($r) => $r['sku'], $stmt->fetchAll());

// Diferencia - SKUs en disco pero NO en BD = HUÉRFANOS
$orphaned = array_diff($sku_dirs, $db_skus);

if (empty($orphaned)) {
    echo "✅ Sin directorios huérfanos - OK\n";
    exit(0);
} else {
    echo "❌ ENCONTRADOS " . count($orphaned) . " DIRECTORIOS HUÉRFANOS:\n";
    echo implode("\n", $orphaned) . "\n";
    echo "\nPara limpiar, ejecuta: php cleanup_orphaned_images.php\n";
    exit(1);
}
?>
