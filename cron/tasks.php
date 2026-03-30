<?php
/**
 * Cron Job para ejecutar tareas programadas
 * 
 * Configurar en crontab:
 * 0 8 * * * /usr/bin/php /var/www/truper_platform/cron/tasks.php
 */

require_once '../config/config.php';
require_once '../src/utils/BirthdayReminder.php';
require_once '../src/controllers/AnalyticsController.php';

// Configurar output
define('CRON_LOG', '../logs/cron.log');

function log_cron($message) {
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message" . PHP_EOL;
    
    @file_put_contents(CRON_LOG, $log_message, FILE_APPEND);
    echo $log_message;
}

try {
    log_cron("=== Iniciando tareas programadas ===");
    
    // 1. Procesar recordatorios de cumpleaños
    log_cron("Procesando recordatorios de cumpleaños...");
    $reminder = new BirthdayReminder($pdo);
    $birthday_result = $reminder->processBirthdayReminders();
    log_cron("Cumpleaños procesados: " . $birthday_result['processed']);
    
    // 2. Generar predicciones de demanda
    log_cron("Generando predicciones de demanda...");
    $analytics = new AnalyticsController($pdo);
    $predictions = $analytics->generatePredictions();
    log_cron("Predicciones generadas: " . count($predictions));
    
    // 3. Limpiar sesiones antiguas
    log_cron("Limpiando sesiones antiguas...");
    $stmt = $pdo->prepare("DELETE FROM action_logs WHERE timestamp < NOW() - INTERVAL '90 days'");
    $stmt->execute();
    log_cron("Logs antiguos eliminados");
    
    // 4. Alertas de inventario bajo
    log_cron("Verificando niveles de reorden...");
    $stmt = $pdo->prepare("
        SELECT id, name, stock_quantity, reorder_level 
        FROM products 
        WHERE stock_quantity <= reorder_level AND is_active = true
    ");
    $stmt->execute();
    $low_stock = $stmt->fetchAll();
    log_cron("Productos con stock bajo: " . count($low_stock));
    
    log_cron("=== Tareas programadas completadas ===");
    
} catch (Exception $e) {
    log_cron("ERROR: " . $e->getMessage());
}
?>
