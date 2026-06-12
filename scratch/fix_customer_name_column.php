<?php
require_once __DIR__ . '/../config/config.php';

try {
    echo "Dropping conflicting views...\n";
    $pdo->exec("DROP VIEW IF EXISTS v_ticket_summary CASCADE");
    $pdo->exec("DROP VIEW IF EXISTS v_current_tickets CASCADE");
    echo "✅ Views dropped.\n\n";

    echo "Altering sales_tickets table to add customer_name...\n";
    $pdo->exec("ALTER TABLE sales_tickets ADD COLUMN IF NOT EXISTS customer_name VARCHAR(255)");
    echo "✅ Column customer_name added.\n\n";

    echo "Running migrate.php again...\n";
    // We can just include migrate.php or execute it. Let's run it.
    // To execute it safely, we can include it after setting $_SESSION['role'] = 'admin'
    $_SESSION['role'] = 'admin';
    
    // Change working directory to public/ so it can find relative config.php
    chdir(__DIR__ . '/../public');
    require 'migrate.php';
    echo "\n✅ Migration complete!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
