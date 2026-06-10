<?php
/**
 * Script simple para ejecutar migraciones SQL
 */

require_once 'config/config.php';

echo "=== Ejecutando Migraciones de Mejoras ===\n\n";

$conn = $GLOBALS['pdo'];

$migrations = [
    'db/PERFORMANCE_INDICES.sql',
    'db/DASHBOARD_IMPROVEMENTS.sql',
    'db/ANALYTICS_DATA_WAREHOUSE.sql',
    'db/CASHIER_IMPROVEMENTS.sql',
    'db/WHOLESALE_IMPROVEMENTS.sql',
    'db/TASKS_IMPROVEMENTS.sql',
    'db/SUPPLY_IMPROVEMENTS.sql',
    'db/TICKETS_CORRECTIONS.sql'
];

foreach ($migrations as $migrationFile) {
    echo "--- Ejecutando: {$migrationFile} ---\n";
    
    if (!file_exists($migrationFile)) {
        echo "⚠ Archivo no encontrado\n\n";
        continue;
    }
    
    try {
        $sql = file_get_contents($migrationFile);
        
        // Manejar bloques de código PostgreSQL ($$ ... $$)
        $statements = [];
        $currentStatement = '';
        $inFunction = false;
        
        $lines = explode("\n", $sql);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            // Ignorar comentarios vacíos
            if (empty($trimmed) || preg_match('/^--/', $trimmed)) {
                if (!$inFunction) {
                    continue;
                }
            }
            
            // Detectar inicio/fin de funciones PostgreSQL
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
                // Dividir statements regulares por ;
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
        
        // Agregar cualquier statement restante
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
                echo "  ✓ Executed\n";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'already exists') === false && 
                    strpos($e->getMessage(), 'duplicate') === false) {
                    echo "  ✗ Error: " . substr($e->getMessage(), 0, 100) . "\n";
                } else {
                    echo "  ⊘ Already exists\n";
                    $executed++;
                }
            }
        }
        
        echo "✓ Completado ({$executed} statements)\n\n";
        
    } catch (Exception $e) {
        echo "✗ Error: " . $e->getMessage() . "\n\n";
    }
}

echo "=== Finalizado ===\n";
