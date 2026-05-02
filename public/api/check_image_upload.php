<?php
/**
 * Endpoint para verificar si el sistema está listo para subir imágenes
 */
require_once '../config/config.php';
require_admin();

header('Content-Type: application/json');

$result = [
    'ready' => false,
    'checks' => [],
    'issues' => [],
    'message' => ''
];

// Verificar directorios
$dirs = [
    'gallery' => __DIR__ . '/../images/products/gallery',
    'products' => __DIR__ . '/../images/products',
];

foreach ($dirs as $name => $path) {
    $exists = is_dir($path);
    $writable = $exists && is_writable($path);
    
    $result['checks'][$name] = [
        'exists' => $exists,
        'writable' => $writable,
        'path' => $path
    ];
    
    if (!$exists) {
        $result['issues'][] = "Directorio $name no existe: $path";
        // Intentar crear
        if (@mkdir($path, 0777, true)) {
            $result['issues'][count($result['issues'])-1] = "✓ Directorio $name creado automáticamente";
            $result['checks'][$name]['exists'] = true;
            $result['checks'][$name]['writable'] = is_writable($path);
        }
    } elseif (!$writable) {
        $result['issues'][] = "Directorio $name no es escribible: $path";
        // Intentar cambiar permisos
        if (@chmod($path, 0777)) {
            $result['issues'][count($result['issues'])-1] = "✓ Permisos de $name corregidos";
            $result['checks'][$name]['writable'] = true;
        }
    }
}

// Verificar si se puede escribir un archivo
$testFile = __DIR__ . '/../images/products/gallery/.write_test_' . time();
$canWrite = @file_put_contents($testFile, '');
if ($canWrite === false) {
    $result['issues'][] = "No se puede escribir en el directorio gallery";
} else {
    @unlink($testFile);
}

// Verificar BD
$result['database'] = ['status' => 'unknown'];
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products");
    $result['database']['status'] = 'connected';
    $result['database']['total_products'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (Exception $e) {
    $result['issues'][] = "Error de BD: " . $e->getMessage();
}

// Determinar si está listo
$result['ready'] = empty($result['issues']) && 
                   $result['checks']['gallery']['exists'] && 
                   $result['checks']['gallery']['writable'];

if ($result['ready']) {
    $result['message'] = 'Sistema listo para cargar imágenes';
} else {
    $result['message'] = 'Hay problemas que evitan cargar imágenes. ' . implode('; ', $result['issues']);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
