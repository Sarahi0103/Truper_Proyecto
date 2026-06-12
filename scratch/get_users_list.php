<?php
require_once __DIR__ . '/../config/config.php';

try {
    echo "=== CLIENTS IN DB ===\n";
    $stmt = $pdo->query("SELECT u.id, u.first_name, u.last_name, u.email, u.role, c.id AS client_id FROM users u LEFT JOIN clients c ON u.id = c.user_id WHERE u.role = 'client'");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

    echo "\n=== TICKETS IN DB ===\n";
    $stmt = $pdo->query("SELECT id, folio, customer_name, total_amount, payment_status, pickup_status, issued_date FROM sales_tickets ORDER BY issued_date DESC LIMIT 5");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
