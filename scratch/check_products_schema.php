<?php
require_once __DIR__ . '/../config/config.php';

try {
    // Check columns of products table
    $q = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'products'");
    echo "Columns in products:\n";
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $col) {
        echo " - " . $col['column_name'] . " (" . $col['data_type'] . ")\n";
    }

    // Check count of products
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    echo "\nTotal products: " . $stmt->fetchColumn() . "\n";

    // Check query used in wholesale api
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE (is_active = true OR active = true)");
        echo "Products with (is_active=true or active=true): " . $stmt->fetchColumn() . "\n";
    } catch (Exception $e) {
        echo "Error querying active flags: " . $e->getMessage() . "\n";
    }

    try {
        $stmt = $pdo->query("SELECT DISTINCT category FROM products");
        echo "\nCategories in products:\n";
        print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
    } catch (Exception $e) {
        echo "Error querying categories: " . $e->getMessage() . "\n";
    }

    try {
        $stmt = $pdo->query("SELECT name, is_active FROM product_categories");
        echo "\nProduct categories active status:\n";
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo "Error querying product_categories: " . $e->getMessage() . "\n";
    }

    // Run the exact query from wholesale.php products endpoint
    try {
        $stmt = $pdo->query("SELECT id, name, sku, COALESCE(unit_price, sell_price, 0) AS unit_price FROM products WHERE (is_active = true OR active = true) AND NOT EXISTS (SELECT 1 FROM product_categories pc WHERE LOWER(pc.name) = LOWER(products.category) AND pc.is_active = false) ORDER BY name LIMIT 5");
        echo "\nWholesale products query sample results:\n";
        print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo "Error in wholesale query: " . $e->getMessage() . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
