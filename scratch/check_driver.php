<?php
require_once __DIR__ . '/../config/config.php';
echo "Driver: " . $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) . "\n";

// Check the column type of is_wholesale in the local database
$stmt = $pdo->prepare("SELECT data_type FROM information_schema.columns WHERE table_name = 'orders' AND column_name = 'is_wholesale'");
$stmt->execute();
echo "is_wholesale type: " . $stmt->fetchColumn() . "\n";
