<?php
require_once 'config/config.php';

try {
    $sql = file_get_contents('db/TWO_FACTOR_AUTH.sql');
    $pdo->exec($sql);
    echo "Migración de sistema 2FA ejecutada exitosamente\n";
} catch (Exception $e) {
    echo "Error ejecutando migración: " . $e->getMessage() . "\n";
}
