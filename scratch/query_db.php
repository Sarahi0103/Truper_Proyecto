<?php
require_once __DIR__ . '/../config/database.php';

try {
    $stmt = $pdo->query("SELECT id, sku, name, unit_price, stock_quantity, is_active FROM marketplace_ce_products ORDER BY id DESC LIMIT 10");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
