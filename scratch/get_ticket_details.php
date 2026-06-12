<?php
require_once __DIR__ . '/../config/config.php';

try {
    $stmt = $pdo->prepare("SELECT * FROM sales_tickets WHERE folio = '202606-00007'");
    $stmt->execute();
    print_r($stmt->fetch(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
