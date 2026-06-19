<?php
require_once __DIR__ . '/../config/database.php';

try {
    $stmt = $pdo->prepare("SELECT id, sku, name, unit_price FROM products WHERE sku LIKE ? OR sku LIKE ?");
    $stmt->execute(['%90021%', '%90021']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
