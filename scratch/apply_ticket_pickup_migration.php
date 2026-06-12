<?php
/**
 * Script temporal para aplicar la migración de la base de datos para la validación de tickets.
 * Versión corregida para no saltar sentencias que inician con comentarios.
 */

require_once __DIR__ . '/../config/config.php';

echo "=== Aplicando migración de validación de tickets (Corregido) ===\n";

try {
    $conn = $GLOBALS['pdo'];
    if (!$conn) {
        throw new Exception("No se pudo conectar a la base de datos.");
    }
    
    $migrationFile = __DIR__ . '/../db/TICKET_PICKUP_VALIDATION.sql';
    if (!file_exists($migrationFile)) {
        throw new Exception("Archivo de migración no encontrado: " . $migrationFile);
    }
    
    $sql = file_get_contents($migrationFile);
    
    // Ejecutar el SQL completo en un solo bloque para evitar problemas con funciones $$
    echo "Ejecutando migración completa...\n";
    
    try {
        $conn->exec($sql);
        echo "✓ Migración aplicada exitosamente\n";
    } catch (PDOException $e) {
        // Ignorar si las columnas o tablas ya existen
        $msg = $e->getMessage();
        if (strpos($msg, 'already exists') !== false || 
            strpos($msg, 'duplicate') !== false ||
            strpos($msg, 'already a relation') !== false) {
            echo "⚠ Algunos elementos ya existían (ignorado)\n";
        } else {
            echo "✗ Error: " . $msg . "\n";
            throw $e;
        }
    }
    
    echo "=== Migración completada con éxito ===\n";
    
} catch (Exception $e) {
    echo "✗ Error crítico en migración: " . $e->getMessage() . "\n";
    exit(1);
}
