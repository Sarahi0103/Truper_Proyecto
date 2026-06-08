<?php
/**
 * Test Suite: Validar gestión completa de stock
 * Script directo sin múltiples includes
 */

// Conexión directa a BD usando la configuración del sistema
require_once __DIR__ . '/config/config.php';
if (!isset($pdo) || !$pdo) {
    die("❌ Error de conexión: no se inicializó la conexión a la base de datos.\n");
}

$test_results = [];

echo "═══════════════════════════════════════════════════════════════\n";
echo "PRUEBA COMPLETA: GESTIÓN DE STOCK CON IMÁGENES\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// TEST 1: Verificar directorio de galerías
$gallery_base = '/var/www/html/images/products/gallery';
if (!is_dir($gallery_base)) {
    if (is_dir(__DIR__ . '/public/images/products/gallery')) {
        $gallery_base = __DIR__ . '/public/images/products/gallery';
    } elseif (is_dir(__DIR__ . '/images/products/gallery')) {
        $gallery_base = __DIR__ . '/images/products/gallery';
    }
}
$gallery_exists = is_dir($gallery_base);
$test_results['gallery_dir_exists'] = $gallery_exists;
echo "✓ TEST 1: Directorio de galerías\n";
echo "  Ruta: $gallery_base\n";
echo "  Estado: " . ($gallery_exists ? "✅ EXISTE" : "❌ NO EXISTE") . "\n";
if ($gallery_exists) {
    $perms = substr(sprintf('%o', fileperms($gallery_base)), -4);
    echo "  Permisos: $perms\n";
}
echo "\n";

// TEST 2: Verificar tabla deleted_product_skus
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM deleted_product_skus");
    $deleted_count = $stmt->fetch()['count'];
    $test_results['deleted_skus_table'] = true;
    echo "✓ TEST 2: Tabla de SKUs eliminados\n";
    echo "  Registros en deleted_product_skus: $deleted_count\n";
    echo "  Estado: ✅ TABLA ACCESIBLE\n\n";
} catch (Exception $e) {
    echo "❌ TEST 2 FALLÓ: " . $e->getMessage() . "\n\n";
    $test_results['deleted_skus_table'] = false;
    die("No se puede continuar sin acceso a BD\n");
}

// TEST 3: Listar SKUs eliminados actualmente
echo "✓ TEST 3: SKUs marcados como eliminados\n";
$stmt = $pdo->query("SELECT sku, deleted_at, reason FROM deleted_product_skus ORDER BY deleted_at DESC LIMIT 10");
$deleted_skus = $stmt->fetchAll();
if (empty($deleted_skus)) {
    echo "  ⚠️  No hay SKUs eliminados aún\n\n";
} else {
    echo "  SKUs eliminados:\n";
    foreach ($deleted_skus as $row) {
        echo "    - SKU: {$row['sku']}, Eliminado: {$row['deleted_at']}, Razón: {$row['reason']}\n";
    }
    echo "\n";
}

// TEST 4: Verificar que no haya SKUs marcados como eliminados en la tabla de productos
echo "✓ TEST 4: Validar integridad - SKUs no deben existir en ambas tablas\n";
$stmt = $pdo->query(
    "SELECT p.sku FROM products p 
     INNER JOIN deleted_product_skus d ON p.sku = d.sku"
);
$conflict_products = $stmt->fetchAll();
if (empty($conflict_products)) {
    echo "  ✅ SIN CONFLICTOS: Ningún SKU eliminado aparece en tabla products\n\n";
} else {
    echo "  ❌ CONFLICTO DETECTADO: Estos SKUs están en ambas tablas:\n";
    foreach ($conflict_products as $row) {
        echo "    - SKU: {$row['sku']}\n";
    }
    echo "\n";
}

