<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM marketplace_ce_products");
    echo "Total marketplace products: " . $stmt->fetchColumn() . "\n";
    $stmt = $pdo->query("SELECT id, sku, name, is_active FROM marketplace_ce_products ORDER BY id DESC LIMIT 50");
    foreach ($stmt->fetchAll() as $row) {
        echo "- ID: {$row['id']} | SKU: {$row['sku']} | Name: {$row['name']} | Active: {$row['is_active']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
