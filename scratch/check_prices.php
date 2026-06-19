<?php
require_once __DIR__ . '/../config/database.php';

try {
    echo "=== products ===\n";
    $stmt = $pdo->query("SELECT id, sku, name, unit_price, sell_price FROM products ORDER BY id DESC LIMIT 50");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        echo "ID: {$row['id']} | SKU: {$row['sku']} | Name: {$row['name']} | Price: " . var_export($row['unit_price'], true) . " | SellPrice: " . var_export($row['sell_price'], true) . "\n";
    }

    echo "\n=== marketplace_ce_products ===\n";
    $stmt = $pdo->query("SELECT id, sku, name, unit_price FROM marketplace_ce_products ORDER BY id DESC LIMIT 50");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        echo "ID: {$row['id']} | SKU: {$row['sku']} | Name: {$row['name']} | Price: " . var_export($row['unit_price'], true) . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