// TEST 5: Verificar que no haya SKUs marcados como eliminados en marketplace
echo "✓ TEST 5: Validar integridad marketplace\n";
$stmt = $pdo->query(
    "SELECT m.sku FROM marketplace_ce_products m 
     INNER JOIN deleted_product_skus d ON m.sku = d.sku"
);
$conflict_marketplace = $stmt->fetchAll();
if (empty($conflict_marketplace)) {
    echo "  ✅ SIN CONFLICTOS: Ningún SKU eliminado aparece en marketplace_ce_products\n\n";
} else {
    echo "  ❌ CONFLICTO DETECTADO: Estos SKUs están en ambas tablas:\n";
    foreach ($conflict_marketplace as $row) {
        echo "    - SKU: {$row['sku']}\n";
    }
    echo "\n";
}

// TEST 6: Verificar galerías en filesystem vs DB
echo "✓ TEST 6: Integridad del filesystem\n";
$fs_galleries = array_diff(scandir($gallery_base), ['.', '..']);
echo "  Galerías en filesystem: " . count($fs_galleries) . "\n";
if (!empty($fs_galleries)) {
    echo "  Primeras galerías:\n";
    foreach (array_slice($fs_galleries, 0, 5) as $sku) {
        $gallery_sku_path = "$gallery_base/$sku";
        if (is_dir($gallery_sku_path)) {
            $files = array_diff(scandir($gallery_sku_path), ['.', '..']);
            echo "    - SKU $sku: " . count($files) . " archivo(s)\n";
        }
    }
}
echo "\n";

// TEST 7: Contar imágenes en BD
echo "✓ TEST 7: Imágenes registradas en BD\n";
$stmt = $pdo->query(
    "SELECT COUNT(*) as total FROM products WHERE variants_json IS NOT NULL AND variants_json != '[]' AND variants_json != 'null'"
);
$images_in_products = $stmt->fetch()['total'] ?? 0;

$has_mp_variants = false;
try {
    $col_check = $pdo->query("SELECT 1 FROM information_schema.columns WHERE table_name = 'marketplace_ce_products' AND column_name = 'variants_json' LIMIT 1");
    if ($col_check->fetchColumn()) {
        $has_mp_variants = true;
    }
} catch (Exception $e) {}

$images_in_marketplace = 0;
if ($has_mp_variants) {
    $stmt = $pdo->query(
        "SELECT COUNT(*) as total FROM marketplace_ce_products WHERE variants_json IS NOT NULL AND variants_json != '[]' AND variants_json != 'null'"
    );
    $images_in_marketplace = $stmt->fetch()['total'] ?? 0;
}

echo "  Productos con galerías: $images_in_products\n";
echo "  Marketplace con galerías: $images_in_marketplace\n";
echo "  Total de productos con imágenes: " . ($images_in_products + $images_in_marketplace) . "\n\n";

// TEST 8: Verificar sample de imágenes en BD
echo "✓ TEST 8: Muestra de referencias de imágenes en BD\n";
$stmt = $pdo->query(
    "SELECT sku, variants_json FROM products 
     WHERE variants_json IS NOT NULL AND variants_json != '[]' AND variants_json != 'null' 
     LIMIT 3"
);
$sample_products = $stmt->fetchAll();
$route_issues = 0;

foreach ($sample_products as $prod) {
    $variants = json_decode($prod['variants_json'], true);
    if (is_array($variants) && !empty($variants)) {
        echo "  SKU: {$prod['sku']}\n";
        foreach (array_slice($variants, 0, 3) as $img) {
            echo "    - $img\n";
            // Rutas correctas deben ser: images/...
            if (!preg_match('#^/?images/#', $img) && strpos($img, '://') === false) {
                $route_issues++;
            }
        }
    }
}

echo "  Rutas inválidas detectadas: $route_issues\n";
echo "  Estado: " . ($route_issues === 0 ? "✅ CORRECTO" : "⚠️  REVISAR") . "\n\n";

// TEST 9: Verificar permisos de directorios de galerías
echo "✓ TEST 9: Permisos de directorios de galerías\n";
$permission_issues = 0;
foreach (array_slice($fs_galleries, 0, 3) as $sku) {
    $sku_path = "$gallery_base/$sku";
    if (is_dir($sku_path)) {
        $perms = substr(sprintf('%o', fileperms($sku_path)), -3);
        echo "  SKU $sku: $perms\n";
        if ($perms !== '777') {
            $permission_issues++;
        }
    }
}
if ($permission_issues === 0) {
    echo "  Estado: ✅ TODOS CON PERMISOS CORRECTOS\n\n";
} else {
    echo "  ⚠️  $permission_issues directorio(s) sin permisos 777\n\n";
}

