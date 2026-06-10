<?php
require_once __DIR__ . '/../config/config.php';

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM transaction_history");
    $totalCount = (int)$stmt->fetchColumn();
    echo "Total rows in transaction_history: $totalCount\n";

    if ($totalCount > 0) {
        $stmt = $pdo->query("SELECT * FROM transaction_history ORDER BY created_at DESC LIMIT 10");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        print_r($rows);
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
