<?php
/**
 * Endpoint de diagnóstico para problemas de imágenes
 */
require_once '../config/config.php';
require_admin();

header('Content-Type: application/json');

$diagnosis = [];

// 1. Verificar directorios
$dirs = [
    'images' => 'public/images',
    'products' => 'public/images/products', 
    'gallery' => 'public/images/products/gallery',
    'by_code' => 'public/images/products/by_code',
];

$diagnosis['directories'] = [];
foreach ($dirs as $name => $path) {
    $fullPath = __DIR__ . '/' . $path;
    $exists = is_dir($fullPath);
    $writable = $exists ? is_writable($fullPath) : false;
    $perms = $exists ? substr(sprintf('%o', fileperms($fullPath)), -4) : 'N/A';
    
    $diagnosis['directories'][$name] = [
        'path' => $path,
        'exists' => $exists,
        'writable' => $writable,
        'permissions' => $perms
    ];
}

// 2. Contar archivos
$diagnosis['file_counts'] = [];
foreach (['gallery', 'by_code'] as $dir) {
    $path = __DIR__ . '/public/images/products/' . $dir;
    if (is_dir($path)) {
        $items = array_diff(scandir($path), ['.', '..']);
        $diagnosis['file_counts'][$dir] = count($items);
    } else {
        $diagnosis['file_counts'][$dir] = 0;
    }
}

// 3. Verificar BD
$diagnosis['database'] = [];
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $diagnosis['database']['products_total'] = $result['total'] ?? 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE image_url LIKE 'data:image%'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $diagnosis['database']['products_with_base64'] = $result['total'] ?? 0;
    
    $diagnosis['database']['status'] = 'connected';
} catch (Exception $e) {
    $diagnosis['database']['status'] = 'error';
    $diagnosis['database']['error'] = $e->getMessage();
}

// 4. Soluciones recomendadas
$diagnosis['recommendations'] = [];

if (!$diagnosis['directories']['gallery']['exists']) {
    $diagnosis['recommendations'][] = "Crear directorio: mkdir -p public/images/products/gallery";
}

if ($diagnosis['directories']['gallery']['exists'] && !$diagnosis['directories']['gallery']['writable']) {
    $diagnosis['recommendations'][] = "Dar permisos de escritura: chmod 775 public/images/products/gallery";
}

if (($diagnosis['database']['products_with_base64'] ?? 0) > 0) {
    $diagnosis['recommendations'][] = "Convertir imágenes base64: ejecutar php clean_base64_images.php";
}

echo json_encode($diagnosis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
