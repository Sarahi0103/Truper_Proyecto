<?php
require_once __DIR__ . '/../config/database.php';

try {
    echo "Updating price to 40.00...\n";
    $stmt = $pdo->prepare("UPDATE marketplace_ce_products SET unit_price = 40.00 WHERE sku = ?");
    $stmt->execute(['90021']);
    
    echo "Retrieving price...\n";
    $stmt = $pdo->prepare("SELECT unit_price FROM marketplace_ce_products WHERE sku = ?");
    $stmt->execute(['90021']);
    $price = $stmt->fetchColumn();
    echo "Retrieved price from DB: " . var_export($price, true) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
