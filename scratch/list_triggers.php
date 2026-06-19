<?php
require_once __DIR__ . '/../config/database.php';

try {
    $q = $pdo->query("
        SELECT 
            event_object_table AS table_name,
            trigger_name,
            action_statement,
            action_timing,
            event_manipulation
        FROM information_schema.triggers
        WHERE event_object_schema = 'public'
    ");
    $triggers = $q->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($triggers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
