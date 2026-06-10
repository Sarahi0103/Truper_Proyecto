<?php
require_once 'config/config.php';

try {
    $sql = file_get_contents('db/PRODUCT_REVIEWS.sql');
    $pdo->exec($sql);
    echo "Migración de sistema de reviews ejecutada exitosamente\n";
} catch (Exception $e) {
    echo "Error ejecutando migración: " . $e->getMessage() . "\n";
}
