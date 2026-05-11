<?php
/**
 * Diagnóstico: Verificar que la carga de imágenes funciona correctamente
 * Ejecutar: php diagnose_images_fix.php
 */

echo "════════════════════════════════════════════════════════════\n";
echo "DIAGNÓSTICO DE CARGA DE IMÁGENES - Fix Verificación\n";
echo "════════════════════════════════════════════════════════════\n\n";

// 1. Verificar estructura de directorios esperada
echo "✓ PASO 1: Verificar estructura de directorios\n";
$dirs_to_check = [
    __DIR__ . '/images' => 'Raíz de imágenes',
    __DIR__ . '/images/products' => 'Productos',
    __DIR__ . '/images/products/gallery' => 'Galería (nuevas imágenes)',
    __DIR__ . '/public' => 'Carpeta public',
    __DIR__ . '/public/api' => 'API',
];

foreach ($dirs_to_check as $path => $desc) {
    $exists = is_dir($path);
    $status = $exists ? "✅ EXISTE" : "❌ NO EXISTE";
    echo "  $desc: $status\n";
    if ($exists) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        $writable = is_writable($path) ? "✅" : "❌";
        echo "    - Permisos: $perms | Escribible: $writable\n";
    }
}
echo "\n";

// 2. Verificar que admin_supply.php está actualizado
echo "✓ PASO 2: Verificar funciones de carga de imágenes\n";
$api_file = __DIR__ . '/public/api/admin_supply.php';
if (file_exists($api_file)) {
    $content = file_get_contents($api_file);
    
    $has_helper = strpos($content, 'function ensure_directory_exists') !== false;
    $has_sku_func = strpos($content, 'function store_product_image_for_sku_admin_supply') !== false;
    $has_product_func = strpos($content, 'function store_product_image') !== false;
    
    echo "  Función ensure_directory_exists: " . ($has_helper ? "✅ PRESENTE" : "❌ FALTANTE") . "\n";
    echo "  Función store_product_image_for_sku_admin_supply: " . ($has_sku_func ? "✅ PRESENTE" : "❌ FALTANTE") . "\n";
    echo "  Función store_product_image: " . ($has_product_func ? "✅ PRESENTE" : "❌ FALTANTE") . "\n";
} else {
    echo "  ❌ No se encontró admin_supply.php\n";
}
echo "\n";

// 3. Simular creación de directorios (sin conexión a BD)
echo "✓ PASO 3: Simular creación de estructura de directorios\n";
try {
    // Simular __DIR__ siendo /public/api
    $test_api_dir = __DIR__ . '/public/api';
    if (is_dir($test_api_dir)) {
        echo "  API dir: ✅ EXISTE\n";
        
        // Simular dirname(__DIR__) desde api
        $level1 = dirname($test_api_dir);
        echo "  dirname(__DIR__): $level1\n";
        
        $level2 = dirname($level1);
        echo "  dirname(dirname(__DIR__)): $level2\n";
        
        $expected_images = $level2 . '/images';
        echo "  Ruta esperada de imágenes: $expected_images\n";
    }
} catch (Exception $e) {
    echo "  ❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// 4. Verificar permisos
echo "✓ PASO 4: Verificar permisos generales\n";
$perms_check = [
    __DIR__ => 'Raíz proyecto',
    __DIR__ . '/public' => 'Public',
    __DIR__ . '/public/api' => 'API',
];

foreach ($perms_check as $path => $desc) {
    if (is_dir($path)) {
        $perms = substr(sprintf('%o', fileperms($path)), -4);
        echo "  $desc: $perms\n";
    }
}
echo "\n";

// 5. Crear directorio de prueba
echo "✓ PASO 5: Prueba de creación de directorio\n";
$test_dir = __DIR__ . '/images/test_upload_' . time();
try {
    if (!is_dir(__DIR__ . '/images')) {
        @mkdir(__DIR__ . '/images', 0777, true);
        @chmod(__DIR__ . '/images', 0777);
    }
    
    @mkdir($test_dir, 0777, true);
    @chmod($test_dir, 0777);
    
    if (is_dir($test_dir) && is_writable($test_dir)) {
        echo "  ✅ Crear y escribir en directorio: EXITOSO\n";
        
        // Intentar crear archivo
        $test_file = $test_dir . '/test.txt';
        if (@file_put_contents($test_file, 'test') !== false) {
            echo "  ✅ Crear archivo de prueba: EXITOSO\n";
            @unlink($test_file);
        } else {
            echo "  ❌ Crear archivo de prueba: FALLÓ\n";
        }
        
        // Limpiar
        @rmdir($test_dir);
    } else {
        echo "  ❌ No se pudo crear directorio o no es escribible\n";
    }
} catch (Exception $e) {
    echo "  ❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

// 6. Resumen
echo "════════════════════════════════════════════════════════════\n";
echo "RESUMEN\n";
echo "════════════════════════════════════════════════════════════\n\n";

$all_good = 
    is_dir(__DIR__ . '/images') &&
    is_dir(__DIR__ . '/images/products') &&
    is_writable(__DIR__ . '/images') &&
    $has_helper && $has_sku_func && $has_product_func;

if ($all_good) {
    echo "✅ SISTEMA LISTO PARA CARGAR IMÁGENES\n\n";
    echo "Próximos pasos:\n";
    echo "1. En Render: Espera a que termine el redeploy (2-3 minutos)\n";
    echo "2. Recarga la página de admin_supply.php\n";
    echo "3. Intenta cargar una imagen en un producto\n";
    echo "4. La galería debe renderizarse sin errores\n";
} else {
    echo "⚠️  ALGUNOS PROBLEMAS DETECTADOS\n\n";
    if (!is_dir(__DIR__ . '/images')) {
        echo "❌ Falta directorio /images\n";
    }
    if (!$has_helper) {
        echo "❌ Función ensure_directory_exists no encontrada\n";
    }
    if (!$has_sku_func) {
        echo "❌ Función store_product_image_for_sku_admin_supply no encontrada\n";
    }
}

echo "\n════════════════════════════════════════════════════════════\n";
?>
