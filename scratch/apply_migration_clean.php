<?php
require_once __DIR__ . '/../config/config.php';

try {
    echo "=== Running TICKET_PICKUP_VALIDATION.sql as a single block ===\n";
    $sql = file_get_contents(__DIR__ . '/../db/TICKET_PICKUP_VALIDATION.sql');
    if ($sql === false) {
        throw new Exception("Could not read migration file.");
    }
    
    // We can run the entire SQL script in a single exec call
    $pdo->exec($sql);
    echo "✓ Migration applied successfully!\n";
    
} catch (Exception $e) {
    echo "✗ Error applying migration: " . $e->getMessage() . "\n";
}
