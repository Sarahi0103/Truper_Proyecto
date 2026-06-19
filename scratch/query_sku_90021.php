<?php
require_once __DIR__ . '/../config/database.php';

try {
    echo "=== products table ===\n";
    $stmt = $pdo->prepare("SELECT id, sku, name, unit_price, stock_quantity, is_active FROM products WHERE sku = ?");
    $stmt->execute(['90021']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

    echo "=== marketplace_ce_products table ===\n";
    $stmt = $pdo->prepare("SELECT id, sku, name, unit_price, stock_quantity, is_active FROM marketplace_ce_products WHERE sku = ?");
    $stmt->execute(['90021']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
