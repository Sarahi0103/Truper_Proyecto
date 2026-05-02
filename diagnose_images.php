<?php
/**
 * Script de diagnóstico para problemas de carga de imágenes
 */

echo "=== DIAGNÓSTICO DE IMÁGENES ===\n\n";

// 1. Verificar directorios
echo "1. DIRECTORIOS:\n";
$dirs = [
    'public/images' => 'Raíz de imágenes',
    'public/images/products' => 'Productos',
    'public/images/products/gallery' => 'Galería (para nuevas imágenes)',
    'public/images/products/by_code' => 'Por código (legacy)',
];

foreach ($dirs as $dir => $desc) {
    $fullPath = __DIR__ . '/' . $dir;
    $exists = is_dir($fullPath);
    $perms = $exists ? substr(sprintf('%o', fileperms($fullPath)), -4) : 'N/A';
    $writable = $exists ? (is_writable($fullPath) ? '✓' : '✗') : 'N/A';
    echo "  [$writable] $dir ($desc) - Permisos: $perms\n";
}

// 2. Contar archivos
echo "\n2. ARCHIVOS:\n";
$galleryDir = __DIR__ . '/public/images/products/gallery';
$byCodeDir = __DIR__ . '/public/images/products/by_code';

$galleryCount = is_dir($galleryDir) ? count(glob($galleryDir . '/*')) : 0;
$byCodeCount = is_dir($byCodeDir) ? count(glob($byCodeDir . '/*')) : 0;

echo "  - Directorio gallery/: " . ($galleryCount > 0 ? "$galleryCount SKUs" : "Vacío o no existe") . "\n";
echo "  - Directorio by_code/: " . ($byCodeCount > 0 ? "$byCodeCount SKUs" : "Vacío") . "\n";

// 3. Crear directorio gallery si no existe
echo "\n3. CORRECCIONES APLICADAS:\n";

if (!is_dir($galleryDir)) {
    if (@mkdir($galleryDir, 0775, true)) {
        echo "  ✓ Directorio gallery/ creado\n";
    } else {
        echo "  ✗ No se pudo crear directorio gallery/\n";
    }
} else {
    echo "  ✓ Directorio gallery/ ya existe\n";
}

// 4. Verificar permisos
if (is_dir($galleryDir)) {
    $perms = substr(sprintf('%o', fileperms($galleryDir)), -4);
    if ($perms !== '0775' && $perms !== '0777') {
        if (@chmod($galleryDir, 0775)) {
            echo "  ✓ Permisos de gallery/ corregidos a 0775\n";
        } else {
            echo "  ⚠ No se pudieron cambiar permisos a 0775\n";
        }
    } else {
        echo "  ✓ Permisos de gallery/ están correctos ($perms)\n";
    }
}

// 5. Verificar conexión a BD y tabla de productos
echo "\n4. BASE DE DATOS:\n";
require_once 'config/config.php';

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "  ✓ Conexión a BD: OK\n";
    echo "  ✓ Tabla products: " . ($result['total'] ?? 0) . " registros\n";
    
    // Verificar si hay imágenes base64
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE image_url LIKE 'data:image%'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $base64Count = $result['total'] ?? 0;
    if ($base64Count > 0) {
        echo "  ⚠ PROBLEMA: $base64Count imágenes aún son base64\n";
        echo "    → Ejecuta: php clean_base64_images.php\n";
    } else {
        echo "  ✓ No hay imágenes base64 pendientes\n";
    }
} catch (Exception $e) {
    echo "  ✗ Error al conectar BD: " . $e->getMessage() . "\n";
}

echo "\n=== FIN DEL DIAGNÓSTICO ===\n";
?>
