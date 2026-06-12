<?php
require_once __DIR__ . '/../config/config.php';

try {
    echo "Checking columns of 'sales_tickets':\n";
    $stmt = $pdo->query("
        SELECT column_name, data_type 
        FROM information_schema.columns 
        WHERE table_name = 'sales_tickets'
        ORDER BY ordinal_position
    ");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($columns)) {
        echo "❌ Table 'sales_tickets' does not exist!\n";
    } else {
        foreach ($columns as $col) {
            echo " - " . $col['column_name'] . " (" . $col['data_type'] . ")\n";
        }
    }

    echo "\nChecking existing tables:\n";
    $stmt = $pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public'
    ");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $t) {
        echo " * $t\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
