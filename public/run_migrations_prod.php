<?php
/**
 * Run migrations in production directly via URL using token.
 */
$token = $_GET['token'] ?? '';
if ($token !== 'TUS_MIGRATIONS_2026') {
    http_response_code(403);
    die('Acceso denegado');
}

header('Content-Type: text/plain; charset=utf-8');

require_once '../config/config.php';

echo "=== DATABASE CONFIG ===\n";
echo "Host: " . DB_HOST . "\n";
echo "Port: " . DB_PORT . "\n";
echo "DB Name: " . DB_NAME . "\n";
echo "User: " . DB_USER . "\n\n";

echo "=== RUNNING MIGRATIONS ===\n\n";

$conn = $GLOBALS['pdo'];

$migrations = [
    '../db/PERFORMANCE_INDICES.sql',
    '../db/ADD_STATUS_UPDATED_AT.sql',
    '../db/ANALYTICS_DATA_WAREHOUSE.sql',
    '../db/CASHIER_IMPROVEMENTS.sql',
    '../db/DASHBOARD_IMPROVEMENTS.sql',
    '../db/WHOLESALE_IMPROVEMENTS.sql',
    '../db/TASKS_IMPROVEMENTS.sql',
    '../db/SUPPLY_IMPROVEMENTS.sql',
    '../db/TICKETS_CORRECTIONS.sql',
    '../db/ALTER_PAYMENT_TERMS.sql',
    '../db/CART_PERSISTENCE.sql',
    '../db/COUPON_SYSTEM.sql',
    '../db/FAVORITES.sql',
    '../db/PASSWORD_RESET.sql',
    '../db/PRODUCT_REVIEWS.sql',
    '../db/TWO_FACTOR_AUTH.sql',
    '../db/create_indexes.sql'
];

foreach ($migrations as $file) {
    echo "--- Executing " . basename($file) . " ---\n";
    if (!file_exists($file)) {
        echo "  [WARNING] File not found\n\n";
        continue;
    }
    
    try {
        $sql = file_get_contents($file);
        $conn->exec($sql);
        echo "  [SUCCESS]\n\n";
    } catch (Exception $e) {
        echo "  [ERROR] " . $e->getMessage() . "\n\n";
    }
}

echo "=== All Migrations Finished ===\n";
