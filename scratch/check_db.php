<?php
require_once __DIR__ . '/../config/database.php';

try {
    echo "=== Rows in wholesale_pricing ===\n";
    $q = $pdo->prepare("SELECT * FROM wholesale_pricing LIMIT 50");
    $q->execute();
    print_r($q->fetchAll(PDO::FETCH_ASSOC));

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
