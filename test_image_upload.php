<?php
/**
 * Prueba rápida para diagnosticar problema de carga de imágenes
 * Ejecutar desde terminal: php test_image_upload.php
 */

require_once 'config/config.php';

echo "=== TEST DE CARGA DE IMÁGENES ===\n\n";

// 1. Verificar directorios
echo "1. DIRECTORIOS:\n";
$dirs = [
    'public/images' => __DIR__ . '/public/images',
    'public/images/products' => __DIR__ . '/public/images/products',
    'public/images/products/gallery' => __DIR__ . '/public/images/products/gallery',
];

foreach ($dirs as $name => $path) {
    $exists = is_dir($path);
    $writable = $exists ? is_writable($path) : false;
    $status = $exists ? ($writable ? '✓ Existe y escribible' : '✗ Existe pero NO escribible') : '✗ No existe';
    echo "  $name: $status\n";
    
    if (!$exists) {
        echo "    → Creando...\n";
        mkdir($path, 0777, true);
        chmod($path, 0777);
        echo "    ✓ Creado\n";
    } elseif (!$writable) {
        echo "    → Fijando permisos...\n";
        chmod($path, 0777);
        echo "    ✓ Permisos ajustados\n";
    }
}

// 2. Test de escritura de archivo
echo "\n2. TEST DE ESCRITURA:\n";
$testFile = __DIR__ . '/public/images/products/gallery/test_' . time() . '.txt';
$written = file_put_contents($testFile, 'Test file');
if ($written) {
    echo "  ✓ Archivo de prueba escrito exitosamente\n";
    unlink($testFile);
    echo "  ✓ Archivo de prueba eliminado\n";
} else {
    echo "  ✗ No se pudo escribir archivo de prueba\n";
}

// 3. Test de base64 en BD
echo "\n3. BASE DE DATOS:\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE image_url LIKE 'data:image%'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $result['total'] ?? 0;
    if ($count > 0) {
        echo "  ⚠ $count productos tienen imágenes base64\n";
        echo "  → Ejecuta: php clean_base64_images.php\n";
    } else {
        echo "  ✓ No hay imágenes base64 en BD\n";
    }
} catch (Exception $e) {
    echo "  ✗ Error: " . $e->getMessage() . "\n";
}

echo "\n=== TEST COMPLETADO ===\n";
?>
