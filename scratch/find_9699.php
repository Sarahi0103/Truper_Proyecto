<?php
require_once __DIR__ . '/../config/database.php';

try {
    echo "=== Searching for products in catalog between 90 and 100 ===\n";
    $stmt = $pdo->prepare("SELECT id, sku, name, unit_price FROM products WHERE unit_price >= 90 AND unit_price <= 100");
    $stmt->execute();
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\n=== Searching for products in marketplace between 90 and 100 ===\n";
    $stmt = $pdo->prepare("SELECT id, sku, name, unit_price FROM marketplace_ce_products WHERE unit_price >= 90 AND unit_price <= 100");
    $stmt->execute();
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