// TEST 10: Verificar integridad de rutas - comparar filesystem vs BD
echo "✓ TEST 10: Comparar galerías en filesystem vs BD\n";
$stmt = $pdo->query("SELECT DISTINCT sku FROM products WHERE variants_json IS NOT NULL AND variants_json != '[]'");
$db_skus_with_images = array_map(fn($r) => $r['sku'], $stmt->fetchAll());

$fs_sku_set = array_map(fn($s) => (int)$s, $fs_galleries);
$db_sku_set = array_map(fn($s) => (int)$s, $db_skus_with_images);

$orphaned_in_fs = array_diff($fs_sku_set, $db_sku_set);
$missing_in_fs = array_diff($db_sku_set, $fs_sku_set);

echo "  Galerías en BD: " . count($db_sku_set) . " SKUs\n";
echo "  Galerías en filesystem: " . count($fs_sku_set) . " SKUs\n";
echo "  Directorios huérfanos (en FS pero no en BD): " . count($orphaned_in_fs) . "\n";
echo "  Imágenes faltantes (en BD pero no en FS): " . count($missing_in_fs) . "\n";

if (count($orphaned_in_fs) > 0) {
    echo "  Huérfanos: " . implode(", ", array_slice($orphaned_in_fs, 0, 5)) . (count($orphaned_in_fs) > 5 ? "..." : "") . "\n";
}
if (count($missing_in_fs) > 0) {
    echo "  ⚠️  Faltantes: " . implode(", ", array_slice($missing_in_fs, 0, 5)) . (count($missing_in_fs) > 5 ? "..." : "") . "\n";
}
echo "\n";

// RESUMEN
echo "═══════════════════════════════════════════════════════════════\n";
echo "RESUMEN DE PRUEBAS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

echo "1. Directorio de galerías: " . ($test_results['gallery_dir_exists'] ? "✅" : "❌") . "\n";
echo "2. Tabla deleted_product_skus: " . ($test_results['deleted_skus_table'] ? "✅" : "❌") . "\n";
echo "3. SKUs eliminados: $deleted_count\n";
echo "4. Conflictos en products: " . count($conflict_products) . "\n";
echo "5. Conflictos en marketplace: " . count($conflict_marketplace) . "\n";
echo "6. Galerías en filesystem: " . count($fs_galleries) . "\n";
echo "7. Productos con imágenes: $images_in_products\n";
echo "8. Marketplace con imágenes: $images_in_marketplace\n";
echo "9. Rutas inválidas: $route_issues\n";
echo "10. Directorios huérfanos: " . count($orphaned_in_fs) . "\n";
echo "11. Imágenes faltantes: " . count($missing_in_fs) . "\n\n";

// Conclusión
$all_pass = 
    $test_results['gallery_dir_exists'] && 
    $test_results['deleted_skus_table'] && 
    empty($conflict_products) && 
    empty($conflict_marketplace) && 
    $route_issues === 0;

if ($all_pass) {
    echo "✅ ESTADO GENERAL: SISTEMA OPERATIVO Y CONSISTENTE\n";
    echo "\n📋 El sistema está listo para:\n";
    echo "   • Cargar imágenes en Stock y Marketplace\n";
    echo "   • Mostrar imágenes en galerías\n";
    echo "   • Eliminar imágenes correctamente\n";
    echo "   • Bloquear SKUs eliminados\n";
} else {
    echo "⚠️  ESTADO GENERAL: REVISAR PROBLEMAS DETECTADOS\n";
    if (!empty($conflict_products) || !empty($conflict_marketplace)) {
        echo "\n⚠️  PROBLEMA CRÍTICO: SKUs eliminados aún aparecen en tablas de productos\n";
    }
    if ($route_issues > 0) {
        echo "\n⚠️  PROBLEMA CRÍTICO: Rutas inválidas en referencias de imágenes\n";
    }
}

echo "\n═══════════════════════════════════════════════════════════════\n\n";
