<?php
require_once __DIR__ . '/../config/database.php';

try {
    $stmt = $pdo->prepare("
        SELECT column_name, data_type, character_maximum_length, numeric_precision, numeric_scale, column_default, is_nullable
        FROM information_schema.columns
        WHERE table_name = 'marketplace_ce_products'
    ");
    $stmt->execute();
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($cols, JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
