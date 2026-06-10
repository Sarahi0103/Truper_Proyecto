<?php
require_once __DIR__ . '/../config/config.php';

try {
    // Check tickets
    $stmt = $pdo->query("SELECT COUNT(*) FROM sales_tickets");
    $count = $stmt->fetchColumn();
    echo "Total rows in sales_tickets: " . $count . "\n";

    if ($count > 0) {
        $stmt = $pdo->query("SELECT * FROM sales_tickets LIMIT 5");
        echo "\nSample tickets:\n";
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // Check ticket items
    $stmt = $pdo->query("SELECT COUNT(*) FROM ticket_items");
    $itemsCount = $stmt->fetchColumn();
    echo "Total rows in ticket_items: " . $itemsCount . "\n";

    // Check orders
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $ordersCount = $stmt->fetchColumn();
    echo "Total rows in orders: " . $ordersCount . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
