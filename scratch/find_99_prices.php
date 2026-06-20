<?php
require_once __DIR__ . '/../config/database.php';

try {
    echo "=== Catalog products with .99 in price ===\n";
    $stmt = $pdo->prepare("SELECT id, sku, name, unit_price FROM products WHERE CAST(unit_price AS TEXT) LIKE '%.99'");
    $stmt->execute();
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\n=== Marketplace products with .99 in price ===\n";
    $stmt = $pdo->prepare("SELECT id, sku, name, unit_price FROM marketplace_ce_products WHERE CAST(unit_price AS TEXT) LIKE '%.99'");
    $stmt->execute();
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
