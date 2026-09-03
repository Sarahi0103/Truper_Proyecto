<?php
/**
 * Cron Job: Factura Global Diaria
 * Se debe ejecutar diariamente (ejemplo: 0 1 * * * php /path/to/cron/daily_global_invoice.php)
 * Genera factura global para las ventas del día anterior que no tienen factura individual
 * Truper Platform
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Services/GlobalInvoiceService.php';
require_once __DIR__ . '/../src/utils/AppLogger.php';

$logger = new AppLogger();

try {
    $logger->info("Iniciando cron job de factura global diaria");
    
    $service = new GlobalInvoiceService($pdo);
    
    // Generar factura global del día anterior
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $result = $service->generateDailyGlobalInvoice($yesterday);
    
    if ($result['success']) {
        if ($result['invoice']) {
            $logger->info("Factura global generada exitosamente para {$yesterday}: UUID {$result['invoice']['uuid']}, Total: {$result['invoice']['total']}, Ventas: {$result['invoice']['sales_count']}");
        } else {
            $logger->info("No hubo ventas para facturar en {$yesterday}");
        }
    } else {
        $logger->error("Error generando factura global: " . ($result['message'] ?? 'Unknown error'));
    }
    
} catch (Exception $e) {
    $logger->error("Error en cron job de factura global: " . $e->getMessage());
    exit(1);
}

exit(0);
?>
