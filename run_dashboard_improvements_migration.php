<?php
require_once 'config/config.php';

try {
    $sql = file_get_contents('db/DASHBOARD_IMPROVEMENTS.sql');
    $pdo->exec($sql);
    echo "Migración de mejoras de dashboard ejecutada exitosamente\n";
} catch (Exception $e) {
    echo "Error ejecutando migración: " . $e->getMessage() . "\n";
}
