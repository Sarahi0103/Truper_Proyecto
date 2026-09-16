<?php
/**
 * Utility Script: Verify and sync shipping_tracking columns
 * Truper Platform
 */
require_once __DIR__ . '/../config/config.php';
global $pdo;

try {
    $pdo->exec("ALTER TABLE shipping_tracking ADD COLUMN IF NOT EXISTS shipped_date TIMESTAMP");
    $pdo->exec("ALTER TABLE shipping_tracking ADD COLUMN IF NOT EXISTS shipping_date TIMESTAMP");
    echo "Columnas de shipping_tracking sincronizadas correctamente.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
