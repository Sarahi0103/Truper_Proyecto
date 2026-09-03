<?php
/**
 * Script de Respaldo Automático de Base de Datos PostgreSQL y Sellos CSD del SAT
 * Truper / Ferretería FOX Platform
 */

if (php_sapi_name() !== 'cli' && !defined('BACKUP_ALLOWED')) {
    die("Acceso restringido a ejecuciones CLI o administradores autenticados.\n");
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/utils/AppLogger.php';

function runDatabaseSatBackup($pdo = null) {
    global $pdo;

    $backupDir = __DIR__ . '/../backups';
    if (!file_exists($backupDir)) {
        @mkdir($backupDir, 0755, true);
    }

    $timestamp = date('Y-m-d_H-i-s');
    $backupFileName = "backup_truper_sat_{$timestamp}.json";
    $backupFilePath = "{$backupDir}/{$backupFileName}";

    $tablesToBackup = [
        'users', 'products', 'product_categories', 'sales_tickets', 
        'sales_ticket_items', 'orders', 'order_items', 'system_settings',
        'marketplace_ce_products', 'expenses', 'transaction_history'
    ];

    $backupData = [
        'metadata' => [
            'created_at' => date('c'),
            'version' => '5.0-truper',
            'environment' => getenv('APP_ENV') ?: 'production',
            'tables_backed_up' => []
        ],
        'tables' => [],
        'sat_csd_status' => []
    ];

    foreach ($tablesToBackup as $table) {
        try {
            $stmt = $pdo->query("SELECT * FROM {$table}");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $backupData['tables'][$table] = $rows;
            $backupData['metadata']['tables_backed_up'][] = [
                'table_name' => $table,
                'record_count' => count($rows)
            ];
        } catch (Exception $e) {
            // Si la tabla no existe en este ambiente, continuar silenciosamente
            $backupData['tables'][$table] = [];
        }
    }

    // Respaldar estado de configuración fiscal SAT CSD
    try {
        $stmtSat = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE '%csd%' OR setting_key LIKE '%company%' OR setting_key LIKE '%facturapi%'");
        $backupData['sat_csd_status'] = $stmtSat->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {}

    $jsonContent = json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $bytesWritten = @file_put_contents($backupFilePath, $jsonContent);

    if ($bytesWritten === false) {
        AppLogger::error("Fallo al escribir archivo de respaldo en {$backupFilePath}");
        return ['success' => false, 'message' => 'No fue posible escribir en el directorio de respaldos.'];
    }

    // Limpieza de respaldos antiguos (más de 30 días)
    $files = glob("{$backupDir}/backup_truper_sat_*.json");
    $now = time();
    $deletedOldCount = 0;
    foreach ($files as $file) {
        if (is_file($file)) {
            if ($now - filemtime($file) >= 30 * 86400) {
                @unlink($file);
                $deletedOldCount++;
            }
        }
    }

    AppLogger::info("Respaldo de BD y SAT CSD generado exitosamente: {$backupFileName} (" . round($bytesWritten / 1024, 2) . " KB)");

    return [
        'success' => true,
        'filename' => $backupFileName,
        'filepath' => $backupFilePath,
        'filesize_kb' => round($bytesWritten / 1024, 2),
        'tables_count' => count($backupData['metadata']['tables_backed_up']),
        'created_at' => $backupData['metadata']['created_at']
    ];
}

// Si se ejecuta desde línea de comandos
if (php_sapi_name() === 'cli') {
    echo "==================================================\n";
    echo "📦 EJECUTANDO RESPALDO AUTOMÁTICO DE BD & SAT CSD\n";
    echo "==================================================\n";
    $result = runDatabaseSatBackup();
    if ($result['success']) {
        echo "✅ Respaldo exitoso: {$result['filename']} ({$result['filesize_kb']} KB)\n";
    } else {
        echo "❌ Error: {$result['message']}\n";
    }
}
