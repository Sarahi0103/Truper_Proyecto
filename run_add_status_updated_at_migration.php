<?php
require_once 'config/config.php';

try {
    $sql = file_get_contents('db/ADD_STATUS_UPDATED_AT.sql');
    $pdo->exec($sql);
    echo "Migración de status_updated_at ejecutada exitosamente\n";
} catch (Exception $e) {
    echo "Error ejecutando migración: " . $e->getMessage() . "\n";
}
