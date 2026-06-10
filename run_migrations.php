<?php
/**
 * Script para ejecutar todas las migraciones de mejoras en Render
 * Sube este archivo a Render y accede a: https://truper-web.onrender.com/run_migrations.php?token=TU_TOKEN_SEGURO
 */

// Token de seguridad para evitar ejecución no autorizada
$SECURITY_TOKEN = getenv('MIGRATION_TOKEN') ?: 'TUS_MIGRATIONS_2026';

if (!isset($_GET['token']) || $_GET['token'] !== $SECURITY_TOKEN) {
    http_response_code(403);
    die('Acceso denegado. Token inválido.');
}

// Configurar headers
header('Content-Type: text/plain; charset=utf-8');

echo "=== Iniciando Migraciones de Mejoras del Sistema ===\n\n";

// Conectar a la base de datos
require_once 'config/config.php';

try {
    $conn = $GLOBALS['pdo'];
    echo "✓ Conexión a base de datos exitosa\n\n";
} catch (Exception $e) {
    die("✗ Error de conexión: " . $e->getMessage() . "\n");
}

// Lista de scripts de migración en orden de ejecución
$migrations = [
    'db/ADD_STATUS_UPDATED_AT.sql',           // Pedidos
    'db/ANALYTICS_DATA_WAREHOUSE.sql',        // Estadísticas
    'db/CASHIER_IMPROVEMENTS.sql',           // Caja
    'db/DASHBOARD_IMPROVEMENTS.sql',         // Dashboard
    'db/WHOLESALE_IMPROVEMENTS.sql',         // Mayoreo
    'db/TASKS_IMPROVEMENTS.sql',            // Tareas
    'db/SUPPLY_IMPROVEMENTS.sql',           // Abastecimiento
    'db/TICKETS_CORRECTIONS.sql'             // Tickets
];

$results = [];

foreach ($migrations as $migrationFile) {
    echo "--- Ejecutando: {$migrationFile} ---\n";

    if (!file_exists($migrationFile)) {
        echo "⚠ Archivo no encontrado: {$migrationFile}\n\n";
        $results[$migrationFile] = 'not_found';
        continue;
    }

    try {
        $sql = file_get_contents($migrationFile);
        
        // Dividir el SQL en statements individuales
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        $executed = 0;
        $errors = 0;
        
        foreach ($statements as $statement) {
            if (empty($statement) || preg_match('/^--/', $statement)) {
                continue;
            }
            
            try {
                $conn->exec($statement);
                $executed++;
            } catch (PDOException $e) {
                // Ignorar errores de "already exists" o "duplicate"
                if (strpos($e->getMessage(), 'already exists') !== false || 
                    strpos($e->getMessage(), 'duplicate') !== false) {
                    // Es normal, el objeto ya existe
                    continue;
                }
                $errors++;
                echo "  Error: " . $e->getMessage() . "\n";
            }
        }
        
        if ($errors === 0) {
            echo "✓ Migración completada ({$executed} statements ejecutados)\n\n";
            $results[$migrationFile] = 'success';
        } else {
            echo "⚠ Migración completada con {$errors} errores\n\n";
            $results[$migrationFile] = 'partial';
        }
        
    } catch (Exception $e) {
        echo "✗ Error ejecutando migración: " . $e->getMessage() . "\n\n";
        $results[$migrationFile] = 'error';
    }
}

// Resumen
echo "\n=== Resumen de Migraciones ===\n";
$success = 0;
$partial = 0;
$errors = 0;
$not_found = 0;

foreach ($results as $file => $status) {
    switch ($status) {
        case 'success':
            echo "✓ {$file}\n";
            $success++;
            break;
        case 'partial':
            echo "⚠ {$file} (parcial)\n";
            $partial++;
            break;
        case 'error':
            echo "✗ {$file} (error)\n";
            $errors++;
            break;
        case 'not_found':
            echo "? {$file} (no encontrado)\n";
            $not_found++;
            break;
    }
}

echo "\nTotal: " . count($results) . " migraciones\n";
echo "Exitosas: {$success}\n";
echo "Parciales: {$partial}\n";
echo "Con errores: {$errors}\n";
echo "No encontradas: {$not_found}\n";

if ($success === count($results)) {
    echo "\n✓ Todas las migraciones se ejecutaron exitosamente\n";
} else {
    echo "\n⚠ Algunas migraciones tuvieron problemas. Revisa los errores arriba.\n";
}

echo "\n=== Finalizado ===\n";
