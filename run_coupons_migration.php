<?php
require_once 'config/config.php';

try {
    $sql = file_get_contents('db/COUPON_SYSTEM.sql');
    $pdo->exec($sql);
    echo "Migración de sistema de cupones ejecutada exitosamente\n";
} catch (Exception $e) {
    echo "Error ejecutando migración: " . $e->getMessage() . "\n";
}
