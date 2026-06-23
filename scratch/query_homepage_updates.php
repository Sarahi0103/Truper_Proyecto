<?php
require_once __DIR__ . '/../config/database.php';

try {
    $stmt = $pdo->query("SELECT id, title, design_template, is_active, brief_description, image_url, additional_images FROM homepage_updates ORDER BY position ASC, id DESC");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
