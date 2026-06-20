<?php
require_once __DIR__ . '/../config/database.php';

try {
    echo "=== All products in catalog ===\n";
    $stmt = $pdo->prepare("SELECT id, sku, name, unit_price FROM products LIMIT 100");
    $stmt->execute();
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\n=== All products in marketplace ===\n";
    $stmt = $pdo->prepare("SELECT id, sku, name, unit_price FROM marketplace_ce_products LIMIT 100");
    $stmt->execute();
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
