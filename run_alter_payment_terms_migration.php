<?php
require_once 'config/config.php';

try {
    $sql = file_get_contents('db/ALTER_PAYMENT_TERMS.sql');
    $pdo->exec($sql);
    echo "Migración de alteración de términos de pago ejecutada exitosamente\n";
} catch (Exception $e) {
    echo "Error ejecutando migración: " . $e->getMessage() . "\n";
}
