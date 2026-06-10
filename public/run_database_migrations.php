<?php
/**
 * Run all database migrations for Truper Platform on Render
 */
require_once '../config/config.php';

// Only allow admin users or local execution
$is_cli = php_sapi_name() === 'cli';
$localhost = $is_cli || in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost']);
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

if (!$localhost && !$is_admin) {
    http_response_code(403);
    die('Acceso denegado. Debes iniciar sesión como administrador.');
}

header('Content-Type: text/plain; charset=utf-8');

echo "=== Iniciando Migraciones de Base de Datos ===\n\n";

$conn = $GLOBALS['pdo'];

// List of all migration files (relative to public/)
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

foreach ($migrations as $migrationFile) {
    echo "--- Ejecutando: " . basename($migrationFile) . " ---\n";
    
    if (!file_exists($migrationFile)) {
        echo "⚠ Archivo no encontrado\n\n";
        continue;
    }
    
    try {
        $sql = file_get_contents($migrationFile);
        
        // Robust SQL parser to split queries while preserving plpgsql functions ($$)
        $statements = [];
        $currentStatement = '';
        $inFunction = false;
        
        $lines = explode("\n", $sql);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            // Ignore empty lines or comments outside function bodies
            if (empty($trimmed) || preg_match('/^--/', $trimmed)) {
                if (!$inFunction) {
                    continue;
                }
            }
            
            // Detect PostgreSQL plpgsql $$ blocks
            if (strpos($line, '$$') !== false) {
                if (!$inFunction) {
                    $inFunction = true;
                    $currentStatement .= $line . "\n";
                } else {
                    $inFunction = false;
                    $currentStatement .= $line . "\n";
                    if (!empty(trim($currentStatement))) {
                        $statements[] = trim($currentStatement);
                    }
                    $currentStatement = '';
                }
            } elseif ($inFunction) {
                $currentStatement .= $line . "\n";
            } elseif (strpos($trimmed, ';') !== false) {
                // Split standard statements by semicolon
                $parts = explode(';', $trimmed);
                foreach ($parts as $part) {
                    $part = trim($part);
                    if (!empty($part)) {
                        $statements[] = $part . ';';
                    }
                }
            } else {
                $currentStatement .= $line . "\n";
            }
        }
        
        if (!empty(trim($currentStatement))) {
            $statements[] = trim($currentStatement);
        }
        
        $executed = 0;
        foreach ($statements as $statement) {
            if (empty($statement) || preg_match('/^--/', $statement)) {
                continue;
            }
            
            try {
                $conn->exec($statement);
                $executed++;
            } catch (PDOException $e) {
                // Ignore "already exists" or duplicate errors
                if (strpos($e->getMessage(), 'already exists') === false && 
                    strpos($e->getMessage(), 'duplicate') === false &&
                    strpos($e->getMessage(), 'already a member') === false) {
                    echo "  ✗ Error: " . substr($e->getMessage(), 0, 150) . "\n";
                } else {
                    $executed++;
                }
            }
        }
        
        echo "✓ Completado ({$executed} sentencias ejecutadas)\n\n";
        
    } catch (Exception $e) {
        echo "✗ Error: " . $e->getMessage() . "\n\n";
    }
}

echo "=== Finalizado ===\n";
