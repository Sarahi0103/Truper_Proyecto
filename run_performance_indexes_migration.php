<?php
/**
 * Script para aplicar migración de índices de rendimiento
 * Ejecutar: php run_performance_indexes_migration.php
 */

require_once __DIR__ . '/config/database.php';

echo "=== Migración de Índices de Rendimiento ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Leer el archivo SQL
    $sqlFile = __DIR__ . '/db/ADD_PERFORMANCE_INDEXES.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("Archivo SQL no encontrado: {$sqlFile}");
    }

    $sql = file_get_contents($sqlFile);
    
    // Dividir en sentencias individuales
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $successCount = 0;
    $skipCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $statement) {
        if (empty($statement) || strpos($statement, '--') === 0) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
            echo "✓ " . substr($statement, 0, 60) . "...\n";
            $successCount++;
        } catch (PDOException $e) {
            // Si el índice ya existe, no es un error
            if (strpos($e->getMessage(), 'already exists') !== false) {
                echo "⊘ " . substr($statement, 0, 60) . "... (ya existe)\n";
                $skipCount++;
            } else {
                echo "✗ " . substr($statement, 0, 60) . "... ERROR: " . $e->getMessage() . "\n";
                $errorCount++;
            }
        }
    }
    
    echo "\n=== Resumen ===\n";
    echo "Creados: {$successCount}\n";
    echo "Ya existían: {$skipCount}\n";
    echo "Errores: {$errorCount}\n";
    
    if ($errorCount === 0) {
        echo "\n✓ Migración completada exitosamente\n";
    } else {
        echo "\n⚠ Migración completada con {$errorCount} errores\n";
    }
    
} catch (Exception $e) {
    echo "\n✗ Error fatal: " . $e->getMessage() . "\n";
    exit(1);
}
