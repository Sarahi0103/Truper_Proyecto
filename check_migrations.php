<?php
/**
 * Script simple para verificar si los archivos de migración están disponibles
 * Accede a: https://truper-web-eg3h.onrender.com/check_migrations.php
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== Verificación de Archivos de Migración ===\n\n";

$migrations = [
    'db/ADD_STATUS_UPDATED_AT.sql',
    'db/ANALYTICS_DATA_WAREHOUSE.sql',
    'db/CASHIER_IMPROVEMENTS.sql',
    'db/DASHBOARD_IMPROVEMENTS.sql',
    'db/WHOLESALE_IMPROVEMENTS.sql',
    'db/TASKS_IMPROVEMENTS.sql',
    'db/SUPPLY_IMPROVEMENTS.sql',
    'db/TICKETS_CORRECTIONS.sql'
];

foreach ($migrations as $file) {
    if (file_exists($file)) {
        $size = filesize($file);
        echo "✓ {$file} ({$size} bytes)\n";
    } else {
        echo "✗ {$file} - NO ENCONTRADO\n";
    }
}

echo "\n=== Verificación de Conexión a Base de Datos ===\n";

try {
    require_once 'config/config.php';
    $conn = $GLOBALS['pdo'];
    echo "✓ Conexión a base de datos exitosa\n";
    
    // Verificar tablas existentes
    $stmt = $conn->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "\nTablas en base de datos (" . count($tables) . "):\n";
    foreach ($tables as $table) {
        echo "  - {$table}\n";
    }
    
} catch (Exception $e) {
    echo "✗ Error de conexión: " . $e->getMessage() . "\n";
}

echo "\n=== Finalizado ===\n";
