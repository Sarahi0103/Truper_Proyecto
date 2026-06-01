<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    echo "Total products: " . $stmt->fetchColumn() . "\n";
    
    $stmt = $pdo->query("SELECT sku, name, is_active, description FROM products ORDER BY id DESC LIMIT 50");
    echo "\nProducts List:\n";
    foreach ($stmt->fetchAll() as $row) {
        echo "- SKU: {$row['sku']} | Name: {$row['name']} | Active: {$row['is_active']} | Desc: {$row['description']}\n";
    }

    $stmt = $pdo->query("SELECT COUNT(*) FROM marketplace_ce_products");
    echo "\nTotal marketplace products: " . $stmt->fetchColumn() . "\n";

    $stmt = $pdo->query("SELECT sku, name, is_active FROM marketplace_ce_products ORDER BY id DESC LIMIT 50");
    echo "\nMarketplace Products List:\n";
    foreach ($stmt->fetchAll() as $row) {
        echo "- SKU: {$row['sku']} | Name: {$row['name']} | Active: {$row['is_active']}\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
