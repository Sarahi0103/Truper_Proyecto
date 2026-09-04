<?php
/**
 * Script para ejecutar migraciones de base de datos
 * Uso: php run_migrations.php
 */

require_once __DIR__ . '/config/config.php';

echo "=== Ejecutando Migraciones de Base de Datos ===\n\n";

$migrations = [
    'add_tax_fields_to_products.sql',
    'add_stock_reservations.sql',
    'add_payment_complements_table.sql',
    'add_coupons_system.sql',
    'add_user_addresses.sql',
    'add_shipping_tracking.sql',
    'add_online_inventory.sql',
    'add_product_reviews.sql',
    'consolidated_v2_and_advanced_features.sql',
    'add_stock_alerts.sql',
    'add_notifications.sql',
    'add_refunds.sql',
    'add_wishlist.sql',
    'add_advanced_coupons.sql',
    'add_translations.sql',
    'add_loyalty_points.sql',
    'add_supply_chain.sql',
    'add_mexican_banks.sql',
    'add_admin_payment_accounts.sql',
    'add_shopping_carts.sql',
    'add_sales_tickets.sql'
];

$migrationsPath = __DIR__ . '/database_migrations/';

foreach ($migrations as $migration) {
    $filePath = $migrationsPath . $migration;
    
    if (!file_exists($filePath)) {
        echo "⚠️  Migración no encontrada: {$migration}\n";
        continue;
    }
    
    echo "📝 Ejecutando: {$migration}...";
    
    try {
        $sql = file_get_contents($filePath);
        if (empty(trim($sql))) {
            echo " ⚠️ VACÍO\n";
            continue;
        }

        $pdo->exec($sql);
        echo " ✅ OK\n";
    } catch (Exception $e) {
        // En caso de que falle todo el bloque, intentar ejecutar con un parser más inteligente o reportar
        echo " ❌ ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Migraciones Completadas ===\n";
