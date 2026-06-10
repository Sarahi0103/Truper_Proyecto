<?php
require_once 'config/config.php';

try {
    $sql = file_get_contents('db/TICKETS_CORRECTIONS.sql');
    $pdo->exec($sql);
    echo "Migración de correcciones de tickets ejecutada exitosamente\n";
} catch (Exception $e) {
    echo "Error ejecutando migración: " . $e->getMessage() . "\n";
}
