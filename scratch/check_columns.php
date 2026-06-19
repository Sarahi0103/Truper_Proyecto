<?php
require_once __DIR__ . '/../config/database.php';

try {
    $stmt = $pdo->prepare("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = ?");
    $stmt->execute(['marketplace_ce_products']);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
