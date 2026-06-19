<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pCount = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $mCount = $pdo->query("SELECT COUNT(*) FROM marketplace_ce_products")->fetchColumn();
    echo "Products count: " . $pCount . "\n";
    echo "Marketplace count: " . $mCount . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
