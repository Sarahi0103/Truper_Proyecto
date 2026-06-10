<?php
require_once 'config/config.php';

try {
    $stmt = $pdo->query("SELECT id, opened_by, opened_at, closed_at, status, opening_amount, closing_amount, expected_amount, difference_amount FROM cash_drawer_sessions ORDER BY id DESC LIMIT 15");
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== CASH DRAWER SESSIONS ===\n";
    foreach ($sessions as $s) {
        printf(
            "ID: %d | Status: %s | Opened: %s | Closed: %s | Open Amt: %.2f | Close Amt: %s\n",
            $s['id'],
            $s['status'],
            $s['opened_at'],
            $s['closed_at'] ?? 'NULL',
            $s['opening_amount'],
            $s['closing_amount'] ?? 'NULL'
        );
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
