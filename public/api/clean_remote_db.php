<?php
/**
 * Script de desinfección de base de datos para entorno de producción Render
 */
header('Content-Type: application/json; charset=utf-8');

// Clave de seguridad simple para evitar ejecuciones no autorizadas
$key = $_GET['key'] ?? '';
if ($key !== 'TruperClean2026') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit(0);
}

try {
    $pdo = include __DIR__ . '/../../config/database.php';
    $sqlFile = __DIR__ . '/../../db/RESET_EMPTY_DATABASE.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception('No se encontró el archivo de esquema limpio');
    }
    
    $sql = file_get_contents($sqlFile);
    $pdo->exec($sql);
    
    echo json_encode([
        'success' => true,
        'message' => 'Base de datos remota de Render reiniciada exitosamente a 0 (Tablas limpias)'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
