<?php
require_once 'config/config.php';

try {
    $sql = file_get_contents('db/PERFORMANCE_INDICES.sql');
    $pdo->exec($sql);
    echo "Migración de índices de rendimiento ejecutada exitosamente\n";
} catch (Exception $e) {
    echo "Error ejecutando migración: " . $e->getMessage() . "\n";
}
