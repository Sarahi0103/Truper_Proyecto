<?php
require_once 'config/config.php';

try {
    $sql = file_get_contents('db/CART_PERSISTENCE.sql');
    $pdo->exec($sql);
    echo "Migración de persistencia de carrito ejecutada exitosamente\n";
} catch (Exception $e) {
    echo "Error ejecutando migración: " . $e->getMessage() . "\n";
}
