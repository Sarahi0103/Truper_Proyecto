<?php
require_once __DIR__ . '/../config/database.php';

try {
    $q = $pdo->query("
        SELECT tablename, rulename, definition
        FROM pg_rules
        WHERE schemaname = 'public'
    ");
    $rules = $q->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rules, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
