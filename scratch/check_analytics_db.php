<?php
require_once 'c:/Users/ksgom/proyecto_Truper/config/config.php';

echo "--- CHECKING TABLES ---\n";
$tables = ['purchase_statistics', 'ai_predictions', 'orders', 'order_items', 'products', 'sales_tickets', 'supplier_orders'];
foreach ($tables as $t) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM $t");
        $stmt->execute();
        $count = $stmt->fetchColumn();
        echo "Table '$t' exists and has $count rows.\n";
    } catch (Exception $e) {
        echo "Table '$t' ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n--- RUNNING getHistoricalDataForPrediction TEST ---\n";
try {
    // Test purchase_statistics query
    $stmt = $pdo->prepare("
        SELECT 
            product_id,
            p.name,
            p.sku,
            month,
            total_quantity,
            season,
            year
        FROM purchase_statistics
        JOIN products p ON purchase_statistics.product_id = p.id
        WHERE year >= ? 
        ORDER BY product_id, year DESC, month DESC
        LIMIT 5
    ");
    $stmt->execute([date('Y') - 2]);
    $rows = $stmt->fetchAll();
    echo "purchase_statistics query succeeded, returned " . count($rows) . " rows.\n";
} catch (Exception $e) {
    echo "purchase_statistics query FAILED: " . $e->getMessage() . "\n";
}

try {
    // Test fallback query
    $fallbackStmt = $pdo->prepare("
        SELECT
            oi.product_id,
            p.name,
            p.sku,
            EXTRACT(MONTH FROM o.created_at)::int AS month,
            SUM(oi.quantity)::int AS total_quantity,
            EXTRACT(YEAR FROM o.created_at)::int AS year
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        JOIN products p ON p.id = oi.product_id
        WHERE o.created_at >= NOW() - INTERVAL '24 months'
        GROUP BY oi.product_id, p.name, p.sku, EXTRACT(YEAR FROM o.created_at), EXTRACT(MONTH FROM o.created_at)
        ORDER BY oi.product_id, year DESC, month DESC
        LIMIT 5
    ");
    $fallbackStmt->execute();
    $fallbackRows = $fallbackStmt->fetchAll();
    echo "Fallback order_items query succeeded, returned " . count($fallbackRows) . " rows.\n";
} catch (Exception $e) {
    echo "Fallback order_items query FAILED: " . $e->getMessage() . "\n";
}
