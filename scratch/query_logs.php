<?php
require_once __DIR__ . '/../config/database.php';

try {
    $stmt = $pdo->query("SELECT id, user_id, action, description, timestamp FROM action_logs ORDER BY id DESC LIMIT 15");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
